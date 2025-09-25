<?php


namespace App\Model;


class AgentMemberModel extends HomeModel
{
    protected ?string $table = 'agent_member';


    const UPDATED_AT = null;

    public function member()
    {
        return $this->hasOne(MemberModel::class, 'uid', 'member_uid');
    }

    //平台代理
    public function agent()
    {
        return $this->hasOne(MemberModel::class, 'uid', 'parent_agent_uid');
    }

    //加盟商
    public function joins()
    {
        return $this->hasOne(MemberModel::class, 'uid', 'parent_join_uid');
    }

    public function role()
    {
        return $this->hasOne(MemberAuthRoleModel::class, 'role_id', 'role_id');
    }

    public function platform()
    {
        return $this->hasOne(AgentPlatformModel::class, 'agent_platform_uid', 'parent_agent_uid');
    }

    public function warehouse()
    {
        return $this->hasOne(WarehouseModel::class, 'ware_id', 'warehouse_id');
    }

}
