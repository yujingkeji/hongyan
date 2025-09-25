<?php

declare(strict_types=1);

namespace App\Request;

use Hyperf\Validation\Request\FormRequest;
use Hyperf\Validation\Rule;


class BlRequest extends CommonRequest
{
    public array $scenes = [
        'add'      => ['transport_type', 'bl_main_sn', 'box_item', 'box_item.*.box', 'box_item.*.total', 'part_item_sn', 'send_port',
                       'destination_port', 'unloading_port', 'transit_port', 'transit_port_code', 'cargo_owner_mark',
                       'ship_name', 'ship_sequence', 'wharf', 'etd', 'eat', 'cut_off_time', 'closing_time', 'shipper',
                       'receiver', 'shipping_mark', 'goods_name_en', 'entrust_total', 'entrust_gross_weight', 'entrust_unit',
                       'entrust_volume', 'real_entrust_total', 'real_entrust_gross_weight', 'real_entrust_unit', 'real_entrust_volume',
                       'payment_method', 'transportation_terms', 'bl_shape', 'bl_upper_limit', 'bl_upper_weight'],
        'lists'    => ['page', 'limit', 'time_type', 'start_time', 'end_time'],
        'nodeList' => ['page', 'limit', 'bl_sn'],
        'edit'     => ['bl_sn', 'transport_type', 'bl_main_sn', 'box_item', 'box_item.*.box', 'box_item.*.total', 'part_item_sn', 'send_port',
                       'destination_port', 'unloading_port', 'transit_port', 'transit_port_code', 'cargo_owner_mark',
                       'ship_name', 'ship_sequence', 'wharf', 'etd', 'eat', 'cut_off_time', 'closing_time', 'shipper',
                       'receiver', 'shipping_mark', 'goods_name_en', 'entrust_total', 'entrust_gross_weight', 'entrust_unit',
                       'entrust_volume', 'real_entrust_total', 'real_entrust_gross_weight', 'real_entrust_unit', 'real_entrust_volume',
                       'payment_method', 'transportation_terms', 'bl_shape', 'bl_upper_limit', 'bl_upper_weight'],
        'done'     => ['bl_sn'],


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
     */
    public function rules(): array
    {
        return [
            "transport_type"            => ["required",
                                            Rule::in([11, 12, 13])],
            "bl_sn"                     => "min:10",
            "bl_main_sn"                => "required",
            "box_item"                  => 'array',
            'box_item.*.box'            => "min:1",
            "box_item.*.total"          => "numeric",
            "part_item_sn"              => 'array',
            "send_port"                 => "min:1",
            "destination_port"          => "min:1",
            "unloading_port"            => "min:1",
            "transit_port"              => "min:1",
            "transit_port_code"         => "min:1",
            "cargo_owner_mark"          => "min:1",
            "ship_name"                 => "min:1",
            "ship_sequence"             => "min:1",
            "wharf"                     => "min:1",
            "etd"                       => "min:1",
            "eat"                       => "min:1",
            "cut_off_time"              => "min:1",
            "closing_time"              => "min:1",
            "shipper"                   => "min:1",
            "receiver"                  => "min:1",
            "shipping_mark"             => "min:1",
            "goods_name_en"             => "min:1",
            "entrust_total"             => "min:1",
            "entrust_gross_weight"      => "min:1",
            "entrust_unit"              => "min:1",
            "entrust_volume"            => "min:1",
            "real_entrust_total"        => "min:1",
            "real_entrust_gross_weight" => "min:1",
            "real_entrust_unit"         => "min:1",
            "real_entrust_volume"       => "min:1",
            "payment_method"            => "min:1",
            "transportation_terms"      => "min:1",
            "bl_shape"                  => "min:1",
            "bl_upper_limit"            => "min:1",
            "bl_upper_weight"           => "min:1",
            "page"                      => "required|numeric",
            "limit"                     => "required|numeric",
            "time_type"                 => [Rule::in(['etd', 'eat', 'cut_off_time', 'closing_time'])],
            "start_time"                => ['date_format:Y-m-d H:i:s'],
            "end_time"                  => ['date_format:Y-m-d H:i:s'],
        ];
    }

    public function messages(): array
    {
        $messages = parent::messages();
        return array_merge($messages, [
            'bl_sn.required'           => 'bl_sn  must be required',
            'transport_type.required'  => 'transport_type  must be required',
            'transport_type.in'        => 'transport_type  must in 11,12,13',
            'bl_main_sn.required'      => 'bl_main_sn  must be required',
            'box_item'                 => 'box_item  must be array',
            'box_item.*.total.numeric' => 'bl_main_sn  must be numeric',
            'part_item_sn'             => 'part_item_sn  must be array',
            'page.required'            => 'page  must be required',
            'limit.required'           => 'limit  must be required',
            'page.numeric'             => 'page  must be numeric',
            'limit.numeric'            => 'limit  must be numeric',
            'time_type.in'             => 'time_type not in [etd, eat, cut_off_time, closing_time]',
            'start_time.date_format'   => 'start_time format is not Y-m-d H:i:s',
            'end_time.date_format'     => 'end_time format is not Y-m-d H:i:s',

        ]);

    }
}
