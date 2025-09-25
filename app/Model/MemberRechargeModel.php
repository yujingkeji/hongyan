<?php

namespace App\Model;

use Hyperf\Database\Model\Relations\HasOne;

class MemberRechargeModel extends HomeModel
{
    protected ?string $table = 'member_recharge';

    protected array $casts = [
        'add_time' => 'datetime:Y-m-d H:i:s'
    ];

    const UPDATED_AT = null;

    public function getFlayAttribute(string $key): int
    {
        if ($key > 0) return 1;
        return 0;
    }

    public function member(): HasOne
    {
        return $this->hasOne("MemberModel", 'uid', 'uid');
    }

    public function source()
    {
        return $this->hasOne(CountryCurrencyModel::class, 'currency_id', 'source_currency_id');
    }

    public function target()
    {
        return $this->hasOne(CountryCurrencyModel::class, 'currency_id', 'target_currency_id');
    }

}
