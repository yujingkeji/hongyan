<?php

declare(strict_types=1);
/**
 * 账单异步统计、结算处理.
 *

 */

namespace App\Process;

use App\Common\Lib\Arr;
use App\Service\BillSettlementService;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Process\AbstractProcess;

class AsyncBillSettlementProcess extends AbstractProcess
{
    /**
     * 进程数量.
     */
    public int $nums = 1;

    /**
     * 进程名称.
     */
    public string $name = 'AsyncBillSettlementProcess';

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


    public function handle(): void
    {
        $redis_key = 'queues:' . $this->name;
        $redis     = $this->container->get(\Redis::class);
        // 第一个参数对应日志的 name, 第二个参数对应 config/autoload/logger.php 内的 key
        $logger                = \Hyperf\Support\make(LoggerFactory::class)->get('log', 'AsyncBillSettlementProcess');
        $BillSettlementService = \Hyperf\Support\make(BillSettlementService::class);
        $BillSettlementService->logger($logger);
        while (true) {
            $redis_data = $redis->rPop($redis_key);
            if ($redis_data) {
                $redis_data         = json_decode($redis_data, true);
                $order_sys_sn       = $redis_data['order_sys_sn'];
                $member_sett_status = Arr::hasArr($redis_data, 'member_sett_status') ? $redis_data['member_sett_status'] : 0;
                $join_sett_status   = Arr::hasArr($redis_data, 'join_sett_status') ? $redis_data['join_sett_status'] : 0;
                try {
                    $settlement = $BillSettlementService->settlement($order_sys_sn, $member_sett_status, $join_sett_status);
                    if ($settlement['code'] == 200) {
                        $logger->info('订单异步结算成功：->', $settlement);
                    }
                } catch (\Throwable $e) {
                    $logger->info('订单异步结算异常：->', $redis_data);
                }
            }
            if (empty($redis_data)) {
                sleep(10);
            }
        }
    }


}
