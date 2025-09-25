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

class FlowNodeReviewerModel extends HomeModel
{
    protected ?string $table = 'flow_node_reviewer';

    /* public function user()
     {
         return $this->hasOne(AdminUserModel::class, 'uid', 'uid')->field('uid,user_name');
     }*/

    public function getFlowIdAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    public function getNodeIdAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    public function member()
    {
        return $this->hasOne(MemberModel::class, 'uid', 'uid')
            ->select(['uid', 'user_name']);
    }

    public function member_child()
    {
        return $this->hasOne(MemberChildModel::class, 'child_uid', 'child_uid')
            ->select(['child_uid', 'child_name', 'name', 'head_url']);
    }

    public function user()
    {
        return $this->hasOne(AdminUserModel::class, 'uid', 'uid');
    }
}
