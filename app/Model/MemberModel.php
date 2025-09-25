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


use App\Common\Lib\Crypt;

class MemberModel extends HomeModel
{
    protected ?string $table = 'member';
    /**
     * @var string 主键
     */
    protected string $primaryKey = 'uid';

    const UPDATED_AT = null;


    public function childRole()
    {
        return $this->hasOne(MemberChildAuthRoleModel::class, 'role_id', 'role_id');
    }

    public function role()
    {
        return $this->hasOne(MemberAuthRoleModel::class, 'role_id', 'role_id');
    }

    public function member()
    {
        return $this->hasOne(AgentMemberModel::class, 'member_uid', 'uid');
    }

    public function info()
    {
        return $this->hasOne(MemberInfoModel::class, 'member_uid', 'uid');
    }
}
