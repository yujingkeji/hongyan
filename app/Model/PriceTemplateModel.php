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

class PriceTemplateModel extends HomeModel
{
    protected ?string $table = 'price_template';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'template_id';


    /**
     * @DOC 发出
     * @Name   send
     * @Author wangfei
     * @date   2023/11/2 2023
     * @return \Hyperf\Database\Model\Relations\HasOne
     */
    public function send()
    {
        return $this->hasOne(CountryCodeModel::class, 'country_id', 'send_country_id');
    }

    /**
     * @DOC   : 目的
     * @Name  : target
     * @Author: wangfei
     * @date  : 2022-12-30 2022
     * @return \Hyperf\Database\Model\Relations\HasOne
     */
    public function target()
    {
        return $this->hasOne(CountryCodeModel::class, 'country_id', 'target_country_id');
    }


    /**
     * @DOC    正在使用的版本
     * @Name   use
     * @Author wangfei
     * @date   2023-07-11 2023
     */
    public function use()
    {
        return $this->hasOne(PriceTemplateVersionModel::class, 'version_id', 'use_version');
    }

    public function member()
    {
        return $this->hasOne(MemberModel::class, 'uid', 'member_uid');
    }

    public function currency()
    {
        return $this->hasOne(CountryCurrencyModel::class, 'currency_id', 'currency_id');
    }

    public function check()
    {
        return $this->hasOne(PriceTemplateVersionModel::class, 'version_id', 'check_version');
    }

    public function flow()
    {
        return $this->hasOne(FlowModel::class, 'flow_id', 'flow_id');
    }

    /**
     * @DOC   : 版本内容(每个版本对应内容)
     * @Name  : version
     * @Author: wangfei
     * @date  : 2022-07-29 2022
     * @return \think\model\relation\HasMany
     */
    public function version()
    {
        return $this->hasMany(PriceTemplateVersionModel::class, 'template_id', 'template_id');
    }
}
