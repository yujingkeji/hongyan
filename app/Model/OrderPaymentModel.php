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

class OrderPaymentModel extends HomeModel
{
    protected ?string $table = 'order_payment';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'item_id';

    protected array $fillable = [
        'order_sys_sn',
        'transaction_id',
        'out_trade_no',
        'payment_status',
        'child_uid',
        'member_uid',
        'parent_join_uid',
        'parent_agent_uid',
        'payment_code',
        'payment_method',
        'payment_currency',
        'payment_amount',
        'add_time',
        'update_time',
        'desc'
    ];

    /* public function costMember()
     {
         return $this->hasMany(OrderCostMemberModel::class, 'payment_sn', 'payment_sn');
     } */

    public function cost_member()
    {
        return $this->hasMany(OrderCostMemberModel::class, 'payment_sn', 'payment_sn');
    }


    public function costJoin()
    {
        return $this->hasMany(OrderCostJoinModel::class, 'payment_sn', 'payment_sn');
    }

    public function cost_member_item()
    {
        return $this->hasMany(OrderCostMemberItemModel::class, 'payment_sn', 'payment_sn');
    }

    public function cost_join_item()
    {
        return $this->hasMany(OrderCostJoinItemModel::class, 'payment_sn', 'payment_sn');
    }


    public function getPaymentSnAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }
}
