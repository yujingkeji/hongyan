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

class GoodsCategoryItemModel extends HomeModel
{
    protected ?string $table = 'goods_category_item';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'id';

    public function cate1()
    {
        return $this->hasOne(GoodsCategoryModel::class, 'cate_id', 'cate1');
    }

    public function cate2()
    {
        return $this->hasOne(GoodsCategoryModel::class, 'cate_id', 'cate2');
    }

    public function template()
    {
        return $this->hasOne(GoodsTemplateModel::class, 'template_id', 'template_id');
    }

}
