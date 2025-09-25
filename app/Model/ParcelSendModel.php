<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Model;

class ParcelSendModel extends HomeModel
{
    protected ?string $table = 'parcel_send';


    const STATUS_WAIT = 67; // 待打包状态
    const STATUS_IN = 65; // 已入库
    const OUT_BOUND = 70; // 可出库
    const STATUS_PACKAGED = 68; // 已打包状态

    public function getSendStationSnAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }


    /**
     * @DOC  关联操作人员ID
     * @Name   agentMember
     * @Author wangfei
     * @date   2023-06-29 2023
     * @return \Hyperf\Database\Model\Relations\HasOne
     */
    public function agentMember()
    {
        return $this->hasOne(AgentMemberModel::class, 'member_uid', 'op_member_uid');

    }

    public function parcel()
    {
        return $this->hasOne(ParcelModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function ware()
    {
        return $this->hasOne(WarehouseModel::class, 'ware_id', 'ware_id');
    }

    //多个
    public function orders()
    {
        return $this->hasMany(OrderModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    //单个
    public function order()
    {
        return $this->hasOne(OrderModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function bl()
    {
        return $this->hasOne(BlModel::class, 'bl_sn', 'bl_sn');
    }

    /**
     * @DOC  提单节点数据
     * @Name   bl_node
     * @Author wangfei
     * @date   2023-08-09 2023
     * @return \Hyperf\Database\Model\Relations\HasOne
     */
    public function bl_node()
    {
        return $this->hasMany(BlNodeModel::class, 'bl_sn', 'bl_sn');
    }

    /**
     * @DOC   : 收件人
     * @Name  : receiver
     * @Author: wangfei
     * @date  : 2022-11-23 2022
     * @return HasOne
     */
    public function receiver()
    {
        return $this->hasOne(OrderReceiverModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function item()
    {
        return $this->hasMany(OrderItemModel::class, 'order_sys_sn', 'order_sys_sn');
    }


    public function exception()
    {
        return $this->hasMany(ParcelExceptionItemModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function order_exception()
    {
        return $this->hasMany(OrderExceptionItemModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function deliveryStation()
    {
        return $this->hasOne(DeliveryStationModel::class, 'send_station_sn', 'send_station_sn');
    }

    public function cost_member_item()
    {
        return $this->hasMany(OrderCostMemberItemModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function channel()
    {
        return $this->hasOne(ChannelModel::class, 'channel_id', 'channel_id');
    }

    public function import()
    {
        return $this->hasOne(ChannelImportModel::class, 'channel_id', 'channel_id');
    }

    public function delivery_station_parcel()
    {
        return $this->hasOne(DeliveryStationParcelModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function swap()
    {
        return $this->hasOne(ParcelSwapModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function pack()
    {
        return $this->hasOne(DeliveryOrderPackModel::class, 'order_sys_sn', 'order_sys_sn');
    }

}
