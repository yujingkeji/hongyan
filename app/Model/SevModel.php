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

class SevModel extends HomeModel
{
    protected ?string $table = 'sev';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'sev_id';

    public function port()
    {
        return $this->hasOne(PortModel::class, 'port_id', 'port_id');
    }

    public function country()
    {
        return $this->hasOne(CountryCodeModel::class, 'country_id', 'country_id');
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

    public function use()
    {
        return $this->hasOne(MemberSevModel::class, 'sev_id', 'sev_id');
    }


    public function getAreaAttribute($value)
    {
        return !empty($value) ? json_decode($value, true) : [];
    }

}
