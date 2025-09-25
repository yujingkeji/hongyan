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

/**
 * 用户目录表
 * Class MemberAuthMenuModel.
 */
class MemberAuthMenuModel extends HomeModel
{
    protected ?string $table = 'member_auth_menu';

    public function children()
    {
        return $this->hasMany(MemberAuthMenuModel::class, 'menu_pid', 'menu_id');
    }

    public function member_auth_role_menu()
    {
        return $this->hasOne(MemberAuthRoleMenuModel::class, 'menu_id', 'menu_id');
    }
}
