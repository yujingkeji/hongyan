<?php

namespace App\Model;

class AuthRoleMenuModel extends HomeModel
{
    protected ?string $table = 'auth_role_menu';

    public function menu()
    {
        return $this->hasOne(AuthMenuModel::class, 'menu_id', 'menu_id');
    }


}
