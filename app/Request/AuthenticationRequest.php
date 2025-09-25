<?php

declare(strict_types=1);

namespace App\Request;


class AuthenticationRequest extends CommonRequest
{
    public array $scenes = [
        'personal'   => ['country', 'country_id', 'province', 'province_id', 'address', 'card_type', 'card_name', 'card_number', 'issuing_date', 'expiry_date', 'photo_path'],
        'enterprise' => ['country', 'country_id', 'province', 'province_id', 'address', 'card_type', 'card_number', 'card_name', 'issuing_date', 'expiry_date', 'photo_path', 'co_name', 'co_card_number', 'co_photo_path'],

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
            'country'        => 'required',
            'country_id'     => 'required',
            'province'       => 'required',
            'province_id'    => 'required',
            'city'           => 'required',
            'city_id'        => 'required',
            'district'       => 'required',
            'district_id'    => 'required',
            'street'         => 'required',
            'street_id'      => 'required',
            'address'        => 'required',
            'card_type'      => 'required',
            'card_name'      => 'required',
            'card_number'    => 'required',
            'issuing_date'   => 'required',
            'expiry_date'    => 'required',
            'photo_path'     => 'required',
            'co_name'        => 'required',
            'co_card_number' => 'required',
            'co_photo_path'  => 'required',
        ];
    }

    public function messages(): array
    {
        $messages = parent::messages();
        return array_merge($messages, [


        ]);
    }
}
