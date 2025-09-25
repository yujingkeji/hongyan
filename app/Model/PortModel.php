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

class PortModel extends HomeModel
{
    protected ?string $table = 'Port';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'port_id';

    public function children()
    {
        return $this->hasMany(PortModel::class, 'parent_id', 'port_id');
    }

    public function country()
    {
        return $this->hasOne(CountryCodeModel::class, 'country_id', 'country_id');
    }

}
