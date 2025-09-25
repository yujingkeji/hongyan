<?php

namespace App\Model;

class TemplateCategoryModel extends HomeModel
{
    protected ?string $table = 'template_category';

    const UPDATED_AT = null;

    public function template()
    {
        return $this->hasOne(GoodsTemplateModel::class, 'template_id', 'template_id');
    }
}
