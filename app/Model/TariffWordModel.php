<?php

namespace App\Model;

class TariffWordModel extends HomeModel
{
    protected ?string $table = 'tariff_word';

    public function tax()
    {
        return $this->hasOne(TariffModel::class, 'id', 'tariff_id');
    }

}
