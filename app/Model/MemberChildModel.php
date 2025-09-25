<?php


namespace App\Model;


use Hyperf\Database\Model\Relations\HasOne;

class MemberChildModel extends HomeModel
{
    protected ?string $table = 'member_child';
    /**
     * @var string 主键
     */
    protected string $primaryKey = 'child_uid';


    public function member(): HasOne
    {
        return $this->hasOne(MemberModel::class, 'uid', 'uid');
    }

    public function chlidRole(): HasOne
    {
        return $this->hasOne(MemberChildAuthRoleModel::class, 'role_id', 'child_role_id');
    }
}
