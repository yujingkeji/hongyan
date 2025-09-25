<?php

declare(strict_types=1);

namespace App\Model;


class ApiPlatformItemModel extends HomeModel
{
    protected ?string $table = 'api_platform_item';
    /**
     * @var string 主键
     */
    protected string $primaryKey = 'item_id';

    const UPDATED_AT = null;


    public function account()
    {
        return $this->hasOne(ApiMemberPlatformAccountModel::class, 'item_id', 'item_id');
    }
}
