<?php

namespace App\Model;

class FreightCompanyItemModel extends HomeModel
{
    protected ?string $table = 'freight_company_item';

    public function config()
    {
        return $this->hasOne(ConfigModel::class, 'cfg_id', 'company_cfg_id');
    }

}
