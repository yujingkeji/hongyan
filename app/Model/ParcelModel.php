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

class ParcelModel extends HomeModel
{
    protected ?string $table = 'parcel';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'order_sys_sn';

    public function getPickNoAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    public function line()
    {
        return $this->hasOne(LineModel::class, 'line_id', 'line_id');
    }

    public function ware()
    {
        return $this->hasOne(WarehouseModel::class, 'ware_id', 'ware_id');
    }

    public function product()
    {
        return $this->hasOne(ProductModel::class, 'pro_id', 'product_id');
    }

    public function channel()
    {
        return $this->hasOne(ChannelModel::class, 'channel_id', 'channel_id');
    }

    public function import()
    {
        return $this->hasOne(ChannelImportModel::class, 'channel_id', 'channel_id');
    }

    public function send()
    {
        return $this->hasOne(ParcelSendModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function log()
    {
        return $this->hasMany(OrderParcelLogModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function member()
    {
        return $this->hasOne(MemberModel::class, 'uid', 'member_uid');
    }

    public function platform()
    {
        return $this->hasOne(AgentPlatformModel::class, 'agent_platform_uid', 'parent_agent_uid');
    }

    /**
     * @DOC   : 发件人
     * @Name  : sender
     * @Author: wangfei
     * @date  : 2022-11-23 2022
     * @return HasOne
     */
    public function sender()
    {
        return $this->hasOne(OrderSenderModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function receiver()
    {
        return $this->hasOne(OrderReceiverModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    /**
     * @DOC 换单
     * @Name   swap
     * @Author wangfei
     * @date   2023/11/16 2023
     * @return \Hyperf\Database\Model\Relations\HasOne
     */
    public function swap()
    {
        return $this->hasOne(ParcelSwapModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    /**
     * @DOC  包裹异常
     * @Name   exception
     * @Author wangfei
     * @date   2023-07-20 2023
     * @return \Hyperf\Database\Model\Relations\HasOne
     */
    public function exception()
    {
        return $this->hasMany(ParcelExceptionItemModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function order_exception_item()
    {
        return $this->hasOne(OrderExceptionItemModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function item()
    {
        return $this->hasMany(OrderItemModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function order()
    {
        return $this->hasOne(OrderModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function cost_member()
    {
        return $this->hasOne(OrderCostMemberModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function cost_join()
    {
        return $this->hasOne(OrderCostJoinModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function cost_member_item()
    {
        return $this->hasMany(OrderCostMemberItemModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function parcel_send()
    {
        return $this->hasMany(ParcelSendModel::class, 'order_sys_sn', 'order_sys_sn');

    }

    /**
     * @DOC  加盟商付款信息
     * @Name   cost_join_item
     * @Author wangfei
     * @date   2023-08-21 2023
     * @return \Hyperf\Database\Model\Relations\HasMany
     */
    public function cost_join_item()
    {
        return $this->hasMany(OrderCostJoinItemModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function getBatchSnAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    public function predictionParcel()
    {
        return $this->hasMany(DeliveryStationParcelModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function delivery_station_parcel()
    {
        return $this->hasMany(DeliveryStationParcelModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    /**
     * @DOC   :主要与  DeliveryStationCheckModel 的 delivery_station_parcel_many 对应，解决 PredictionParcelService 类  提取 withDeliveryStationParcelLocation 公共提取的问题
     * @Name  : delivery_station_parcel_many
     * @Author: wangfei
     * @date  : 2025-04 20:58
     * @return \Hyperf\Database\Model\Relations\HasMany
     *
     */
    public function delivery_station_parcel_many()
    {
        return $this->hasMany(DeliveryStationParcelModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function export()
    {
        return $this->hasOne(ChannelExportModel::class, 'channel_id', 'channel_id');
    }

    public function parcel_pick()
    {
        return $this->hasOne(ParcelPickTaskModel::class, 'pick_no', 'pick_no');
    }

}
