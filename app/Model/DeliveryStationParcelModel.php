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

class DeliveryStationParcelModel extends HomeModel
{
    protected ?string $table = 'delivery_station_parcel';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'send_station_sn';


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

    public function getVolumeAttribute($value)
    {
        return $this->length * $this->width * $this->height;
    }

    public function item()
    {
        return $this->hasMany(DeliveryStationParcelItemModel::class, 'send_station_sn', 'send_station_sn');
    }

    public function check()
    {
        return $this->hasOne(DeliveryStationCheckModel::class, 'send_station_sn', 'send_station_sn');
    }

    public function send()
    {
        return $this->hasOne(ParcelSendModel::class, 'order_sys_sn', 'send_station_sn');
    }

    public function order()
    {
        return $this->hasOne(OrderModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    /**
     * @DOC   : 关联货位
     * @Name  : storage_location
     * @Author: wangfei
     * @date  : 2025-02 16:21
     * @return \Hyperf\Database\Model\Relations\HasOne
     *
     */
    public function storage_location() //parcel_location_id
    {
        return $this->hasOne(WarehouseParcelLocationModel::class, 'parcel_location_id', 'parcel_location_id');
    }


    public function parcelException()
    {
        return $this->hasMany(ParcelExceptionItemModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function item_exception()
    {
        return $this->hasMany(DeliveryStationParcelItemExceptionModel::class, 'send_station_sn', 'send_station_sn');
    }

    public function station_exception()
    {
        return $this->hasMany(ParcelExceptionItemModel::class, 'order_sys_sn', 'send_station_sn');
    }

    public function parcel()
    {
        return $this->hasMany(ParcelModel::class, 'order_sys_sn', 'order_sys_sn');
    }
}
