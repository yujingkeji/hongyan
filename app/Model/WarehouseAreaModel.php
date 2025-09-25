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


class WarehouseAreaModel extends HomeModel
{


    protected ?string $table = 'warehouse_area';

    public function warehouse()
    {
        return $this->hasOne(WarehouseModel::class,'ware_id','ware_id');
    }


}
