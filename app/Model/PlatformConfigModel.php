<?php

namespace App\Model;

class PlatformConfigModel extends HomeModel
{
    protected ?string $table = 'platform_config';

    // 费用配置
    const TypeCost = 8;

    public function item()
    {
        return $this->hasMany(PlatformConfigItemModel::class, 'platform_id', 'platform_id');
    }

    public function group()
    {
        return $this->hasOne(PlatformConfigItemModel::class, 'item_id', 'group_id');
    }

}
