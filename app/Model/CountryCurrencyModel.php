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

class CountryCurrencyModel extends HomeModel
{
    protected ?string $table = 'country_currency';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'currency_id';


}
