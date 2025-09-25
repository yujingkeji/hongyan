<?php

declare(strict_types=1);

namespace App\Request;


class ThirdConfigureRequest extends CommonRequest
{
    public array $scenes = [
        'default' => ['third_code', 'status'],
        'huaquan' => ['appKey', 'appSecret'],
        'info'    => ['third_id'],
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
            "third_id"   => 'required',
            'third_code' => 'required|between:2,15',
            "status"     => 'required|integer|in:0,1',
            "appKey"     => 'required',
            "appSecret"  => 'required',
        ];
    }

    public function messages(): array
    {
        $messages = parent::messages();
        return array_merge($messages, [


        ]);
    }
}
