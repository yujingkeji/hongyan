<?php

/**
 *商品信息备案任务，并且处理相同的订单
 */
declare(strict_types=1);

namespace App\Process\JobProcess;

use App\Common\Lib\Arr;
use App\Exception\HomeException;
use App\Model\OrderExceptionItemModel;
use App\Model\OrderItemModel;
use App\Model\OrderModel;
use App\Request\LibValidation;
use App\Service\TaskCenterPushService;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\Logger;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Redis\Redis;

class OrderItemToRecordPassJob
{
    protected Redis $redis;

    #[Inject]
    protected LoggerFactory $loggerFactory;

    protected Logger $logger;

    public function __construct()
    {
        $this->redis  = \Hyperf\Support\make(Redis::class);
        $this->logger = $this->loggerFactory->get('default');
    }

    public function handle(array $job_data)
    {
        return $this->orderItemToOtherRecordPass($job_data['item_id']);
    }


    /**
     * @DOC   : 解决当前item_id 关联的其他订单
     * @Name  : orderItemToOtherRecordPass
     * @Author: wangfei
     * @date  : 2025-01 19:47
     * @param int $item_id
     * @return false|int
     *
     */
    public function orderItemToOtherRecordPass(int $item_id)
    {
        if (empty($item_id)) {
            throw new HomeException('item_id cannot be empty');
        }
        $itemDb = $this->queryItemDb($item_id);
        if (empty($itemDb)) {
            return false;
        }
        // 查询关联订单
        $order_sys_sn_arr = $this->getAssociatedOrders($itemDb);
        if (!empty($order_sys_sn_arr)) {
            Db::beginTransaction();
            try {
                // 更新订单状态
                $this->updateOrderRecordStatus($order_sys_sn_arr);
                //更改相关联的订单明细
                $this->updateOrderItemRecordStatus($order_sys_sn_arr, $itemDb);
                // 删除异常项
                $this->deleteExceptionItems($order_sys_sn_arr);
                // 删除空异常记录
                $this->deleteEmptyExceptions($order_sys_sn_arr);
                Db::commit();
            } catch (\Exception $e) {
                Db::rollBack();
                $this->logger->error('Failed to process order items: ' . $e->getMessage());
                throw new HomeException('处理失败');
            }
        }
        return true;

    }

    //修改订单状态

    /**
     * @DOC   :
     *
     * UPDATE `order`
     * SET order_record = 22006
     * WHERE order_sys_sn = '730526648820793344'
     * AND EXISTS (
     * SELECT 1
     * FROM order_item
     * WHERE order_item.order_sys_sn = `order`.order_sys_sn
     * AND order_item.sku_id > 0
     * )
     * AND NOT EXISTS (
     * SELECT 1
     * FROM order_item
     * JOIN goods_sku ON order_item.sku_id = goods_sku.sku_id
     * JOIN goods_base ON goods_sku.goods_base_id = goods_base.goods_base_id
     * WHERE order_item.order_sys_sn = `order`.order_sys_sn
     * AND goods_base.record_status <> 3
     * );
     * @Name  : orderItemToRecordPass
     * @Author: wangfei
     * @date  : 2025-01 15:27
     * @param string $orderSysSn
     * @return void
     *
     */
    public function orderItemToRecordPass(string $orderSysSn)
    {
        if (empty($orderSysSn)) {
            throw new HomeException('order_sys_sn cannot be empty');
        }

        Db::beginTransaction();
        try {
            OrderModel::where('order_sys_sn', $orderSysSn)
                ->whereHas('item', function ($query) {
                    $query->where('sku_id', '>', 0);
                })
                ->whereDoesntHave('item.goods_sku.goods_base', function ($query) {
                    $query->where('record_status', '<>', 3);
                })
                ->update(['order_record' => OrderModel::Order_Status_Record_Pass]);

            Db::table('order_exception_item')->where('order_sys_sn', $orderSysSn)
                ->where('code', '=', OrderExceptionItemModel::RECORD_YES_INIT)
                ->delete();
            Db::table('order_exception')
                ->whereNotExists(function ($query) {
                    $query->select('order_sys_sn')
                        ->from('order_exception_item')
                        ->whereColumn('order_exception_item.order_sys_sn', 'order_exception.order_sys_sn');
                })
                ->where('order_sys_sn', $orderSysSn)
                ->delete();
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();
            throw new HomeException('通过备案失败' . $e->getMessage());
            $this->logger->warning('orderItemToRecordPass:' . $e->getMessage());
            return false;
        }
        return true;
    }

