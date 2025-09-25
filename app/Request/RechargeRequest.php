<?php

declare(strict_types=1);

namespace App\Request;


class RechargeRequest extends CommonRequest
{
    public array $scenes = [
        'wx'   => ['body', 'order_no', 'amount'],
        'does' => ['amount', 'member_id', 'child_uid'],
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
            'amount'           => 'required',
            'member_id'        => 'required',
            'subordinate'      => 'required',
            'child_uid'        => 'required',
            'parent_agent_uid' => 'required',
            'body'             => 'required',
            'order_no'         => 'required',
            'attach'           => 'required',
            'goods_tag'        => 'required',
        ];
    }


    public function messages(): array
    {
        $messages = parent::messages();
        return array_merge($messages, [

        ]);
    }
}
