<?php

declare(strict_types=1);

namespace App\Model;


class ApiMemberPlatformModel extends HomeModel
{
    protected ?string $table = 'api_member_platform';
    /**
     * @var string 主键
     */
    protected string $primaryKey = 'member_platform_id';

    const UPDATED_AT = null;

    /**
     * @DOC   : 平台信息
     * @Name  : platform
     * @Author: wangfei
     * @date  : 2022-06-16 2022
     * @return \think\model\relation\HasOne
     */
    public function platform()
    {
        return $this->hasOne(ApiPlatformModel::class, 'platform_id', 'platform_id');
    }

    public function account()
    {
        return $this->hasMany(ApiMemberPlatformAccountModel::class, 'member_platform_id', 'member_platform_id');
    }

    public function country()
    {
        return $this->hasOne(CountryCodeModel::class, 'country_id', 'country_id');
    }
    public function item()
    {
        return $this->hasMany(ApiPlatformItemModel::class, 'platform_id', 'platform_id');
    }


}
