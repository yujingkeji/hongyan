<?php

declare(strict_types=1);

namespace App\Model;

class GoodsBcModel extends HomeModel
{
    protected ?string $table = 'goods_bc';

    public function getGoodsBaseIdAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }
}
