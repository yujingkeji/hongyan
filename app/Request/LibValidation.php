<?php

declare(strict_types=1);

namespace App\Request;

use App\Common\Lib\Arr;
use App\Common\Lib\Crypt;
use App\Exception\HomeException;
use App\Model\CountryAreaModel;
use App\Request\Lib\BaseLib;
use App\Request\Lib\ChinaAddressLib;
use Hyperf\Codec\Json;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\Rule;


class LibValidation extends BaseLib
{
    protected int $country_id = 1;
    protected array $member;

    #[Inject]
    protected Crypt $crypt;
    #[Inject]
    protected ?ValidatorFactoryInterface $validationFactory = null;

    public function __construct(array $member = [])
    {
        $this->member = $member; //会员信息
    }

    /**
     * @DOC
     * @Name   validate
     * @Author wangfei
     * @date   2024/4/15 2024
     * @param array $params 检测参数
     * @param array $rules 检测规则
     * @param array $messages 错误信息
     * @param array $customAttributes
     * @param bool $errorsAll 是否返回全部错误信息
     * @return array
     */
    public function validate(array $params, array $rules, array $messages = [], array $customAttributes = [], bool $errorsAll = false): array
    {
        $messages  = array_merge($this->messages(), $messages);
        $Validator = $this->validationFactory->make(
            $params,
            $rules,
            $messages,
            $customAttributes
        );
        if ($Validator->fails()) {

            if ($errorsAll === true) {
                //全部错误信息
                throw new HomeException(Json::encode($Validator->errors()->toArray()), 201);
            } else {
                throw new HomeException($Validator->errors()->first(), 201);
            }
        }

        return $Validator->validated();
    }

    /**
     * @DOC  加密地址联系人信息
     * @Name   handleEncrypt
     * @Author wangfei
     * @date   2023-09-11 2023
     * @param $Address
     * @return mixed
     * @throws \Exception
     */
    protected function handleEncrypt($Address): mixed
    {
        $Address['name']   = Arr::hasArr($Address, 'name') ? base64_encode($this->crypt->encrypt($Address["name"])) : "";
        $Address['phone']  = Arr::hasArr($Address, 'phone') ? base64_encode($this->crypt->encrypt($Address["phone"])) : "";
        $Address['mobile'] = Arr::hasArr($Address, 'mobile') ? base64_encode($this->crypt->encrypt($Address["mobile"])) : "";
        return $Address;
    }

