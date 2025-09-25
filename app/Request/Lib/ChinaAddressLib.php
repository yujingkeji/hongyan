<?php

declare(strict_types=1);

namespace App\Request\Lib;


use Hyperf\Validation\Rule;

/** 中国大陆地区发件人地址 验证
 * Class ChinaDaLuSenderRequest
 * @package App\Request
 */
class ChinaAddressLib extends BaseLib
{


    public function rules(): array
    {
        return [
            'zip'         => ['nullable', 'numeric'],
            'name'        => ['required', 'min:2'],
            'country'     => ['required','min:2'],
            'country_id'  => ['required','integer', 'min:1'],
            'province'    => ['required_without:province_id', 'min:2'],
            'province_id' => ['required_without:province', 'integer', 'min:1'],
            'city'        => ['required_without:city_id', 'min:2'],
            'city_id'     => ['required_without:city', 'integer', 'min:0'],
            'district'    => ['min:2'],
            'district_id' => ['integer', 'min:0','nullable'],
            'street'      => ['nullable'],
            'street_id'   => ['integer', 'nullable'],
            'address'     => ['required', 'string', 'min:2'],
            'area_code'   => ['string'],
            'company'     => ['nullable'],
            'phone'       => ['required_without:mobile', 'string', 'min:2'],
            'mobile'      => ['required_without:phone', 'string', 'min:2'],
            'is_save'     => ['integer', Rule::in([0, 1])]
        ];
    }

    //发件人地址
    public function senderMessages(): array
    {
        return array_merge(parent::messages(),
            [
                'zip.numeric'                  => 'sender.zip code must be a number',
                'name.required'                => 'sender.name must be filled out',
                'country.required_without'     => '"sender.Country_id" and "sender.country" must fill in one of them',
                'country_id.required_without'  => '"sender.Country_id" and "sender.country" must fill in one of them',
                'country_id.integer'           => 'Sender.Country_id must be an integer',
                'province.required_without'    => '"sender.province_id" and "sender.province" must fill in one of them',
                'province_id.required_without' => '"sender.province_id" and "sender.province" must fill in one of them',
                'province_id.integer'          => 'sender.province_id must be an integer',
                'city.required_without'        => '"sender.city_id" and "sender.city" must fill in one of them',
                'city_id.required_without'     => '"sender.city_id" and "sender.city" must fill in one of them',
                'city_id.integer'              => 'sender.city_id must be an integer',
                'district_id.integer'          => 'sender.district_id must be an integer',
                'street_id.integer'            => 'sender.street_id must be an integer',
                'address.string'               => 'sender.address must be a string type',
                'phone.required_without'       => '"sender.phone" and "sender.mobile" must fill in one of them',
                'mobile.required_without'      => '"sender.phone" and "sender.mobile" must fill in one of them',
                'is_save.integer'              => 'sender.is_save must be an integer',

            ]);
    }

    //收件人地址
    public function receiverMessages(): array
    {

        return
            [
                'zip.numeric'                  => 'receiver.zip code must be a number',
                'name.required'                => 'receiver.name must be filled out',
                //                'country.required_without'     => '"receiver.Country_id" and "receiver.country" must fill in one of them',
                //                'country_id.required_without'  => '"receiver.Country_id" and "receiver.country" must fill in one of them',
                'country_id.integer'           => 'Sender.Country_id must be an integer',
                'province.required_without'    => '"receiver.province_id" and "receiver.province" must fill in one of them',
                'province_id.required_without' => '"receiver.province_id" and "receiver.province" must fill in one of them',
                'province_id.integer'          => 'receiver.province_id must be an integer',
                'city.required_without'        => '"receiver.city_id" and "receiver.city" must fill in one of them',
                'city_id.required_without'     => '"receiver.city_id" and "receiver.city" must fill in one of them',
                'city_id.integer'              => 'receiver.city_id must be an integer',
                'district_id.integer'          => 'receiver.district_id must be an integer',
                'street_id.integer'            => 'receiver.street_id must be an integer',
                'address.string'               => 'receiver.address must be a string type',
                'phone.required_without'       => '"receiver.phone" and "receiver.mobile" must fill in one of them',
                'mobile.required_without'      => '"receiver.phone" and "receiver.mobile" must fill in one of them',
                'is_save.integer'              => 'receiver.is_save must be an integer',
            ];
    }
}
