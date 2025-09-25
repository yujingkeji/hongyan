<?php

declare(strict_types=1);

namespace App\Request;

use Hyperf\Validation\Request\FormRequest;
use Hyperf\Validation\Rule;


class OrdersRequest extends CommonRequest
{
    public array $scenes = [
        'parcelList'     => ['order_sys_sn', 'transport_sn', 'batch_sn', 'ware_id', 'parcel_status', 'product_id', 'start_time', 'end_time', 'page', 'limit'],
        'parcelSendList' => ['order_sys_sn', 'transport_sn', 'ware_id', 'line_id', 'parcel_status', 'channel_id', 'bl_sn', 'page', 'limit'],
        'edit'           => ['order_sys_sn', 'user_custom_sn', 'order_type', 'order_weight', 'order_total', 'from_platform_id', 'from_order_sn', 'pro_id', 'desc'],

    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *  {
     * "order_sys_sn": 16821556060001,
     * "user_custom_sn": "1201231242",
     * "order_type": 19,
     * "order_weight": 1,
     * "order_total": 20,
     * "from_platform_id": "1790",
     * "from_order_sn": "20125801213",
     * "pro_id": 10007,
     * "desc": "赶快给我发",
     * "sender": {
     * "zip": "230031",
     * "name": "算力小镇",
     * "country": "中国",
     * "country_id": 1,
     * "province": "浙江省",
     * "province_id": 327504,
     * "city": "杭州市",
     * "city_id": 328136,
     * "district": "临平区",
     * "district_id": 370068,
     * "street": "乔司街道",
     * "street_id": 370073,
     * "address": "杭海路1602号算力小镇B栋613",
     * "phone": "0571-2475810",
     * "mobile": "13866610000",
     * "is_save": 1
     * },
     * "receiver": {
     * "zip": "246680",
     * "name": "费龙",
     * "country": "中国",
     * "country_id": 1,
     * "province": "安徽省",
     * "province_id": 327504,
     * "city": "安庆市",
     * "city_id": 328136,
     * "district": "岳西县",
     * "district_id": 328247,
     * "street": "菖蒲镇",
     * "address": "转桥村转桥组20号",
     * "phone": "0556-2472370",
     * "mobile": "13866660000",
     * "identity_code": "310112760920051",
     * "is_save": 1
     * },
     * "item": [
     * {
     * "category_item_id": "3",
     * "goods_base_id": "",
     * "sku_id": "",
     * "item_sku_name": "床上用品1",
     * "item_code": "",
     * "item_spec": "规格",
     * "item_sku": "",
     * "item_num": 1,
     * "item_price": "230",
     * "item_price_unit": "RMB",
     * "item_record_sn": ""
     * },
     * {
     * "category_item_id": "3",
     * "goods_base_id": "",
     * "sku_id": "",
     * "item_sku_name": "床上用品",
     * "item_code": "",
     * "item_spec": "规格",
     * "item_sku": "",
     * "item_num": 1,
     * "item_price": "230",
     * "item_price_unit": "RMB",
     * "item_record_sn": ""
     * }
     * ]
     * }
     */
    public function rules(): array
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

    public function messages(): array
    {
        $messages = parent::messages();
        return array_merge($messages, [
            'sender.required'        => 'sender 地址不能为空',
            'sender.array'           => 'sender 地址为数组集合',
            'sender.zip.numeric'     => 'sender.zip code must be a number',
            'sender.name.required'   => 'sender.name must be filled out',
            'sender.is_save.integer' => 'sender.is_save must be an integer',


            'receiver.required'        => 'receiver 地址不能为空',
            'receiver.array'           => 'receiver 地址为数组集合',
            'receiver.zip.numeric'     => 'receiver.zip code must be a number',
            'receiver.name.required'   => 'receiver.name must be filled out',
            'receiver.is_save.integer' => 'receiver.is_save must be an integer',


            'start_time.required_with' => 'start_time、end_time不能只填写一个日期',
            'end_time.required_with'   => 'start_time、end_time不能只填写一个日期'

        ]);
    }
}
