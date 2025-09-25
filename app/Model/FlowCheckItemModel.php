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

class FlowCheckItemModel extends HomeModel
{
    protected ?string $table = 'flow_check_item';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'item_id';

    public function member()
    {
        return $this->hasOne(MemberModel::class, 'uid', 'check_uid');
    }

    public function child()
    {
        return $this->hasOne(MemberChildModel::class, 'child_uid', 'check_child_uid');
    }

    public function flow()
    {
        return $this->hasOne(FlowModel::class, 'flow_id', 'flow_id');
    }

    public function check()
    {
        return $this->hasOne(FlowCheckModel::class, 'check_id', 'check_id');
    }

    public function version()
    {
        return $this->hasOne(PriceTemplateVersionModel::class, 'check_id', 'check_id');
    }

    /**
     * @DOC 授信申请
     * @Name   credit
     * @Author wangfei
     * @date   2023/11/10 2023
     * @return \Hyperf\Database\Model\Relations\HasOne
     */
    public function credit()
    {
        return $this->hasOne(MemberCreditApplyModel::class, 'check_id', 'check_id');
    }


    public function getFlowIdAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    public function getNodeIdAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    public function getItemIdAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    public function getCheckNodeIdAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }
}
