<?php

declare(strict_types=1);

namespace App\Request;


class NotifyRequest extends CommonRequest
{
    public array $scenes = [
        'create' => ['title', 'type', 'receive_uid', 'message', 'status', 'receive_status'],
        'update' => ['notify_id', 'title', 'type', 'receive_uid', 'message', 'status', 'receive_status'],
        'unread' => ['member_uid', 'notify_id'],
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
            'title'          => 'required|between:1,50',
            'type'           => 'required|integer',
            'receive_uid'    => 'array',
            'message'        => 'required',
            'member_uid'     => 'required|integer',
            'notify_id'      => 'required|integer',
            'status'         => 'required|integer',
            'receive_status' => 'required|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'          => '标题不能为空',
            'title.between'           => '标题数字超限',
            'type.required'           => '消息类型不能为空',
            'type.integer'            => '消息类型参数错误',
            'receive_uid.array'       => '收件人为数组',
            'message.required'        => '消息不能为空',
            'member_uid.required'     => '用户不能为空',
            'member_uid.integer'      => '用户参数错误',
            'notify_id.required'      => '消息不能为空',
            'notify_id.integer'       => '消息参数错误',
            'status.required'         => '发布状态不能为空',
            'status.integer'          => '发布状态参数错误',
            'receive_status.required' => '收件人状态不能为空',
            'receive_status.integer'  => '收件人状态参数错误',
        ];
    }

//    public function messages(): array
//    {
//        $messages = parent::messages();
//        return array_merge($messages, [
//            'between' => ':attribute must between :1 - :20',
//            'array'   => ':attribute must array',
//        ]);
//    }
}
