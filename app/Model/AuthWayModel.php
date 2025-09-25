<?php


namespace App\Model;


class AuthWayModel extends HomeModel
{

    protected ?string $table      = 'auth_way';
    protected string  $primaryKey = 'way_id';


    public function interface()
    {
        return $this->hasOne(AuthWayInterfaceModel::class, 'way_id', 'way_id');
    }

    public function platform()
    {
        return $this->hasOne(ApiPlatformModel::class, 'platform_id', 'platform_id');
    }

    public function member_platform()
    {
        return $this->hasOne(ApiMemberPlatformModel::class, 'platform_id', 'platform_id');
    }

    public function item()
    {
        return $this->hasMany(ApiPlatformItemModel::class, 'platform_id', 'platform_id');
    }

    public function account()
    {
        return $this->hasMany(ApiMemberPlatformAccountModel::class, 'platform_id', 'platform_id');
    }

    public function country()
    {
        return $this->hasOne(CountryCodeModel::class, 'country_id', 'country_id');
    }
}