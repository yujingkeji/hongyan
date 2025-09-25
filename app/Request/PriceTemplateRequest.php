<?php

declare(strict_types=1);

namespace App\Request;


use App\Rules\RulesUnique;
use App\Service\Cache\BaseCacheService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Validation\Rule;

class PriceTemplateRequest
{
    #[Inject]
    protected BaseCacheService $baseCacheService;

    public function rules(string $scenes, array $params, array $member = []): array
    {
        switch ($scenes) {
            case 'add':
                return [
                    'rules'    => $this->addAndEditRules(params: $params, member: $member),
                    'messages' => $this->addAndEditMessages(params: $params),
                ];
                break;
            case 'edit':
                //TODO 编辑的时候，禁止编辑 发出地、目的地、货币
                /*   unset($priceTemplateUpdate['send_country_id']);//删除发出地
                   unset($priceTemplateUpdate['target_country_id']);//删除目的地
                   unset($priceTemplateUpdate['currency_id']);//删除货币类型*/
                $rules                  = $this->addAndEditRules(params: $params, member: $member);
                $rules['template_id']   = ['required', 'integer', Rule::exists('price_template')->where(function ($query) use ($params, $member) {
                    $query->where('member_uid', '=', $member['uid'])->where('template_id', '=', $params['template_id'])->select(['template_id']);
                })];
                $rules['template_name'] = ['required', 'string', 'min:4', Rule::unique('price_template')->where(function ($query) use ($params, $member) {
                    $query->where('member_uid', '=', $member['uid'])
                        ->where('template_name', '=', $params['template_name'])
                        ->where('template_id', '<>', $params['template_id']);
                })];
                return [
                    'rules'    => $rules,
                    'messages' => $this->addAndEditMessages(params: $params),
                ];
                break;
            case 'query':
                $rules =
                    [
                        'template_id' => ['required', 'integer', Rule::exists('price_template')->where(function ($query) use ($params, $member) {
                            $query->where('uid', '=', $member['uid'])->where('template_id', '=', $params['template_id']);
                        })],

                    ];
                return ['rules' => $rules, 'messages' => $this->addAndEditMessages(params: $params)];
                break;
            case 'versionHandle':
                return [
                    'rules'    => $this->addAndEditVersionRules(params: $params, member: $member),
                    'messages' => $this->addAndEditMessages(params: $params),
                ];
                break;
        }
        return [];
    }

    /**
     * @DOC 新增OR编辑价格模板版本
     * @Name   addAndEditVersionRules
     * @Author wangfei
     * @date   2023/11/2 2023
     * @param array $params
     * @param array $member
     * @return array
     */
    protected function addAndEditVersionRules(array $params, array $member)
    {
        $CountryCodeCache = $this->baseCacheService->CountryCodeCache();
        $CountryCodeCache = array_column($CountryCodeCache, 'country_id');
        $rules            =
            [
                'template_id'                             => ['required', 'integer', Rule::exists('price_template')->where(function ($query) use ($params, $member) {
                    $query->where('member_uid', '=', $member['uid'])->where('template_id', '=', $params['template_id'])->select(['template_id']);
                })],
                'version_id'                              => ['integer', Rule::exists('price_template_version')->where(function ($query) use ($params, $member) {
                    $query->where('version_id', '=', $params['version_id'])->where('template_id', '=', $params['template_id'])->where('member_uid', '=', $member['uid']);
                })],
                'item'                                    => ['array'],
                'item.*.item_id'                          => ['integer'],//区域ID
                'item.*.area'                             => ['required', 'array'],//区域
                //                'item.*.area.*.country'                   => ['required', 'integer', Rule::in($CountryCodeCache)],//国家地区
                'item.*.area.*.province'                  => ['string'],//二级行政区域
                'item.*.area.*.city'                      => ['string'],//三级行政区域
                'item.*.price_item'                       => ['required', 'array'],//区域对应的价格
                'item.*.price_item.*.weight_before_value' => ['required', 'numeric', 'min:0'],//重量最小值 大于等于
                'item.*.price_item.*.weight_end_value'    => ['required', 'numeric', 'min:0'],//规则后值小于
                'item.*.price_item.*.first'               => ['required', 'numeric', 'min:0'],//首重
                'item.*.price_item.*.first_price'         => ['required', 'numeric', 'min:0'],//首重金额
                'item.*.price_item.*.continue'            => ['required', 'numeric', 'min:0'],//续重
                'item.*.price_item.*.continue_price'      => ['required', 'numeric', 'min:0'],//续重金额
                'desc'                                    => ['string'],//版本说明

            ];
        /**
         *  `weight_before_value`:'前规则值 大于等于',
         *  `weight_end_value`:'规则后值小于',
         *  `first`:'首重',
         *  `first_price`:'首重费用',
         *  `continue`:'续重',
         *  `continue_price`:'续重费用',
         */
        return $rules;
    }

