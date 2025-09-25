<?php

namespace App\Model;

use Hyperf\Database\Model\Relations\HasMany;

class MemberChildAuthRoleModel extends HomeModel
{
    protected ?string $table = 'member_child_auth_role';

    public function child(): HasMany
    {
        return $this->hasMany(MemberChildModel::class, 'uid', 'uid');
    }

    /**
     * @DOC   : 根据角色获取权限
     */
    public function role(int $role_id)
    {
        $v = $this->where('role_id', '=', $role_id)->first();
        if (!empty($v)) {
            return $v->toArray();
        }
        return [];
    }

    public function getWorkMenusAttribute($value)
    {
        return !empty($value) ? json_decode($value, true):[];
    }


}
