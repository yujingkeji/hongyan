<?php

declare(strict_types=1);

namespace App\Model;

class MemberCreditApplyModel extends HomeModel
{
    protected ?string $table      = 'member_credit_apply';
    protected string  $primaryKey = 'apply_id';


    public function check()
    {
        return $this->hasOne(FlowCheckModel::class, 'check_id', 'check_id');
    }


    public function target_member()
    {
        return $this->hasOne(MemberModel::class, 'uid', 'target_member_uid');
    }

    public function apply_member()
    {
        return $this->hasOne(MemberModel::class, 'uid', 'apply_join_uid');
    }

    //平台代理
    public function agent()
    {
        return $this->hasOne(MemberModel::class, 'uid', 'parent_agent_uid');
    }
}
