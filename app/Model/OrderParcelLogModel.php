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

use Hyperf\ModelCache\Cacheable;

class OrderParcelLogModel extends HomeModel
{
    use Cacheable;

    protected ?string $table = 'order_parcel_log';

    // 取号失败字段  log_code
    const OrderFail = '10001';


}
