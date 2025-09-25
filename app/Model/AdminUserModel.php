<?php

namespace App\Model;

class AdminUserModel extends HomeModel
{
    protected ?string $table = 'admin_user';

    public function role()
    {
        return $this->hasMany(AuthRoleModel::class, 'role_id', 'role_id');
    }

}
