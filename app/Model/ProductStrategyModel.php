<?php

declare(strict_types=1);

namespace App\Model;

class ProductStrategyModel extends HomeModel
{
    protected ?string $table = 'product_strategy';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'strategy_id';

    public function getAddTimeAttribute($value)
    {
        return !empty($value) ? date('Y-m-d H:i:s', $value) : '';
    }

    public function getUpdateTimeAttribute($value)
    {
        return !empty($value) ? date('Y-m-d H:i:s', $value) : '';
    }


    public function item()
    {
        return $this->hasMany(ProductStrategyItemModel::class, 'strategy_id', 'strategy_id');
    }

    public function line()
    {
        return $this->hasOne(LineModel::class, 'line_id', 'line_id');
    }
}
