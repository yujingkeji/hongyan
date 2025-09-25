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

class DeliveryStationModel extends HomeModel
{
    protected ?string $table = 'delivery_station';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'send_station_sn';

    /**
     * @var  int 预报包裹类型 26101:直邮
     */
    const TYPE_DIRECT = 26101;  // 直邮
    /**
     * @var int 预报
     */
    const TYPE_COLLECT = 26102;  // 集运 预报

    /**
     * @DOC 预报包裹状态
     */
    /**
     * @var int 待入库
     */
    const STATUS_WAIT_IN = 26001;// 待入库 26001

    const STATUS_RECEIVE = 26005; // 已收货
    const STATUS_DISCARD = 26010;// 已废弃
    const STATUS_IN = 26015;// 已入库
    const STATUS_OUT = 26020;  // 待出库
    const STATUS_SEND = 26025; // 已发出


    public function getSendStationSnAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    public function getSendLogisticsSnAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    public function getOrderSysSnAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    public function getContentDataAttribute($value)
    {
        return !empty($value) ? json_decode($value, true) : '';
    }

    public function item()
    {
        return $this->hasMany(DeliveryStationItemModel::class, 'send_station_sn', 'send_station_sn');
    }

    public function check()
    {
        return $this->hasOne(DeliveryStationCheckModel::class, 'send_station_sn', 'send_station_sn');
    }


    public function order()
    {
        return $this->hasOne(OrderModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function send_member()
    {
        return $this->hasOne(MemberModel::class, 'uid', 'send_member_uid');
    }

    public function send_join_member()
    {
        return $this->hasOne(MemberModel::class, 'uid', 'send_join_uid');
    }

    public function send_agent_member()
    {
        return $this->hasOne(MemberModel::class, 'uid', 'send_agent_uid');
    }

    public function exception()
    {
        return $this->hasMany(ParcelExceptionItemModel::class, 'order_sys_sn', 'send_station_sn');
    }

    public function cost_member_item()
    {
        return $this->hasMany(OrderCostMemberItemModel::class, 'order_sys_sn', 'send_station_sn');
    }

    public function parcelException()
    {
        return $this->hasMany(ParcelExceptionItemModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function delivery_station_parcel()
    {
        return $this->hasOne(DeliveryStationParcelModel::class, 'send_station_sn', 'send_station_sn');
    }

    public function delivery_station_parcel_many()
    {
        return $this->hasMany(DeliveryStationParcelModel::class, 'send_station_sn', 'send_station_sn');
    }

    public function item_exception()
    {
        return $this->hasMany(DeliveryStationParcelItemExceptionModel::class, 'send_station_sn', 'send_station_sn');
    }

    public function station_exception()
    {
        return $this->hasMany(ParcelExceptionItemModel::class, 'order_sys_sn', 'send_station_sn');
    }

    public function warehouse()
    {
        return $this->hasOne(WarehouseModel::class, 'ware_id', 'ware_id');
    }

    public function cost_item()
    {
        return $this->hasMany(DeliveryStationParcelCostModel::class, 'station_sn', 'send_station_sn');
    }

    /**
     * @DOC 发件人
     */
    public function sender()
    {
        return $this->hasOne(OrderSenderModel::class, 'order_sys_sn', 'order_sys_sn');
    }


    /**
     * @DOC 收件人
     */
    public function receiver()
    {
        return $this->hasOne(OrderReceiverModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function parcel()
    {
        return $this->hasOne(ParcelModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function log()
    {
        return $this->hasMany(OrderParcelLogModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function swap()
    {
        return $this->hasOne(ParcelSwapModel::class, 'order_sys_sn', 'order_sys_sn');
    }

}
