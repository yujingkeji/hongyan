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


class RecordCategoryGoodsModel extends Model
{
    protected ?string $table = 'record_category_goods';
    /**
     * @var string 主键
     */
    protected string $primaryKey = 'id';
    const UPDATED_AT = null;

    public function getParentStringAttribute($value)
    {
        if ($value) {
            return str_replace(',', '->', $value);
        }
        return $value;
    }

    public function haschildren()
    {
        return $this->hasOne(RecordCategoryGoodsModel::class, 'parent_id', 'id');
    }

    public function subordinate()
    {
        return $this->hasMany(RecordCategoryGoodsModel::class, 'parent_id', 'id');
    }

    public function template()
    {
        return $this->hasOne(TemplateCategoryModel::class, 'category_id', 'id');
    }
}
