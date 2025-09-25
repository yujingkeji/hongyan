<?php


namespace App\Model;


class AuthWayInterfaceModel extends HomeModel
{

    protected ?string $table      = 'auth_way_interface';
    protected string  $primaryKey = 'way_interface_id';


    public function interface()
    {
        return $this->hasOne(ApiPlatformInterfaceModel::class, 'interface_id', 'interface_id');
    }

}