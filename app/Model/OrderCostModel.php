<?php


namespace App\Model;

class OrderCostModel extends HomeModel
{
    protected ?string $table = 'order_cost';
    protected array $fillable = [
        'order_sys_sn',
        'transport_sn',
        'member_uid',
        'parent_join_uid',
        'parent_agent_uid',
        'product_id',
        'channel_id',
        'settlement_status',
        'platform_payment',
        'member_cost_payment',
        'member_has_payment',
        'join_cost_payment',
        'join_self_payment',
        'member_adjustment_payment',
        'join_has_payment',
        'join_profit_amount',
        'platform_refund_money',
        'platform_has_refund_money',
        'add_time',
        'settlement_time',
        'change_amount_sign',
    ];
}
