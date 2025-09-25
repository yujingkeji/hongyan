<?php

declare(strict_types=1);

namespace App\Model;


class PortsModel extends HomeModel
{
    protected ?string $table = 'ports';

    public function country()
    {
        return $this->hasOne(CountryCodeModel::class, 'country_id', 'country_id');
    }

    public function port()
    {
        return $this->hasOne(PortModel::class, 'port_id', 'port_id');
    }

    public function getPortAirStrAttribute($value)
    {
        $data = [1 => "机场", 2 => "港口"];
        return $data[$value];
    }

    public function getAddTimeAttribute($value)
    {
        return !empty($value) ? date('Y-m-d H:i:s', $value) : '';
    }

}
