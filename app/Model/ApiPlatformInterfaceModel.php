<?php


namespace App\Model;


class ApiPlatformInterfaceModel extends HomeModel
{

    protected ?string $table = 'api_platform_interface';
    protected string $primaryKey = 'interface_id';

    public function getAddTimeAttribute($value)
    {
        if (!empty($value)) {
            return date("Y-m-d H:i:s", $value);
        }
        return '';
    }

    public function auth()
    {
        return $this->hasMany(ApiPlatformInterfaceAuthModel::class, 'interface_id', 'interface_id');
    }

    public function platform()
    {
        return $this->hasOne(ApiPlatformModel::class, 'platform_id', 'platform_id');
    }
}
