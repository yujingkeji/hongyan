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

class WarehouseModel extends HomeModel
{
    protected ?string $table = 'warehouse';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'ware_id';


    public function getConfineAttribute($value)
    {
        return !empty($value) ? json_decode($value, true) : $value;
    }

    public function getConfineBackAttribute($value)
    {
        return !empty($value) ? json_decode($value, true) : $value;
    }

    public function type()
    {
        return $this->hasOne(CategoryModel::class, 'cfg_id', 'ware_cfg_id');
    }

    public function country()
    {
        return $this->hasOne(CountryAreaModel::class, 'id', 'country_id');
    }


}
