<?php

declare(strict_types=1);
/**
 * 包裹渠道节点切换服务.
 *

 */

namespace App\Process;

use App\Model\DeliveryOrderPackModel;
use App\Model\DeliveryStationModel;
use App\Model\ParcelModel;
use App\Model\ParcelSendModel;
use App\Model\WarehouseParcelLocationModel;
use App\Service\BlService;
use App\Service\ParcelChannelNodeSwitchService;
use Hyperf\DbConnection\Db;
use Hyperf\Logger\Logger;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Process\AbstractProcess;

class AsyncParcelChannelNodeSwitchProcess extends AbstractProcess
{
    /**
     * 进程数量.
     */
    public int $nums = 1;

    /**
     * 进程名称.
     */
    public string $name = 'AsyncParcelChannelNodeSwitchProcess';

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
    protected BlService $blService;


    public function handle(): void
    {
        $redis_key = 'queues:' . $this->name;
        $redis     = $this->container->get(\Redis::class);
        // 第一个参数对应日志的 name, 第二个参数对应 config/autoload/logger.php 内的 key
        $this->logger                   = $this->container->get(LoggerFactory::class)->get('log', 'ParcelChannelNodeSwitchProcess');
        $ParcelChannelNodeSwitchService = \Hyperf\Support\make(ParcelChannelNodeSwitchService::class);
        $this->blService                = \Hyperf\Support\make(BlService::class);

        while (true) {
            $redis_data = $redis->rPop($redis_key);
            if ($redis_data) {
                $redis_data            = json_decode($redis_data, true);
                $order_sys_sn          = $redis_data['order_sys_sn'];
                $sort                  = $redis_data['sort'];
                $where                 = [];
                $where['order_sys_sn'] = $order_sys_sn;
                try {
                    $parcelUpdate = [];
                    $parcelDb     = ParcelModel::query()->where($where)
                        ->with([
                            'send' => function ($query) {
                                $query->select(['order_sys_sn', 'bl_sn', 'send_status']);
                            }
                        ])
                        ->select(['order_sys_sn', 'transport_sn', 'member_uid', 'parent_join_uid', 'parent_agent_uid', 'channel_id', 'channel_content'])
                        ->first()->toArray();
                    $msg          = 'send：' . $parcelDb['send']['send_status'];
                    if ($parcelDb['send']['send_status'] == 0) {
                        $msg .= ' ,集货发出后移除、禁止切换';
                        $this->logger->info('出口报关：' . $order_sys_sn . '->' . $msg, $redis_data);
                        continue;
                    }
                    $channel_content = json_decode($parcelDb['channel_content'], true);
                    $ParcelChannelNodeSwitchService->channelNodeInit(channel_id: 0, channelData: $channel_content);
                    $nextNode = $ParcelChannelNodeSwitchService->nextNode($sort);
                    //当前节点表更新
                    $currentNodeUpdate = [];
                    $parcel_table      = '';
                    switch ($nextNode['currentKey']) {
                        case "send":
                            $parcel_table                            = 'parcel_send';
                            $currentNodeUpdate['parcel_send_status'] = 78;
                            break;
                        case "export":
                            $parcel_table                              = 'parcel_export';
                            $currentNodeUpdate['parcel_export_status'] = 109;
                            break;
                        case "trunk":
                            $parcel_table                             = 'parcel_trunk';
                            $currentNodeUpdate['parcel_trunk_status'] = 128;
                            break;
                        case "import":
                            $parcel_table                              = 'parcel_import';
                            $currentNodeUpdate['parcel_import_status'] = 169; //清关结束
                            break;
                        case "transport":
                            $parcel_table                                 = 'parcel_transport';
                            $currentNodeUpdate['parcel_transport_status'] = 210; //派送签收
                            break;
                    }
                    switch ($nextNode['nodeKey']) {
                        case "export":


                            $nextNodeExport = $ParcelChannelNodeSwitchService->nextNodeExport(nodeSourceDb: $nextNode['nodeSourceDb'], ParcelDb: $parcelDb);
                            $parcelUpdate   = ['parcel_status' => 80, 'idx_sort' => $nextNodeExport['sort']]; //parcel_status=80,报关作业中
                            $bl_sn          = (string)$parcelDb['send']['bl_sn'];
                            $this->nextNodeBl(bl_sn: $bl_sn, nextNodeExport: $nextNodeExport);
                            // 查询包裹信息
                            $warehouseParcelLocationDb = Db::table('warehouse_parcel_location')->where('order_sys_sn', $order_sys_sn)->first();
                            Db::beginTransaction();
                            try {
                                Db::table('parcel_export')->updateOrInsert($where, $nextNodeExport);
                                Db::table('parcel')->where('order_sys_sn', '=', $order_sys_sn)->update($parcelUpdate);
                                Db::table($parcel_table)->where($where)->update($currentNodeUpdate);
                                // 修改预报包裹订单的状态
                                Db::table('delivery_station')->where('order_sys_sn', $order_sys_sn)->update(['delivery_status' => DeliveryStationModel::STATUS_SEND]);
                                Db::table('delivery_station_parcel')->where('order_sys_sn', $order_sys_sn)->update(['delivery_status' => DeliveryStationModel::STATUS_SEND]);
                                Db::commit();
                                // 修改打包时的库位状态
                                if ($warehouseParcelLocationDb) {
                                    Db::table('warehouse_parcel_location')->where('order_sys_sn', $order_sys_sn)->update(['status' => WarehouseParcelLocationModel::STATUS_SEND, 'desc' => '已出库发货结单']);
                                    // 修改包裹在位数量
                                    Db::table('warehouse_storage_location')->where('storage_location_id', $warehouseParcelLocationDb->storage_location_id)->decrement('num');
                                }
                                // 查询拣货任务是否结束
                                $pickNo = ParcelModel::where('order_sys_sn', $order_sys_sn)->value('pick_no');
                                if (!in_array($pickNo, [0, ''])) {
                                    $pickCount = ParcelModel::where('pick_no', $pickNo)
                                        ->whereHas('send', function ($query) {
                                            $query->where('parcel_send_status', ParcelSendModel::OUT_BOUND);
                                        })->count();
                                    if ($pickCount <= 1) {
                                        Db::table('parcel_pick_task')->where('pick_no', $pickNo)->update(['pick_status' => 1]);
                                    }
                                }

                                $msg = '成功';
                            } catch (\Throwable $e) {
                                Db::rollBack();
                                $msg = '失败：' . $e->getMessage();
                                echo 'error:' . $msg . 'line' . $e->getLine() . 'file' . $e->getFile() . PHP_EOL;
                            }

                            break;
                        case "trunk":
                            $nextNodeTrunk = $ParcelChannelNodeSwitchService->nextNodeTrunk(nodeSourceDb: $nextNode['nodeSourceDb'], ParcelDb: $parcelDb);
                            $parcelUpdate  = ['parcel_status' => 110, 'idx_sort' => $nextNodeTrunk['sort']]; //parcel_status=110,干线运输
                            $bl_sn         = (string)$parcelDb['send']['bl_sn'];
                            $this->nextNodeBl(bl_sn: $bl_sn, nextNodeExport: $nextNodeTrunk);
                            Db::beginTransaction();
                            try {
                                Db::table($parcel_table)->where($where)->update($currentNodeUpdate);
                                Db::table('parcel_trunk')->updateOrInsert($where, $nextNodeTrunk);
                                Db::table('parcel')->where('order_sys_sn', '=', $order_sys_sn)->update($parcelUpdate);
                                Db::commit();
                                $msg = '成功';
                            } catch (\Throwable $e) {
                                Db::rollBack();
                                $msg = '失败：' . $e->getMessage();
                            }
                            break;

                        case "import":
                            $nextNodeImport = $ParcelChannelNodeSwitchService->nextNodeImport(nodeSourceDb: $nextNode['nodeSourceDb'], ParcelDb: $parcelDb);
                            $parcelUpdate   = ['parcel_status' => 130, 'idx_sort' => $nextNodeImport['sort']]; //parcel_status=130,清关作业
                            $bl_sn          = (string)$parcelDb['send']['bl_sn'];
                            $this->nextNodeBl(bl_sn: $bl_sn, nextNodeExport: $nextNodeImport);
                            Db::beginTransaction();
                            try {
                                Db::table($parcel_table)->where($where)->update($currentNodeUpdate);
                                Db::table('parcel_import')->updateOrInsert($where, $nextNodeImport);
                                Db::table('parcel')->where('order_sys_sn', '=', $order_sys_sn)->update($parcelUpdate);
                                Db::commit();
                                $msg = '成功';
                            } catch (\Throwable $e) {
                                Db::rollBack();
                                $msg = '失败：' . $e->getMessage();
                            }

                            break;
                        case "transport":
                            $nextNodeTransport = $ParcelChannelNodeSwitchService->nextNodeTransport(nodeSourceDb: $nextNode['nodeSourceDb'], ParcelDb: $parcelDb);
                            $parcelUpdate      = ['parcel_status' => 170, 'idx_sort' => $nextNodeTransport['sort']]; //parcel_status=170,转运作业
                            $bl_sn             = (string)$parcelDb['send']['bl_sn'];
                            $this->nextNodeBl(bl_sn: $bl_sn, nextNodeExport: $nextNodeTransport);
                            Db::beginTransaction();
                            try {
                                Db::table($parcel_table)->where($where)->update($currentNodeUpdate);
                                Db::table('parcel_transport')->updateOrInsert($where, $nextNodeTransport);
                                Db::table('parcel')->where('order_sys_sn', '=', $order_sys_sn)->update($parcelUpdate);
                                Db::commit();
                                $msg = '成功';
                            } catch (\Throwable $e) {
                                Db::rollBack();
                                $msg = '失败：' . $e->getMessage();
                            }
                            break;
                    }
                    $this->logger->info($nextNode['currentKey'] . '->' . $nextNode['nodeKey'] . ' 切换成功：' . $order_sys_sn . '->' . $msg, $redis_data);
                    unset($nextNode, $nextNodeExport);
                } catch (\Throwable $e) {
                    $this->logger->info('渠道节点切换异常：' . $order_sys_sn . '->' . $e->getMessage(), $redis_data);
                }

            } else {
                #没有数据 休闲3秒
                sleep(10);
            }
        }
    }

    /**
     * @DOC   切换到下一个节点的时候，将提单也切换给下一个服务商
     * @Name   nextNodeBl
     * @Author wangfei
     * @date   2023-08-02 2023
     * @param string $bl_sn
     * @param array $nextNodeExport
     */
    public function nextNodeBl(string $bl_sn, array $nextNodeExport)
    {
        //检车提单节点是否存在数据，不存在补充
        $BlNodeCacheCheck = $this->blService->BlNodeCacheCheck(blSn: $bl_sn, node_cfg_id: $nextNodeExport['node_cfg_id'], op_member_uid: $nextNodeExport['op_member_uid']);
        if (!$BlNodeCacheCheck) {
            $BlNodeData['bl_sn']         = $bl_sn;
            $BlNodeData['node_cfg_id']   = $nextNodeExport['node_cfg_id'];
            $BlNodeData['op_member_uid'] = $nextNodeExport['op_member_uid'];
            $BlNodeData['sort']          = $nextNodeExport['sort'];
            $BlNodeData['op_start_time'] = time();
            Db::table('bl_node')->insert($BlNodeData);
            unset($BlNodeData);
        }
    }
}
