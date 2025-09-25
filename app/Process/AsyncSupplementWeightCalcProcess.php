<?php

declare(strict_types=1);
/**
 * 异步运费计算.
 *

 */

namespace App\Process;


use App\Common\Lib\Crypt;
use App\Service\Express\ExpressService;
use App\Service\ParcelWeightCalcService;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Process\AbstractProcess;

class AsyncSupplementWeightCalcProcess extends AbstractProcess
{
    /**
     * 进程数量.
     */
    public int $nums = 1;

    /**
     * 进程名称.
     */
    public string $name = 'AsyncSupplementWeightCalcProcess';

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


    protected ExpressService $ExpressService;
    protected Crypt $Crypt;

    public function handle(): void
    {
        $redis_key = 'queues:AsyncSupplementWeightCalcProcess';
        $redis     = $this->container->get(\Redis::class);
        // 第一个参数对应日志的 name, 第二个参数对应 config/autoload/logger.php 内的 key
        $this->logger            = $this->container->get(LoggerFactory::class)->get('log', 'AsyncSupplementWeightCalcProcess');
        $parcelWeightCalcService = \Hyperf\Support\make(ParcelWeightCalcService::class, [$this->logger]);
        while (true) {
            $redis_data = $redis->rPop($redis_key);
            if ($redis_data) {
                $redis_data    = json_decode($redis_data, true);
                $order_sys_sn  = $redis_data['order_sys_sn'];
                $member_weight = $redis_data['member_weight'];
                $join_weight   = $redis_data['join_weight'];
                try {
                    $supplementWeightCalc = $parcelWeightCalcService->supplementWeightCalc(order_sys_sn: $order_sys_sn, member_weight: $member_weight, join_weight: $join_weight);
                    if ($supplementWeightCalc['memberSupplementFee'] > 0) {
                        $supplementWeightCalcToSave = $parcelWeightCalcService->supplementWeightCalcToSave($supplementWeightCalc);
                        if ($supplementWeightCalcToSave['code'] == 201) {
                            $this->logger->info('补重量计算Task-保存报错：' . $order_sys_sn . ' ' . $supplementWeightCalcToSave['msg'], []);
                        }
                        if ($supplementWeightCalcToSave['code'] == 200) {
                            $data['order_sys_sn']       = $order_sys_sn;
                            $data['member_sett_status'] = 0;
                            $data['join_sett_status']   = 0;
                            $redis->lPush('queues:AsyncBillSettlementProcess', json_encode($data)); // 将订单丢入到整理数据明细中
                        }

                    }
                } catch (\Throwable $e) {
                    $this->logger->info('补重量计算异常：' . $order_sys_sn . ' ', [$e->getMessage() . $e->getFile() . $e->getLine()]);
                }
            }
            if (empty($redis_data)) {
                sleep(1);
            }
        }
    }


}
