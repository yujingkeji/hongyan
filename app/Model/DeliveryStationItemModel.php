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

class DeliveryStationItemModel extends HomeModel
{
    protected ?string $table = 'delivery_station_item';


    public function getSendStationSnAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    public function getItemPictureAttribute($value)
    {
        return !empty($value) ? json_decode($value, true) : [];
    }

    public function parcel_item()
    {
        return $this->hasOne(DeliveryStationParcelItemModel::class,'station_item_id','item_id');
    }

}
