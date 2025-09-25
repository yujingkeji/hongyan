<?php

declare(strict_types=1);

namespace App\Request\Lib;

use Hyperf\Validation\Rule;

class OrdersLib extends BaseLib
{
    public function bak(): array
    {

        return [
            'order_sys_sn'     => 'integer',
            'transport_sn'     => 'alpha_num',
            'batch_sn'         => 'integer|numeric',
            'ware_id'          => 'integer|numeric',
            'line_id'          => 'integer|numeric',
            'parcel_status'    => 'integer|numeric',
            'product_id'       => 'integer',
            'channel_id'       => 'integer',
            'bl_main_sn'       => 'min:10',
            'bl_sn'            => 'min:10',
            'start_time'       => 'required_with:end_time|date',
            'end_time'         => 'required_with:start_time|date',
            'page'             => 'integer',
            'limit'            => 'integer',
            'receiver_id'      => 'required|integer',
            'package_sn'       => 'required|string|min:1',
            'user_custom_sn'   => 'string|min:5',
            'order_type'       => ['string', Rule::in([19, 20, 21, 22])],
            'order_weight'     => ['numeric'],
            'order_total'      => ['numeric'],
            'from_platform_id' => ['integer'],
            'pro_id'           => ['integer'],
            'desc'             => ['string'],
            'sender'           => ['required', 'array'],
            'sender.zip'       => ['numeric'],
            'sender.name'      => ['required', 'min:2'],
            'sender.address'   => ['string', 'min:2'],
            'sender.phone'     => ['required_without:mobile', 'string', 'min:2'],
            'sender.mobile'    => ['required_without:phone', 'string', 'min:2'],
            'sender.is_save'   => ['integer', Rule::in([0, 1])],

            'receiver'         => ['required', 'array'],
            'receiver.zip'     => ['numeric'],
            'receiver.name'    => ['required', 'min:2'],
            'receiver.address' => ['string', 'min:2'],
            'receiver.phone'   => ['required_without:mobile', 'string', 'min:2'],
            'receiver.mobile'  => ['required_without:phone', 'string', 'min:2'],
            'receiver.is_save' => ['integer', Rule::in([0, 1])],
        ];
    }

    //编辑验证

    /**
     * @DOC
     * @Name   OrderEditRules
     * @Author wangfei
     * @date   2023-09-12 2023
     * @param array $ProductIdArr 已经添加的产品ID数组集合
     * @return array
     */
    public function OrderEditRules(array $ProductIdArr = []): array
    {
        return [
            'order_sys_sn'     => ['required', 'string'],
            'user_custom_sn'   => 'string|min:5',
            'order_type'       => ['numeric', Rule::in([19, 20, 21, 22])],
            'order_weight'     => ['numeric'],
            'order_total'      => ['numeric'],
            'from_platform_id' => ['integer'],
            'pro_id'           => ['required', 'integer', Rule::in($ProductIdArr)],
            'desc'             => ['nullable', 'string'],
            'sender'           => ['required', 'array'],
            'receiver'         => ['required', 'array'],
        ];
    }

    /**
     * @DOC  添加规则验证
     * @Name   OrderAddRules
     * @Author wangfei
     * @date   2023-09-12 2023
     * @return array
     */
    public function OrderAddRules(array $ProductIdArr = []): array
    {
        return [
            'user_custom_sn'   => 'string|min:5',
            'invited_uid'      => ['nullable', 'integer'],
            'order_type'       => ['numeric', Rule::in([19, 20, 21, 22])],
            'order_weight'     => ['required', 'numeric', 'min:0.01'],
            'order_total'      => ['numeric'],
            'from_platform_id' => ['integer'],
            'line_id'          => ['required', 'integer'],
            'pro_id'           => ['required', 'integer', Rule::in($ProductIdArr)],
            'desc'             => ['string'],
            'sender'           => ['required', 'array'],
            'receiver'         => ['required', 'array'],
        ];
    }

