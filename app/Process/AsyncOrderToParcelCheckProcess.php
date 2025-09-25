<?php

declare(strict_types=1);
/**
 * 转包检查，获取渠道等
 */

namespace App\Process;

use App\Service\AnalyseChannelService;
use App\Service\ParcelWeightCalcService;
use Hyperf\DbConnection\Db;
use Hyperf\Logger\Logger;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Process\AbstractProcess;
use function App\Common\batchUpdateSql;

class AsyncOrderToParcelCheckProcess extends AbstractProcess
{
    /**
     * 进程数量.
     */
    public int $nums = 1;

    /**
     * 进程名称.
     */
    public string $name = 'AsyncOrderToParcelCheckProcess';

    /**
     * 重定向自定义进程的标准输入和输出.
     */
    public bool $redirectStdinStdout = false;

    /**
     * 管道类型.
     */
    public int $pipeType = 2;

    /**
     * 是否启用协程.
     */
    public bool $enableCoroutine = true;

    protected Logger $logger;

    public function handle(): void
    {
        $redis_key             = 'queues:' . $this->name;
        $redis                 = $this->container->get(\Redis::class);
        $analyseChannelService = \Hyperf\Support\make(AnalyseChannelService::class);
        $this->logger          = $this->container->get(LoggerFactory::class)->get('log', 'AsyncOrderToParcelCheckProcess');
        while (true) {
            $redis_data = $redis->rPop($redis_key);
            if ($redis_data) {
                $redis_data    = json_decode($redis_data, true);
                $order_sys_sys = $redis_data['order_sys_sn'];
                try {

                    $AnalyseChannelResult = $analyseChannelService->analyse($order_sys_sys);
                    $msg                  = '';
                    Db::beginTransaction();
                    try {
                        //订单表更新渠道信息
                        if (!empty($AnalyseChannelResult['updateChannel'])) {
                            $batchUpdateSql = batchUpdateSql('order', $AnalyseChannelResult['updateChannel'],['order_sys_sn']);
                            Db::update($batchUpdateSql);
                        }
                        if (isset($AnalyseChannelResult['orderException']) && !empty($AnalyseChannelResult['orderException'])) {
                            $order_sys_snArr = array_column($AnalyseChannelResult['orderException'], 'order_sys_sn');
                            Db::table("order_exception")->whereIn('order_sys_sn', $order_sys_snArr)->delete();
                            Db::table("order_exception")->insert($AnalyseChannelResult['orderException']);
                        }
                        if (isset($AnalyseChannelResult['orderExceptionItem']) && !empty($AnalyseChannelResult['orderExceptionItem'])) {
                            $order_sys_snArr = array_unique(array_column($AnalyseChannelResult['orderException'], 'order_sys_sn'));
                            Db::table("order_exception_item")->whereIn('order_sys_sn', $order_sys_snArr)->delete();
                            Db::table("order_exception_item")->insert($AnalyseChannelResult['orderExceptionItem']);
                        }
                        if (isset($AnalyseChannelResult['orderParcelLog']) && !empty($AnalyseChannelResult['orderParcelLog'])) {
                            $order_sys_snArr = array_unique(array_column($AnalyseChannelResult['orderParcelLog'], 'order_sys_sn'));
                            Db::table("order_parcel_log")->whereIn('order_sys_sn', $order_sys_snArr)->delete();
                            Db::table("order_parcel_log")->insert($AnalyseChannelResult['orderParcelLog']);
                        }
                        if (!empty($AnalyseChannelResult['orderParcelPay'])) {
                            $logger = \Hyperf\Support\make(LoggerFactory::class)->get('log', 'AsyncSupplementWeightCalcProcess');
                            foreach ($AnalyseChannelResult['orderParcelPay'] as $orderParcelPay) {
                                $parcelWeightCalcService = \Hyperf\Support\make(ParcelWeightCalcService::class, [$logger, $orderParcelPay['member']]);
                                $parcelWeightCalcService->orderToParcelCalc([$orderParcelPay['order_sys_sn']], $orderParcelPay['member']);
                            }
                        }
                        Db::commit();

                        $msg = '操作成功';
                    } catch (\Throwable $e) {
                        $msg = $e->getMessage();
                        Db::rollBack();
                    }
                } catch (\Throwable $e) {
                    $AnalyseChannelResult['order_sys_sn'] = $order_sys_sys;
                    $this->logger->info('转包异常->' . $e->getMessage(), [$redis_data, $AnalyseChannelResult]);
                }

            }
            else{
                sleep(10);
            }
        }
    }


}
