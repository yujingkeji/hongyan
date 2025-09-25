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

/**
 * 用户目录表
 * Class MemberAuthMenuModel.
 */
class WorkAuthMenuModel extends HomeModel
{
    protected ?string $table = 'work_auth_menu';

    public function children()
    {
        return $this->hasMany(WorkAuthMenuModel::class, 'menu_pid', 'menu_id');
    }


}
