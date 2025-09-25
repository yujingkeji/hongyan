<?php

declare(strict_types=1);


namespace App\Model;

/** 交接单模型
 * Class DeliveryReceiptModel
 * @package App\Model
 */
class DeliveryHandoverModel extends HomeModel
{
    protected ?string $table = 'delivery_handover';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'delivery_sn';


}
