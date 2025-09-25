<?php

declare(strict_types=1);

namespace App\Model;


class BrandModel extends HomeModel
{
    protected ?string $table = 'brand';

    public function source()
    {
        return $this->hasOne(CountryCodeModel::class, 'country_id', 'source_country_id');
    }

}
