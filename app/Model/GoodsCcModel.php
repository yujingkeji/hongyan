<?php

declare(strict_types=1);

namespace App\Model;

class GoodsCcModel extends HomeModel
{
    protected ?string $table = 'goods_cc';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'goods_base_id';


    public function getGoodsBaseIdAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }
}
