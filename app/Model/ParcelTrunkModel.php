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

class ParcelTrunkModel extends HomeModel
{
    protected ?string $table = 'parcel_trunk';

    public function exception()
    {
        return $this->hasMany(ParcelExceptionItemModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function bl()
    {
        return $this->hasOne(BlModel::class, 'bl_sn', 'bl_sn');
    }
}
