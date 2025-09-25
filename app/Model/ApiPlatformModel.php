<?php

declare(strict_types=1);

namespace App\Model;


class ApiPlatformModel extends HomeModel
{
    protected ?string $table = 'api_platform';
    /**
     * @var string 主键
     */
    protected string $primaryKey = 'platform_id';

    const UPDATED_AT = null;

    const XUNIWL = 9;

    public function item()
    {
        return $this->hasMany(ApiPlatformItemModel::class, 'platform_id', 'platform_id');
    }

    public function cfg()
    {
        return $this->hasOne(CategoryModel::class, 'cfg_id', 'platform_cfg_id');
    }

    public function interface()
    {
        return $this->hasMany(ApiPlatformInterfaceModel::class, 'platform_id', 'platform_id');
    }

    public function account()
    {
        return $this->hasMany(ApiMemberPlatformAccountModel::class, 'platform_id', 'platform_id');
    }
}
