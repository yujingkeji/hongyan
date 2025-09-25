<?php

namespace App\Model;

class AuthRoleModel extends HomeModel
{
    protected ?string $table = 'auth_role';

    public function menu()
    {
        return $this->hasMany(AuthRoleMenuModel::class, 'role_id', 'role_id');
    }

    public function user()
    {
        return $this->hasMany(AdminUserModel::class, 'role_id', 'role_id');
    }


}
