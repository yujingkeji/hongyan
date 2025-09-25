<?php

declare(strict_types=1);

namespace App\Model;


class ApiMemberPlatformAccountModel extends HomeModel
{
    protected ?string $table = 'api_member_platform_account';
    /**
     * @var string 主键
     */
    protected string $primaryKey = 'account_id';

    const UPDATED_AT = null;


    public function item()
    {
        return $this->hasOne(ApiPlatformItemModel::class, 'item_id', 'item_id');
    }
}
