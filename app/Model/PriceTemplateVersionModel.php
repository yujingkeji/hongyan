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

class PriceTemplateVersionModel extends HomeModel
{
    protected ?string $table = 'price_template_version';
    //'审核状态 0 待提交，1：审核中 2：同意 ,3：拒绝,4：撤销。
    /**
     * @var int 待提交
     * */
    public const STATUS_WAIT = 0;
    /**
     * @var int 审核中
     */
    public const STATUS_CHECKING = 1;
    /**
     * @var int 已同意
     */
    public const STATUS_AGREE = 2;
    /**
     * @var int 拒绝
     */
    public const STATUS_REFUSE = 3;
    /**
     * @var int 撤销
     */
    public const STATUS_CANCEL = 4;
    /**
     * @var string 主键
     */
    protected string $primaryKey = 'version_id';

    /**
     * @DOC   版本内容
     * @Name   item
     * @Author wangfei
     * @date   2023-07-11 2023
     * @return \Hyperf\Database\Model\Relations\HasMany
     */
    public function item()
    {
        return $this->hasMany(PriceTemplateItemModel::class, 'version_id', 'version_id');
    }

    public function child()
    {
        return $this->hasOne(MemberChildModel::class, 'child_uid', 'child_uid');
    }

    public function member()
    {
        return $this->hasOne(MemberModel::class, 'uid', 'member_uid');
    }

    public function check()
    {
        return $this->hasOne(FlowCheckModel::class, 'check_id', 'check_id');
    }

    public function template()
    {
        return $this->hasOne(PriceTemplateModel::class, 'template_id', 'template_id');
    }

}
