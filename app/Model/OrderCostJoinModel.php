<?php


namespace App\Model;
class OrderCostJoinModel extends HomeModel
{
    protected ?string $table = 'order_cost_join';


    public function version()
    {
        return $this->hasOne(PriceTemplateVersionModel::class, 'version_id', 'member_version_id');

    }

    public function price_template()
    {
        return $this->hasOne(PriceTemplateModel::class, 'template_id', 'member_template_id');
    }

    /**
     * @DOC  确认收货、重新计算运费使用
     * @Name   single
     * @Author wangfei
     * @date   2023-07-13 2023
     * @return \Hyperf\Database\Model\Relations\HasOne
     */
    public function item()
    {
        return $this->hasMany(OrderCostJoinItemModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function cost()
    {
        return $this->hasOne(OrderCostModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function member_cost()
    {
        return $this->hasOne(OrderCostMemberModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    /**
     * @DOC
     * @Name   join_cost
     * @Author wangfei
     * @date   2023-09-23 2023
     * @return \Hyperf\Database\Model\Relations\HasMany
     */
    public function join_cost()
    {
        return $this->hasOne(OrderCostJoinModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function member_cost_item()
    {
        return $this->hasMany(OrderCostMemberItemModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function parcel()
    {
        return $this->hasOne(ParcelModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function order()
    {
        return $this->hasOne(OrderModel::class, 'order_sys_sn', 'order_sys_sn');
    }
}