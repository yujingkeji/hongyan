<?php

namespace App\Model;

class AuthMenuModel extends HomeModel
{
    protected ?string $table = 'auth_menu';

    public function children()
    {
        return $this->hasMany(AuthMenuModel::class, 'menu_pid', 'menu_id');
    }

}
