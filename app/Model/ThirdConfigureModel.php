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

use Hyperf\Database\Model\Relations\HasMany;

class ThirdConfigureModel extends HomeModel
{
    protected ?string $table = 'third_configure';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'third_id';


    /**
     * @DOC
     * @Name   field
     * @Author wangfei
     * @date   2023/11/8 2023
     * @return HasMany
     */
    public function field()
    {
        return $this->hasMany(ThirdConfigureFieldModel::class, 'third_id', 'third_id');
    }

    /**
     * @DOC 关联查询用户是否已经配置
     * @Name   member_third
     * @Author wangfei
     * @date   2023/11/8 2023
     * @return \Hyperf\Database\Model\Relations\HasOne
     */
    public function member_third()
    {
        return $this->hasOne(MemberThirdConfigureModel::class, 'third_id', 'third_id');
    }


}
