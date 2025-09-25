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

use Hyperf\Database\Model\Relations\HasOne;

class MemberThirdConfigureModel extends HomeModel
{
    protected ?string $table = 'member_third_configure';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'member_third_id';


    public function third(): hasOne
    {
        return $this->hasOne(ThirdConfigureModel::class, 'third_id', 'third_id');
    }

    public function field(): \Hyperf\Database\Model\Relations\HasMany
    {
        return $this->hasMany(MemberThirdConfigureItemModel::class, 'member_third_id', 'member_third_id');
    }

}