    /**
     * @DOC 发件人地址校验
     * @Name   sender
     * @Author wangfei
     * @date   2023-09-11 2023
     * @param array $LineData
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function sender(array $LineData, array $params): array
    {
        $messages = [];
        $LineSend = $LineData['send'];
        if ($LineData['send_country_id'] == $this->country_id) {
            $ChinaAddressLib = \Hyperf\Support\make(ChinaAddressLib::class);
            $rules           = $ChinaAddressLib->rules();
            $messages        = $ChinaAddressLib->senderMessages();
        } else {
            $rules = $this->addressRule();
        }
        $params = $this->validate(params: $params, rules: $rules, messages: $messages);
        //加密联系人
        $params                   = $this->handleEncrypt($params);
        $params['corporate_name'] = $params['company'] ?? '';
        unset($params['company']);
        $params           = $this->handleIdAddress(address: $params);
        $result['params'] = $params;
        $result['md5']    = $this->md5ToAddress($params);
        return $result;
    }


    /**
     * @DOC 收件人地址校验
     * @Name   receiver
     * @Author wangfei
     * @date   2023-09-11 2023
     * @param array $LineData
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function receiver(array $LineData, array $params): array
    {
        $messages = [];
        if ($LineData['target_country_id'] == $this->country_id) {
            $ChinaAddressLib = \Hyperf\Support\make(ChinaAddressLib::class);
            $rules           = $ChinaAddressLib->rules();
            $messages        = $ChinaAddressLib->receiverMessages();
        } else {
            $rules = $this->addressRule();
        }
        $params['country_id'] = $params['country_id'];
        $params['country']    = $params['country'];
        $params               = $this->validate(params: $params, rules: $rules, messages: $messages);
        $params               = $this->handleEncrypt($params);
        if (Arr::hasArr($LineData, 'target_country_id')) {
            $params['country_id'] = $LineData['target_country_id'];
        }
        if (Arr::hasArr($LineData, 'target_country')) {
            $params['country'] = $LineData['target_country'];
        }
        $params['corporate_name'] = $params['company'] ?? '';
        unset($params['company']);
        $params           = $this->handleIdAddress(address: $params);
        $result['params'] = $params;
        $result['md5']    = $this->md5ToAddress($params);
        return $result;
    }

    /**
     * @DOC  整理地址，匹配上ID
     * @Name   handleIdAddress
     * @Author wangfei
     * @date   2023-09-11 2023
     * @param array $address
     * @param array $areaIdArr
     * @return array
     */
    protected function handleIdAddress(array $address, array $areaIdArr = []): array
    {

        if (Arr::hasArr($address, 'province_id')) {
            $areaIdArr[] = $address['province_id'];
        }
        if (Arr::hasArr($address, 'city_id')) {
            $areaIdArr[] = $address['city_id'];
        }
        if (Arr::hasArr($address, 'district_id')) {
            $areaIdArr[] = $address['district_id'];
        }
        if (Arr::hasArr($address, 'street_id')) {
            $areaIdArr[] = $address['street_id'];
        }
        if (!empty($areaIdArr)) {
            $countryArea = CountryAreaModel::query()->whereIn('id', $areaIdArr)->select(['id', 'name', 'name_en', 'name_zh'])->get()->toArray();
            $countryArea = array_column($countryArea, null, 'id');
            if (isset($address['province_id']) && isset($countryArea[$address['province_id']])) {
                $address['province'] = $countryArea[$address['province_id']]['name'];
            }
            if (isset($address['city_id']) && isset($countryArea[$address['city_id']])) {
                $address['city'] = $countryArea[$address['city_id']]['name'];
            }
            if (isset($address['district_id']) && isset($countryArea[$address['district_id']])) {
                $address['district'] = $countryArea[$address['district_id']]['name'];
            }
            if (isset($address['street_id']) && isset($countryArea[$address['street_id']])) {
                $address['street'] = $countryArea[$address['street_id']]['name'];
            }
            if (isset($address['street_id']) && !$address['street_id']) {
                $address['street'] = '';
            }
        }
        unset($areaIdArr);
        return $address;

    }

    /**
     * @DOC 地址转MD5
     * @Name   md5ToAddress
     * @Author wangfei
     * @date   2023-09-11 2023
     * @param $address
     * @return string
     */
    protected function md5ToAddress($address)
    {
        $keys = ['country', 'name', 'province', 'city', 'district', 'street', 'address', 'phone', 'mobile', 'member_uid', 'parent_join_uid', 'parent_agent_uid'];
        foreach ($address as $key => $val) {
            if (!in_array($key, $keys)) {
                unset($address[$key]);
            }
        }
        $Str = Arr::hasSortString($address);
        unset($address);
        return md5($Str);

    }


    /**
     * @DOC  发件人地址规则
     * @Name   addressRule
     * @Author wangfei
     * @date   2023-09-11 2023
     * @return array
     */
    protected function addressRule()
    {
        return
            [
                'zip'         => ['numeric'],
                'name'        => ['required', 'min:2'],
                'country'     => ['string', 'min:2'],
                'country_id'  => ['integer', 'min:1'],
                'province'    => ['string', 'min:2'],
                'province_id' => ['integer', 'min:0'],
                'city'        => ['string', 'min:2'],
                'city_id'     => ['integer', 'min:0'],
                'district'    => ['nullable'],
                'district_id' => ['integer', 'min:0'],
                'street'      => ['nullable'],
                'street_id'   => ['nullable', 'integer'],
                'address'     => ['required', 'string', 'min:2'],
                'area_code'   => ['string'],
                'phone'       => ['required_without:mobile', 'string', 'min:2'],
                'mobile'      => ['required_without:phone', 'string', 'min:2'],
                'is_save'     => ['integer', Rule::in([0, 1])]
            ];
    }
}
