<?php


namespace App\Model;

class OrderCostMemberItemModel extends HomeModel
{
    protected ?string $table = 'order_cost_member_item';
    protected string $primaryKey = 'item_id';
    public function getPaymentSnAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    protected array $fillable =
        [
            'order_sys_sn',
            'payment_sn',
            'member_uid',
            'parent_join_uid',
            'parent_agent_uid',
            'charge_code',
            'charge_code_name',
            'payment_status',
            'payment_code',
            'payment_method',
            'payment_currency',
            'original_total_fee',
            'discount',
            'payment_amount',
            'exchange_rate',
            'exchange_amount',
            'income_currency',
            'info',
            'supplement_desc',
            'add_time',
            'update_time',
        ];

}
