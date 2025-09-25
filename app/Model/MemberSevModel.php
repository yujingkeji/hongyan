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

class MemberSevModel extends HomeModel
{
    protected ?string $table = 'member_sev';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'member_sev_id';


    public function servers()
    {
        return $this->hasOne(SevModel::class, 'sev_id', 'sev_id');
    }


    /**
     * @DOC   : 线路五段节点
     * @Name  : node
     * @Author: wangfei
     * @date  : 2022-05-19 2022
     * @return \think\model\relation\HasOne
     */
    public function node()
    {
        return $this->hasOne(CategoryModel::class, 'cfg_id', 'sev_cfg_id');
    }

    public function supply()
    {
        return $this->hasOne(MemberModel::class, 'uid', 'supply_uid');
    }

    public function use()
    {
        return $this->hasOne(MemberModel::class, 'uid', 'use_uid');
    }

    public function country()
    {
        return $this->hasOne(CountryCodeModel::class, 'country_id', 'country_id');
    }
}
