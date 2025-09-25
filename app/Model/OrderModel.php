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


use Hyperf\DbConnection\Db;

class OrderModel extends HomeModel
{
    protected ?string $table = 'order';
    const Order_Status_Record_Pass = 22006; //订单备案通过
    const Order_Status_Record_Personal = 22002; //个人物品需要备案
    const Order_Status_Record_Personal_Fail = 22003; //个人物物品备案失败

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'order_sys_sn';


    public function member()
    {
        return $this->hasOne(MemberModel::class, 'uid', 'member_uid');
    }

    //平台代理
    public function agent()
    {
        return $this->hasOne(MemberModel::class, 'uid', 'parent_agent_uid');
    }

    //加盟商
    public function joins()
    {
        return $this->hasOne(MemberModel::class, 'uid', 'parent_join_uid');
    }

    /**
     * @DOC   产品
     * @Name   product
     * @Author wangfei
     * @date   2023-08-11 2023
     * @return \Hyperf\Database\Model\Relations\HasOne
     */
    public function product()
    {
        return $this->hasOne(ProductModel::class, 'pro_id', 'pro_id');
    }

    /**
     * @DOC   : 发件人
     * @Name  : sender
     * @Author: wangfei
     * @date  : 2022-11-23 2022
     * @return HasOne
     */
    public function sender()
    {
        return $this->hasOne(OrderSenderModel::class, 'batch_sn', 'batch_sn');
    }


    public function line()
    {
        return $this->hasOne(LineModel::class, 'line_id', 'line_id');
    }

    /**
     * @DOC   : 收件人
     * @Name  : receiver
     * @Author: wangfei
     * @date  : 2022-11-23 2022
     * @return HasOne
     */
    public function receiver()
    {
        return $this->hasOne(OrderReceiverModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function item()
    {
        return $this->hasMany(OrderItemModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    /**
     * @DOC   :
     * @Name  : channel
     * @Author: wangfei
     * @date  : 2023-04-17 2023
     * @return HasOne
     */
    public function channel()
    {
        return $this->hasOne(ChannelModel::class, 'channel_id', 'channel_id');
    }

    public function cost_member()
    {
        return $this->hasOne(OrderCostMemberModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function cost_join()
    {
        return $this->hasOne(OrderCostJoinModel::class, 'order_sys_sn', 'order_sys_sn');
    }


    //重新取号，获取支付号，当做支付批次号使用
    public function cost_member_item()
    {
        return $this->hasMany(OrderCostMemberItemModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function cost_join_item()
    {
        return $this->hasMany(OrderCostJoinItemModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function parcel()
    {
        return $this->hasOne(ParcelModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function log()
    {
        return $this->hasMany(OrderParcelLogModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function send()
    {
        return $this->hasOne(ParcelSendModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function exception()
    {
        return $this->hasOne(OrderExceptionModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function exception_item()
    {
        return $this->hasOne(OrderExceptionItemModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function cost()
    {
        return $this->hasOne(OrderCostModel::class, 'order_sys_sn', 'order_sys_sn');
    }


    /**
     * @DOC 清关节点
     * @Name   parcel_import
     * @Author wangfei
     * @date   2023/10/13 2023
     * @return \Hyperf\Database\Model\Relations\HasOne
     */
    public function parcel_import()
    {
        return $this->hasOne(ParcelImportModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function fromPlatform()
    {
        return $this->hasOne(CategoryModel::class, 'cfg_id', 'from_platform_id');
    }

    public function ware()
    {
        return $this->hasOne(WarehouseModel::class, 'ware_id', 'ware_id');
    }

    public function swap()
    {
        return $this->hasOne(ParcelSwapModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function parcelException()
    {
        return $this->hasMany(ParcelExceptionItemModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function transport()
    {
        return $this->hasOne(ChannelTransportModel::class, 'channel_id', 'channel_id');
    }

    public function prediction()
    {
        return $this->hasMany(DeliveryStationModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function predictionParcel()
    {
        return $this->hasMany(DeliveryStationParcelModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public function delivery_order_pack()
    {
        return $this->hasOne(DeliveryOrderPackModel::class, 'order_sys_sn', 'order_sys_sn');
    }
    public function delivery_station()
    {
        return $this->hasOne(DeliveryStationModel::class, 'order_sys_sn', 'order_sys_sn');
    }

}