    // 根据远程关联备案情况来修改订单状态
    public function orderItemToRemoteRecordPass(string $orderSysSn)
    {
        if (empty($orderSysSn)) {
            throw new HomeException('order_sys_sn cannot be empty');
        }

        Db::beginTransaction();
        try {
            OrderModel::where('order_sys_sn', $orderSysSn)
                ->whereHas('item', function ($query) {
                    $query->whereNotNull('item_record_sn');
                })
                ->whereDoesntHave('item.goods_sku.goods_base', function ($query) {
                    $query->where('record_status', '<>', 3);
                })
                ->update(['order_record' => OrderModel::Order_Status_Record_Pass]);

            Db::table('order_exception_item')->where('order_sys_sn', $orderSysSn)
                ->where('code', '=', OrderExceptionItemModel::RECORD_YES_INIT)
                ->delete();
            Db::table('order_exception')
                ->whereNotExists(function ($query) {
                    $query->select('order_sys_sn')
                        ->from('order_exception_item')
                        ->whereColumn('order_exception_item.order_sys_sn', 'order_exception.order_sys_sn');
                })
                ->where('order_sys_sn', $orderSysSn)
                ->delete();
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();
            throw new HomeException('通过备案失败' . $e->getMessage());
            $this->logger->warning('orderItemToRecordPass:' . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * 获取关联订单
     *
     * @param array $itemDb
     * @return array
     */
    private function getAssociatedOrders(array $itemDb): array
    {
        return OrderModel::where('member_uid', $itemDb['member_uid'])
            ->whereIn('order_record', [
                OrderModel::Order_Status_Record_Personal,
                OrderModel::Order_Status_Record_Personal_Fail
            ])
            ->whereHas('item', function ($query) use ($itemDb) {
                // 分组条件以确保逻辑正确
                $query->where(function ($q) {
                    $q->where('sku_id', '>', 0)
                        ->orWhereNotNull('item_record_sn');
                });
                if (!empty($itemDb['sku_code'])) {
                    $query->where('sku_code', $itemDb['sku_code']);
                } else {
                    $query->where('md5_key', $itemDb['md5_key']);
                }
            })
            ->whereDoesntHave('item.goods_sku.goods_base', function ($query) {
                $query->where('record_status', '<>', 3);
            })
            ->pluck('order_sys_sn')
            ->toArray();
    }


    /**
     * 更新订单备案状态
     *
     * @param array $order_sys_sn_arr
     * @return void
     */
    private function updateOrderRecordStatus(array $order_sys_sn_arr)
    {
        Db::table('order')
            ->whereIn('order_sys_sn', $order_sys_sn_arr)
            ->update(['order_record' => OrderModel::Order_Status_Record_Pass]);
    }

    /** 回家优化
     * @DOC   : 修改item_id 相关联的同源订单item
     * @Name  : updateOrderItemRecordStatus
     * @Author: wangfei
     * @date  : 2025-01 16:39
     * @param array $order_sys_sn_arr
     * @param array $itemDb
     * @param array $update
     * @return void
     *
     */

    private function updateOrderItemRecordStatus(array $order_sys_sn_arr, array $itemDb)
    {
        $update['item_record_sn'] = $itemDb['item_record_sn'];
        $update['sku_id']         = $itemDb['sku_id'];

        Db::table('order_item')
            ->whereIn('order_sys_sn', $order_sys_sn_arr)
            ->where('member_uid', $itemDb['member_uid'])
            ->where(function ($query) use ($itemDb) {
                if (!empty($itemDb['sku_code'])) {
                    $query->where('sku_code', $itemDb['sku_code']);
                } else {
                    $query->where('md5_key', $itemDb['md5_key']);
                }
            })
            ->update($update);
    }

    /**
     * 删除异常项
     *
     * @param array $order_sys_sn_arr
     * @return void
     */
    private function deleteExceptionItems(array $order_sys_sn_arr)
    {
        Db::table('order_exception_item')
            ->whereIn('order_sys_sn', $order_sys_sn_arr)
            ->where('code', '=', OrderExceptionItemModel::RECORD_YES_INIT)
            ->delete();
    }

    /**
     * 删除空异常记录
     *
     * @param array $order_sys_sn_arr
     * @return void
     */
    private function deleteEmptyExceptions(array $order_sys_sn_arr)
    {
        Db::table('order_exception')
            ->whereNotExists(function ($query) {
                $query->select('order_sys_sn')
                    ->from('order_exception_item')
                    ->whereColumn('order_exception_item.order_sys_sn', 'order_exception.order_sys_sn');
            })
            ->whereIn('order_sys_sn', $order_sys_sn_arr)
            ->delete();
    }

    /**
     * @DOC   :sqlToPassRecord 与  orderItemToRecordPass 雷同
     * @Name  : sqlToPassRecord
     * @Author: wangfei
     * @date  : 2025-01 16:55
     * @param string $orderSysSn
     * @return void
     *
     */
    public function sqlToPassRecord(string $orderSysSn)
    {
        try {
            // 使用 PDO 预处理语句防止 SQL 注入
            $sql = 'UPDATE `order`
                SET order_record = :orderRecordStatus
                WHERE order_sys_sn = :orderSysSn
                AND EXISTS (
                    SELECT 1
                    FROM order_item
                    WHERE order_item.order_sys_sn = `order`.order_sys_sn
                    AND order_item.sku_id > 0
                )
                AND NOT EXISTS (
                    SELECT 1
                    FROM order_item
                    JOIN goods_sku ON order_item.sku_id = goods_sku.sku_id
                    JOIN goods_base ON goods_sku.goods_base_id = goods_base.goods_base_id
                    WHERE order_item.order_sys_sn = `order`.order_sys_sn
                    AND goods_base.record_status <> 3
                );';
            Db::update($sql, [
                ':orderRecordStatus' => OrderModel::Order_Status_Record_Pass,
                ':orderSysSn'        => $orderSysSn,
            ]);
        } catch (\Throwable $e) {

        }
    }

    /**
     * @DOC   : 根据item_id查询order_item 的数据
     * @DOC   :
     * @Name  : queryItemDb
     * @Author: wangfei
     * @date  : 2025-01 11:13
     * @param int $item_id
     *
     */
    public function queryItemDb(int $item_id)
    {
        try {
            $item_db = OrderItemModel::where('item_id', '=', $item_id)->first();
            if (!empty($item_db)) {
                return $item_db->toArray();
            }
        } catch (\Throwable) {

        }
        return [];
    }

    /**
     * @DOC   : 检验并添加任务
     * @Name  : pushTask
     * @Author: wangfei
     * @date  : 2025-01 10:09
     * @param array $redis_data
     * @return bool|\Redis|string
     *
     */
    public function pushTask(array $redis_data)
    {
        $redis_data         = make(LibValidation::class)->validate($redis_data,
            [
                'item_id' => ['required', 'integer']
            ],
            [
                'item_id.required' => 'item_id不能为空',
                'item_id.integer'  => 'item_id必须为整数',
            ]
        );
        $result['job_name'] = 'OrderItemToRecordPassJob';
        $result['job_data'] = $redis_data;
        return $this->redis->lPush(TaskCenterPushService::TASK_CENTER_PUSH_KEY, json_encode($result));
    }
}
