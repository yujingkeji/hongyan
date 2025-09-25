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


class MemberAddressModel extends HomeModel
{
    protected ?string $table = 'member_address';
    /**
     * @var string 主键
     */
    protected string $primaryKey = 'address_id';

    const UPDATED_AT = null;

    public function country()
    {
        return $this->hasOne(CountryAreaModel::class, 'id', 'country_id');
    }

    public function province()
    {
        return $this->hasOne(CountryAreaModel::class, 'id', 'province_id');
    }

    public function city()
    {
        return $this->hasOne(CountryAreaModel::class, 'id', 'city_id');
    }

    public function district()
    {
        return $this->hasOne(CountryAreaModel::class, 'id', 'district_id');
    }

    public function street()
    {
        return $this->hasOne(CountryAreaModel::class, 'id', 'street_id');
    }

}
