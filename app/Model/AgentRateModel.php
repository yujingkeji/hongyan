<?php

declare(strict_types=1);

namespace App\Model;

class AgentRateModel extends HomeModel
{
    protected ?string $table = 'agent_rate';

    public function source()
    {
        return $this->hasOne(CountryCurrencyModel::class, 'currency_id', 'source_currency_id');
    }

    public function target()
    {
        return $this->hasOne(CountryCurrencyModel::class, 'currency_id', 'target_currency_id');
    }

}
