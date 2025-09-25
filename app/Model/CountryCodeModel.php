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

class CountryCodeModel extends HomeModel
{
    protected ?string $table = 'country_code';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'country_id';

    protected  array $fillable = [
        'country_id',
        'country_name',
        // 其他允许批量赋值的属性
    ];

    public function area()
    {
        return $this->hasOne(CountryAreaModel::class, 'country_id', 'country_id');
    }


}
