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

class OrderExceptionModel extends HomeModel
{
    protected ?string $table = 'order_exception';



    /**
     * @var string 主键
     */

    public function item()
    {
        return $this->hasMany(OrderExceptionItemModel::class, 'order_sys_sn', 'order_sys_sn');
    }


}
