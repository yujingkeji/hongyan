<?php


namespace App\Model;


class FreightCompanyModel extends HomeModel
{
    protected ?string $table = 'freight_company';

    public function getAddTimeAttribute($value)
    {
        if (!empty($value)) {
            return date("Y-m-d H:i:s", $value);
        }
        return '';
    }


    public function item()
    {
        return $this->hasMany(FreightCompanyItemModel::class, 'company_id', 'company_id');
    }

    public function country()
    {
        return $this->hasOne(CountryCodeModel::class, 'country_id', 'country_id');
    }

    public function config()
    {
        return $this->hasOne(ConfigModel::class, 'cfg_id', 'company_cfg_id');
    }


}
