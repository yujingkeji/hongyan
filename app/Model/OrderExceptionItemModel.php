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

class OrderExceptionItemModel extends HomeModel
{
    protected ?string $table = 'order_exception_item';

    /**
     * @var int 需要备案
     */
    const RECORD_YES_INIT = 22002;

    /**
     * @var string 主键
     */


    #protected string $primaryKey = 'exception_sys_sn';

    public function order()
    {
        return $this->hasOne(OrderModel::class, 'order_sys_sn', 'order_sys_sn');
    }


}
