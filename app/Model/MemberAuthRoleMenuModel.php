<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace App\Model;

class MemberAuthRoleMenuModel extends HomeModel
{
    protected ?string $table = 'member_auth_role_menu';

    public function menu()
    {
        return $this->hasOne(MemberAuthMenuModel::class, 'menu_id', 'menu_id');
    }
}
