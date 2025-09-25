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

class ParcelExportModel extends HomeModel
{
    protected ?string $table = 'parcel_export';

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

    public function deliveryStation()
    {
        return $this->hasMany(DeliveryStationModel::class, 'send_station_sn', 'send_station_sn');
    }

    public function cost_member_item()
    {
        return $this->hasMany(OrderCostMemberItemModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function channel()
    {
        return $this->hasOne(ChannelModel::class, 'channel_id', 'channel_id');
    }

}
