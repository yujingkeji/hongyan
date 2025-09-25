<?php

namespace App\Model;

class OrderCostMemberModel extends HomeModel
{
    protected ?string $table = 'order_cost_member';


    public function getAddTimeAttribute($value)
    {
        if (!empty($value)) return date("Y-m-d H:i:s", $value);

    }

    public function getPayTimeAttribute($value)
    {
        if (!empty($value)) return date("Y-m-d H:i:s", $value);
    }

    /**
     * @DOC   : 版本内容(每个版本对应内容)
     * @Name  : version
     * @Author: wangfei
     * @date  : 2022-07-29 2022
     * @return \think\model\relation\HasMany
     */
    public function version()
    {
        return $this->hasOne(PriceTemplateVersionModel::class, 'version_id', 'member_version_id');
    }

    public function member_cost()
    {
        return $this->hasOne(OrderCostMemberModel::class, 'order_sys_sn', 'order_sys_sn');
    }




    /**
     * @DOC  确认收货、重新计算运费使用
     * @Name   item
     * @Author wangfei
     * @date   2023-07-13 2023
     * @return \Hyperf\Database\Model\Relations\HasOne
     */
    public function item()
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

    public function price_template()
    {
        return $this->hasOne(PriceTemplateModel::class, 'template_id', 'member_template_id');
    }

}