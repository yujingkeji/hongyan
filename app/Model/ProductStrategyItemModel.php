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

class ProductStrategyItemModel extends HomeModel
{
    protected ?string $table = 'product_strategy_item';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'item_id';

    public function getChannelAttribute($value)
    {
        return !empty($value) ? json_decode($value, true) : '';
    }

    public function getGoodsItemAttribute($value)
    {
        return !empty($value) ? json_decode($value, true) : '';
    }

    public function getGoodsItemsAttribute($value)
    {
        return !empty($value) ? json_decode($value, true) : [];
    }



    public function good()
    {
        return $this->hasOne(GoodsCategoryItemModel::class, 'id', 'goods_item_id');
    }


}
