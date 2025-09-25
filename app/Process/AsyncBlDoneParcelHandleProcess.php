<?php

declare(strict_types=1);
/**
 * 提单结束将提单下所有包裹丢入【AsyncParcelChannelNodeSwitchProcess】切换任务.
 *

 */

namespace App\Process;



use App\Model\ParcelExportModel;
use App\Model\ParcelImportModel;
use App\Model\ParcelSendModel;
use App\Model\ParcelTrunkModel;
use App\Service\BlService;
use App\Service\ParcelChannelNodeSwitchService;
use Hyperf\Logger\Logger;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Process\AbstractProcess;

class AsyncBlDoneParcelHandleProcess extends AbstractProcess
{
    /**
     * 进程数量.
     */
    public int $nums = 1;

    /**
     * 进程名称.
     */
    public string $name = 'AsyncBlDoneParcelHandleProcess';

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

    protected Logger    $logger;
    protected BlService $blService;
    public function handle(): void
    {
        $redis_key    = 'queues:' . $this->name;
        $redis        = $this->container->get(\Redis::class);
        $this->logger = $this->container->get(LoggerFactory::class)->get('log', 'ParcelChannelNodeSwitchProcess');
        // 第一个参数对应日志的 name, 第二个参数对应 config/autoload/logger.php 内的 key
        $ParcelChannelNodeSwitchService = \Hyperf\Support\make(ParcelChannelNodeSwitchService::class);
        while (true) {
            $redis_data = $redis->rPop($redis_key);
            if ($redis_data) {
                $redis_data     = json_decode($redis_data, true);
                $bl_sn          = $redis_data['bl_sn'];
                $table          = $redis_data['table'];
                $where          = [];
                $where['bl_sn'] = $bl_sn;
                switch ($table) {
                    case 'default':
                    case 'parcel_transport':

                        break;
                    case 'parcel_send':
                        $query = ParcelSendModel::query();
                        break;
                    case 'parcel_export':
                        $query = ParcelExportModel::query();
                        break;
                    case 'parcel_trunk':
                        $query = ParcelTrunkModel::query();
                        break;
                    case 'parcel_import':
                        $query = ParcelImportModel::query();
                        break;

                }
                if (!empty($query)) {
                    $parcelDb = $query->where($where)
                        ->select(['order_sys_sn', 'member_uid', 'parent_join_uid', 'parent_agent_uid', 'sort'])
                        ->get()->toArray();
                    foreach ($parcelDb as $key => $parcel) {
                        $ParcelChannelNodeSwitchService->lPush(order_sys_sn: $parcel['order_sys_sn'], sort: $parcel['sort']);
                    }
                    $this->logger->info('提单结束：丢入队列切换下级渠道', $parcelDb);
                }
            }
            else{
                sleep(10);
            }
        }
    }


}
