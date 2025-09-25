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

class OrderItemModel extends HomeModel
{
    protected ?string $table = 'order_item';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'item_id';

    public function categoryItem()
    {
        return $this->hasOne(GoodsCategoryItemModel::class, 'id', 'category_item_id');
    }

    public function category()
    {
        return $this->hasOne(RecordCategoryGoodsModel::class, 'id', 'category_item_id');
    }

    public function categoryImage()
    {
        return $this->hasOne(GoodsCategoryItemModel::class, 'id', 'category_item_id')
            ->select(['id', 'image']);
    }


    /**
     * @DOC sku数据 必须是备案通过的
     * @Name   record
     * @Author wangfei
     * @date   2023-09-01 2023
     * @return \Hyperf\Database\Model\Relations\HasOne
     */
    public function record()
    {
        return $this->hasOne(GoodsSkuModel::class, 'sku_id', 'sku_id')
            ->whereHas('goods', function ($query) {
                $query->where('record_status', '=', 3)->select(['record_status', 'goods_base_id']);
            });
    }

    //存在备案，但是未通过
    public function record_not()
    {
        return $this->hasOne(GoodsSkuModel::class, 'sku_id', 'sku_id')
            ->whereHas('goods', function ($query) {
                $query->where('record_status', '!=', 3)->select(['record_status', 'goods_base_id']);
            });

    }

    /**
     * @DOC   : 通过order_item 的item_code 获取sku数据
     * @Name  : item_to_sku
     * @Author: wangfei
     * @date  : 2025-01 14:05
     * @return \Hyperf\Database\Model\Relations\HasOne
     *
     */
    public function item_to_sku()
    {
        return $this->hasOne(GoodsSkuModel::class, 'sku_id', 'sku_id');
    }

    public function goods_sku()
    {
        return $this->belongsTo(GoodsSkuModel::class, 'sku_id', 'sku_id');
    }


    /**
     * @DOC 远程备案
     * @Name   convert
     * @Author wangfei
     * @date   2024/3/30 2024
     * @return \Hyperf\Database\Model\Relations\HasOne
     */
    public
    function convert()
    {
        return $this->hasOne(GoodsConvertRecordModel::class, 'item_code', 'item_record_sn');
    }


    public
    function goods()
    {
        return $this->hasOne(GoodsBaseModel::class, 'goods_base_id', 'goods_base_id');
    }


    /**
     * @DOC   : 异常明细
     * @Name  : exception_item
     * @Author: wangfei
     * @date  : 2024-12 19:32
     * @return \Hyperf\Database\Model\Relations\HasOne
     *
     */
    public
    function exception_item()
    {
        return $this->hasOne(OrderExceptionItemModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    public
    function order()
    {
        return $this->hasOne(OrderModel::class, 'order_sys_sn', 'order_sys_sn');
    }


}
