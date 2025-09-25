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


class WarehouseStorageLocationModel extends HomeModel
{
    protected ?string $table = 'warehouse_storage_location';

    public function ware()
    {
        return $this->hasOne(WarehouseModel::class, 'ware_id', 'ware_id');
    }

    public function area()
    {
        return $this->hasOne(WarehouseAreaModel::class, 'area_id', 'area_id');
    }

    public function type()
    {
        return $this->hasOne(WarehouseLocationTypeModel::class, 'type_id', 'type_id');
    }

}
