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

class MemberAuthRoleModel extends HomeModel
{
    /*
     *  var string
     */
    protected ?string $table = 'member_auth_role';

    protected string $primaryKey = 'role_id';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected array $fillable = [];

    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = [];

    public function menu()
    {
        return $this->hasMany(MemberAuthRoleMenuModel::class, 'role_id', 'role_id');
    }

    public function member()
    {
        return $this->hasMany(MemberModel::class, 'role_id', 'role_id');
    }

    public function getWorkMenusAttribute($value)
    {
        return !empty($value) ? json_decode($value, true):[];
    }
}
