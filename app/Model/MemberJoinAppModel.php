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

class MemberJoinAppModel extends HomeModel
{
    protected ?string $table = 'member_join_app';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'id';

    public function jsons()
    {
        return $this->hasOne(MemberModel::class, 'uid', 'member_join_uid');
    }

}
