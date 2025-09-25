<?php

declare(strict_types=1);

namespace App\Request;


class UploadFileRequest extends CommonRequest
{
    public array $scenes = [
        'uploadFile' => ['image_md5', 'pic_name', 'upload_field', 'size'],
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
            'image_md5'    => 'required',
            'pic_name'     => 'required',
            'size'         => 'required',
            'upload_field' => 'required',
        ];
    }

    public function messages(): array
    {
        $messages = parent::messages();
        return array_merge($messages, [


        ]);
    }
}
