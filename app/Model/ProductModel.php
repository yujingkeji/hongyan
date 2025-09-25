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

class ProductModel extends HomeModel
{
    protected ?string $table = 'product';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'pro_id';

    /**
     * @DOC   价格模板
     * @Name   price_template
     * @Author wangfei
     * @date   2023-07-11 2023
     * @return \Hyperf\Database\Model\Relations\HasOne
     */
    public function price_template()
    {
        return $this->hasOne(PriceTemplateModel::class, 'template_id', 'price_template_id');
    }

    public function strategy()
    {
        return $this->hasOne(ProductStrategyModel::class, 'strategy_id', 'strategy_id');
    }

    public function line()
    {
        return $this->hasOne(LineModel::class, 'line_id', 'line_id');
    }

}