    /**
     * @DOC  添加规则验证返回信息
     */
    public function OrderAddMsg(): array
    {
        return [
            'user_custom_sn.min'       => '自定义订单号不能小于5位',
            'order_type.in'            => '订单类型不正确',
            'order_weight.required'    => '请填写订单重量',
            'order_weight.numeric'     => '订单重量必须为数字',
            'order_weight.min'         => '订单重量不能小于0.01',
            'order_total.numeric'      => '商品金额必须为数字',
            'from_platform_id.integer' => '来源平台ID必须为数字',
            'line_id.required'         => '未检测到线路信息',
            'line_id.integer'          => '线路ID必须为数字',
            'pro_id.required'          => '产品ID不能为空',
            'pro_id.integer'           => '产品ID必须为数字',
            'pro_id.in'                => '产品ID不在产品列表中',
            'desc.string'              => '订单描述必须为字符串',
            'sender.required'          => '发件人信息不能为空',
            'receiver.required'        => '收件人信息不能为空',
        ];
    }

    /**
     * @DOC  若填写了Item_Id 将进行验证是否在原来商品明细里
     * @Name   OrderItemRules
     * @Author wangfei
     * @date   2023-09-11 2023
     * @param array $itemIdAdd
     * @return array
     */
    public function OrderItemRules(array $itemIdAdd = []): array
    {
        return
            [
                '*.category_item_id' => ['required', 'numeric'],
                '*.category_item'    => ['nullable'],
                '*.item_id'          => ['integer', Rule::in($itemIdAdd)],
                '*.goods_base_id'    => ['integer'],
                '*.sku_id'           => ['numeric'],
                '*.item_sku_name'    => ['nullable', 'string', 'min:1'],
                '*.item_code'        => ['nullable', 'string', 'min:5'],
                '*.item_spec'        => ['string'],
                '*.item_num'         => ['required', 'numeric', 'min:1'],
                '*.item_price'       => ['required', 'numeric', 'min:1'],
                '*.item_price_unit'  => ['nullable', 'string'],
                '*.item_record_sn'   => ['nullable'],
                '*.item_tax'         => ['numeric'],
                '*.record_sku_id'    => ['nullable', 'numeric'],
                '*.brand_id'         => ['nullable', 'numeric'],
                '*.brand_name'       => ['nullable', 'string'],
            ];
    }

    /**
     * @DOC  验证
     */
    public function OrderItemMsg(): array
    {
        return [
            '*.category_item_id.required' => '分类ID不能为空',
            '*.category_item_id.numeric'  => '分类ID必须为数字',
            '*.item_id.integer'           => '商品ID必须为数字',
            '*.item_id.in'                => '商品ID不在商品明细中',
            '*.goods_base_id.integer'     => '商品基础ID必须为数字',
            '*.sku_id.numeric'            => 'SKU ID必须为数字',
            '*.item_sku_name.string'      => 'SKU名称必须为字符串',
            '*.item_sku_name.min'         => 'SKU名称不能小于1位',
            '*.item_code.string'          => '商品编码必须为字符串',
            '*.item_code.min'             => '商品编码不能小于5位',
            '*.item_spec.required'        => '商品规格不能为空',
            '*.item_spec.string'          => '商品规格必须为字符串',
            '*.item_num.required'         => '商品数量不能为空',
            '*.item_num.numeric'          => '商品数量必须为数字',
            '*.item_num.min'              => '商品数量不能小于1',
            '*.item_price.required'       => '商品单价不能为空',
            '*.item_price.numeric'        => '商品单价必须为数字',
            '*.item_price.min'            => '商品单价不能小于1',
            '*.item_price_unit.string'    => '商品单价单位必须为字符串',
            '*.item_tax.numeric'          => '商品税率必须为数字',
            '*.record_sku_id.numeric'     => '商品记录SKU ID必须为数字',
            '*.brand_id.numeric'          => '品牌ID必须为数字',
            '*.brand_name.string'         => '品牌名称必须为字符串',
            '*.brand_name.min'            => '品牌名称不能小于1位',
        ];
    }


    public function messages(): array
    {
        return parent::messages();

    }
}
