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

class DeliveryStationParcelItemModel extends HomeModel
{
    protected ?string $table = 'delivery_station_parcel_item';

    public function getParcelItemIdAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    public function getSendStationSnAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    public function getItemPictureAttribute($value)
    {
        return !empty($value) ? json_decode($value, true) : [];
    }

    public function station_item()
    {
        return $this->hasMany(DeliveryStationItemModel::class, 'item_id', 'station_item_id');
    }

    public function item_exception()
    {
        return $this->hasMany(DeliveryStationParcelItemExceptionModel::class, 'item_id', 'parcel_item_id');
    }

}
