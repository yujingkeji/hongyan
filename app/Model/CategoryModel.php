<?php

declare(strict_types=1);

namespace App\Model;


class CategoryModel extends HomeModel
{
    protected ?string $table = 'category';

    public function children()
    {
        return $this->hasMany(CategoryModel::class, 'pid', 'cfg_id')
            ->with('children')
            ->select('cfg_id,title,title_en,desc,status,code,pid');

    }

}
