<?php

declare(strict_types=1);

namespace App\Request;


class MemberRequest extends CommonRequest
{
    public array $scenes = [
        'query'          => ['uid'],
        'register'       => ['username', 'password', 'confirm_password', 'register_code'],
        'franchisee'     => ['username', 'password', 'confirm_password'],
        'sendCode'       => ['area_code', 'mobile', 'flag'],
        'bindPhone'      => ['area_code', 'mobile', 'code'],
        'code'           => ['code'],
        'changePassword' => ['password', 'new_password', 'confirm_password'],
        'auditCheck'     => ['uid', 'status', 'error'],
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
            'uid'              => 'required|integer',
            'username'         => 'required|min:4',
            'password'         => 'required',
            'confirm_password' => 'required',
            'new_password'     => 'required',
            'register_code'    => 'required',
            'area_code'        => 'required',
            'mobile'           => 'required',
            'flag'             => 'required',
            'code'             => 'required',
            'role'             => 'required',
            'status'           => 'required',
            'error'            => 'nullable',
        ];
    }

    public function messages(): array
    {
        $messages = parent::messages();
        return array_merge($messages, [


        ]);
    }
}
