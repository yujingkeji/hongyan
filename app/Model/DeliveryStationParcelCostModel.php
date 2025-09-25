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

class DeliveryStationParcelCostModel extends HomeModel
{
    protected ?string $table = 'delivery_station_parcel_cost';


    public function getStationParcelSnAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }


}