    protected function addAndEditRules(array $params, array $member)
    {
        $CountryCurrencyCache = $this->baseCacheService->CountryCurrencyCache();
        $CountryCurrencyCache = array_column($CountryCurrencyCache, 'currency_id');
        $CountryCodeCache     = $this->baseCacheService->CountryCodeCache();
        $CountryCodeCache     = array_column($CountryCodeCache, 'country_id');
        $rules                =
            [

                'template_name' => ['required', 'string', 'min:4', Rule::unique('price_template')->where(function ($query) use ($params, $member) {
                    $query->where('member_uid', '=', $member['uid'])
                        ->where('template_name', '=', $params['template_name']);
                })],
                'currency_id'   => ['required', 'integer', Rule::in($CountryCurrencyCache)],//货币单位

                'send_country_id'                         => ['required', 'integer', Rule::in($CountryCodeCache)],//发出国家
                'target_country_id'                       => ['required', 'integer', Rule::in($CountryCodeCache)],//目的国家
                'item'                                    => ['array'],
                'item.*.item_id'                          => ['integer'],//区域ID
                'item.*.area'                             => ['required', 'array', new RulesUnique('province')],//区域
                //   'item.*.area.*.country'                   => ['required', 'integer', Rule::in($CountryCodeCache)],//国家地区
                'item.*.area.*.province'                  => ['string',],//二级行政区域 //禁止此项重复
                'item.*.area.*.city'                      => ['string'],//三级行政区域
                'item.*.area.*.district'                  => ['string'],//三级行政区域
                'item.*.price_item'                       => ['required', 'array'],//区域对应的价格
                'item.*.price_item.*.weight_before_value' => ['required', 'numeric', 'min:0'],//重量最小值 大于等于
                'item.*.price_item.*.weight_end_value'    => ['required', 'numeric', 'min:0'],//规则后值小于
                'item.*.price_item.*.first'               => ['required', 'numeric', 'min:0'],//首重
                'item.*.price_item.*.first_price'         => ['required', 'numeric', 'min:0'],//首重金额
                'item.*.price_item.*.continue'            => ['required', 'numeric', 'min:0'],//续重
                'item.*.price_item.*.continue_price'      => ['required', 'numeric', 'min:0'],//续重金额
                'desc'                                    => ['string'],//版本说明
                'version_id'                              => ['nullable', 'numeric'],//版本说明

            ];
        /**
         *  `weight_before_value`:'前规则值 大于等于',
         *  `weight_end_value`:'规则后值小于',
         *  `first`:'首重',
         *  `first_price`:'首重费用',
         *  `continue`:'续重',
         *  `continue_price`:'续重费用',
         */
        return $rules;
    }

    protected function addAndEditMessages(array $params = [])
    {
        $messages =
            [
                'template_id.required'   => '价格模板ID必填',
                'template_id.exists'     => '当前价格模板不存在禁止修改',
                'version_id.exists'      => '当前价格模板下的不存在当前版本、禁止更新',
                'template_name.required' => '价格模板名称必填',
                'template_name.min'      => '价格模板名称不能少于4个字符',
                'template_name.unique'   => '当前名称已存在、禁止重复',
                'flow_id.exists'         => '当前用户下的流程不存在、请确认选择流程',
            ];
        return $messages;
    }


}
