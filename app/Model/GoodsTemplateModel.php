<?php

namespace App\Model;


class GoodsTemplateModel extends HomeModel
{
    protected ?string $table = 'goods_template';

    public function item()
    {
        return $this->hasMany(GoodsTemplateItemModel::class, 'template_id', 'template_id');
    }

}
