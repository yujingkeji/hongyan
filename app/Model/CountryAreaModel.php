<?php

declare(strict_types=1);

namespace App\Model;

class CountryAreaModel extends HomeModel
{
    protected ?string $table = 'country_area';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'id';

    public function children()
    {
        return $this->hasMany(CountryAreaModel::class, 'parent_id', 'id');
    }

    public function country()
    {
        return $this->hasOne(CountryCodeModel::class, 'country_id', 'country_id');
    }

}
