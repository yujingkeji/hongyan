<?php


namespace App\Model;

class OrderCostJoinItemModel extends HomeModel
{
    protected ?string $table = 'order_cost_join_item';
    protected string $primaryKey = 'item_id';

    protected array $fillable = [
        'order_sys_sn', // 订单系统编号
        'transport_sn', // 运单号
        'payment_sn', // 支付单号
        'should_member_uid', // 应付人，一般为加盟商上ID
        'real_member_uid', // 实际支付人：加盟商支付的时候与should_member_uid值一样
        'parent_agent_uid', // 上级平台代理ID
        'payment_status', // 0:未支付，1：已支付
        'charge_code', // 收费代码 config 表的cfg_id(加盟商与平台收费代码)
        'charge_code_name', // 收费名称
        'payment_type', // 支付类型：表：config的config_id，PID= 14301
        'payment_code', // 支付代码
        'payment_method', // 支付方式 ，记录当前金额的支付方式
        'original_total_fee', // 原价
        'discount', // 折扣
        'original_total_discount_fee', // 原价折扣后的金额
        'payment_currency', // 支付币种
        'payment_amount', // 支付金额
        'exchange_rate', // 汇率
        'exchange_amount', // 换汇后金额、直接充值到余额的时候需要换算
        'income_currency', // 收入币种
        'desc', // 扣款备注
        'add_time', // 添加时间
        'update_time', // 更新时间
    ];

    public function getPaymentSnAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }
}
