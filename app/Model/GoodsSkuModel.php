<?php

declare(strict_types=1);

namespace App\Model;

class GoodsSkuModel extends HomeModel
{
    protected ?string $table = 'goods_sku';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'sku_id';

    public function getGoodsBaseIdAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }


    public function goods()
    {
        return $this->hasOne(GoodsBaseModel::class, 'goods_base_id', 'goods_base_id');
    }

    /**
     * @DOC 备案数据
     * @Name   cc
     * @Author wangfei
     * @date   2023-09-04 2023
     * @return \Hyperf\Database\Model\Relations\HasOne
     */
    public function cc()
    {
        return $this->hasOne(GoodsCcModel::class, 'goods_base_id', 'goods_base_id');
    }

    public function goods_base()
    {
        return $this->belongsTo(GoodsBaseModel::class, 'goods_base_id', 'goods_base_id');
    }


}
