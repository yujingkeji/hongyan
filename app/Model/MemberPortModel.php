<?php

declare(strict_types=1);

namespace App\Model;

class MemberPortModel extends HomeModel
{
    protected ?string $table = 'member_port';
    /**
     * @var string 主键
     */
    protected string $primaryKey = 'member_port_id';

    public function port()
    {
        return $this->hasOne(PortModel::class, 'port_id', 'port_id');
    }

    public function country()
    {
        return $this->hasOne(CountryCodeModel::class, 'country_id', 'country_id');
    }

}
