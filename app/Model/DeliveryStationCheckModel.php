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

class DeliveryStationCheckModel extends HomeModel
{
    protected ?string $table = 'delivery_station_check';


    public function getSendStationSnAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    public function getPickNoAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    public function getPictureAttribute($value)
    {
        return !empty($value) ? json_decode($value, true) : [];
    }

    public function getVideoAttribute($value)
    {
        return !empty($value) ? json_decode($value, true) : [];
    }

    public function delivery_station()
    {
        return $this->hasOne(DeliveryStationModel::class, 'send_station_sn', 'send_station_sn');
    }

    public function delivery_station_parcel()
    {
        return $this->hasOne(DeliveryStationParcelModel::class, 'send_station_sn', 'send_station_sn');
    }

    /**
     * @DOC   :用于打印拣货任务列表使用 主要解决集运包裹合并发货，拣货时需要从不同的货位拣货
     * @Name  : delivery_station_parcel_many
     * @Author: wangfei
     * @date  : 2025-04 20:59
     * @return \Hyperf\Database\Model\Relations\HasMany
     *
     */
    public function delivery_station_parcel_many()
    {
        return $this->hasMany(DeliveryStationParcelModel::class, 'send_station_sn', 'send_station_sn');
    }

}
