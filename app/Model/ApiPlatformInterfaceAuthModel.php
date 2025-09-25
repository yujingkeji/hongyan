<?php


namespace App\Model;


class ApiPlatformInterfaceAuthModel extends HomeModel
{

    protected ?string $table      = 'api_platform_interface_auth';
    protected string  $primaryKey = 'auth_id';


    public function element()
    {
        return $this->hasOne(CategoryModel::class, 'cfg_id', 'auth_cfg_id');
    }
}