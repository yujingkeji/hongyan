<?php

namespace App\Controller\Home\Record;

use App\Common\Lib\Arr;
use App\Controller\Home\HomeBaseController;
use App\Exception\HomeException;
use App\JsonRpc\RecordServiceInterface;
use App\Model\CountryAreaModel;
use App\Model\GoodsConvertRecordModel;
use App\Model\MemberThirdConfigureItemModel;
use App\Model\MemberThirdConfigureModel;
use App\Request\LibValidation;
use App\Service\Cache\BaseCacheService;
use App\Service\GoodsService;
use App\Service\OrdersCreateCheckService;
use App\Service\ThirdService\Third\record;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: "record")]
class RecordController extends HomeBaseController
{

    #[Inject]
    protected OrdersCreateCheckService $orderCheck;

    /**
     * @DOC 父级查询
     */
    public function parentData($parent_agent_uid)
    {
        $where           = [
            'third_id'   => 131,
            'status'     => 1,
            'member_uid' => $parent_agent_uid,
        ];
        $member_third_id = MemberThirdConfigureModel::where($where)->value('member_third_id');
        if (empty($member_third_id)) {
            throw new HomeException('当前用户未配置备案设置，操作错误');
        }
        $where = [
            'member_third_id' => $member_third_id,
            'member_uid'      => $parent_agent_uid,
        ];
        $items = MemberThirdConfigureItemModel::where($where)->get()->toArray();
        if (empty($items)) {
            throw new HomeException('当前用户未配置备案设置，操作错误');
        }
        $recordData = [];
        foreach ($items as $v) {
            $recordData[$v['field']] = $v['field_value'];
        }
        return $recordData;
    }

    /**
     * @DOC 引用备案
     */
    #[RequestMapping(path: 'rest', methods: 'post')]
    public function rest(RequestInterface $request): ResponseInterface
    {
        $param = $request->all();
        $data  = \Hyperf\Support\make(GoodsService::class)->categoryRecordLists($param);
        return $this->response->json($data);
    }

    /**
     * @DOC 备案详情
     */
    #[RequestMapping(path: 'info', methods: 'post')]
    public function info(RequestInterface $request)
    {
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($request->all(),
            [
                'record_base_id' => ['required', 'integer'],
            ]
        );
        $thirdData     = \Hyperf\Support\make(RecordServiceInterface::class)->batchByBaseId([$param['record_base_id']]);
        if (is_array($thirdData) && isset($thirdData['code']) && $thirdData['code'] == 200) {
            // 处理详情数据
            $thirdData['data']        = $thirdData['data'][0] ?? [];
            $BaseCacheService         = \Hyperf\Support\make(BaseCacheService::class);
            $recordCategoryGoodsCache = $BaseCacheService->recordCategoryGoodsCache();
            $handleGoodsCategory      = Arr::handleGoodsCategory($recordCategoryGoodsCache, $thirdData['data']['category_item_id']);
            unset($handleGoodsCategory['data']);
            $CountryAreaCache       = $BaseCacheService->CountryAreaCache();
            $data                   = $thirdData['data'];
            $data['base_id']        = 0;
            $data['bc_checked']     = isset($thirdData['data']['bc']['record_base_id']) ? 1 : 0;
            $data['cc_checked']     = isset($thirdData['data']['cc']['record_base_id']) ? 1 : 0;
            $data['category']       = $handleGoodsCategory;
            $data['goods_base_id']  = 0;
            $data['goods_md5']      = '';
            $data['send_country']   = isset($CountryAreaCache['name'][$thirdData['data']['send_country']]) ?: 0;
            $data['origin_country'] = isset($CountryAreaCache['name'][$thirdData['data']['origin_country']]) ?: 0;

            return $this->response->json(['code' => 200, 'msg' => '查询成功', 'data' => $data]);
        }
        return $this->response->json(['code' => 201, 'msg' => '查询失败', 'data' => $thirdData]);
    }

    /**
     * @DOC 根据商家编码查询信息
     */
    #[RequestMapping(path: 'goods/code', methods: 'post')]
    public function goodsCode(RequestInterface $request)
    {
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $params        = $LibValidation->validate($request->all(),
            [
                'sku_code' => ['required'],
            ],
            [
                'sku_code.required' => '缺少商家编码'
            ]
        );
        $member        = $request->UserInfo;
        $item[]        = ['item_code' => $params['sku_code']];

        $baseCacheService         = \Hyperf\Support\make(BaseCacheService::class);
        $recordCategoryGoodsCache = $baseCacheService->recordCategoryGoodsCache();
        // 查询本地数据
        $skuCodeArr = $this->orderCheck->skuCode($item, $member['uid']);
        if (!empty($skuCodeArr)) {
            $data = json_decode(json_encode($skuCodeArr[$params['sku_code']] ?? []), true);
            // 处理分类
            if (!empty($data['category_item_id'])) {
                $category                          = Arr::handleGoodsCategory($recordCategoryGoodsCache, $data['category_item_id']);
                $data['category']['category_name'] = '';
                $data['category']['category_id']   = 0;
                if (!empty($category)) {
                    $data['category']['category_name'] = $category['string'];
                    $data['category']['category_id']   = end($category['data'])['id'] ?? 0;
                }
            }
            return $this->response->json(['code' => 200, 'msg' => '查找成功', 'data' => $data]);
        }
        // 查询远程数据
        $convertRecordArr = $this->orderCheck->handleConvertRecord(itemArr: $item, member: $member);
        $data             = $convertRecordArr[$params['sku_code']] ?? [];
        if (!empty($data['tax_number'])) {
            $category                          = $recordCategoryGoodsCache[$data['tax_number']] ?? [];
            $data['category']['category_name'] = '';
            $data['category']['category_id']   = 0;
            if (!empty($category)) {
                $data['category']['category_name'] = $category['parent_string'];
                $data['category']['category_id']   = $category['id'];
            }
        }
        return $this->response->json(['code' => 200, 'msg' => '查找成功', 'data' => $data]);
    }

    /**
     * @DOC 商品栏目只显示关联过的远程商品
     */
    #[RequestMapping(path: 'history/goods', methods: 'post')]
    public function historyGoods(RequestInterface $request)
    {
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($request->all(),
            [
                'sku_code' => ['nullable', 'string'],
                'page'     => ['required', 'integer'],
                'limit'    => ['required', 'integer'],
            ]
        );
        $member        = $request->UserInfo;
        $where         = [];
        if (Arr::hasArr($param, 'sku_code')) {
            $where[] = ['item_code', '=', $param['sku_code']];
        }
        $history = GoodsConvertRecordModel::where('member_uid', $member['uid'])
            ->where($where)
            ->select(['record_sku_id', 'item_code'])
            ->paginate($param['limit']);
        // 取出 所有record信息
        $recordIds = $history->items();
        $skuIDs    = array_column($recordIds, 'record_sku_id');
        $itemCode  = array_column($recordIds, 'item_code', 'record_sku_id');
        $skuIDs    = array_unique($skuIDs);
        // 获取远程备案信息
        $record = \Hyperf\Support\make(RecordServiceInterface::class)->batchBySkuId($skuIDs);
        $data   = [];
        if ($record['code'] == 200 && !empty($record['data'])) {
            // 获取分类信息
            $baseCacheService         = \Hyperf\Support\make(BaseCacheService::class);
            $recordCategoryGoodsCache = $baseCacheService->recordCategoryGoodsCache();
            $recordCategoryGoodsCache = array_column($recordCategoryGoodsCache, null, 'tax_code');
            foreach ($record['data'] as $item) {
                $data[] = [
                    'barcode'        => $item['sku'][0]['barcode'] ?? '',
                    'spec'           => $item['sku'][0]['spec'] ?? '',
                    'spec_unit'      => $item['sku'][0]['spec_unit'] ?? '',
                    'sku_code'       => $itemCode[$item['sku'][0]['sku_id']] ?? '',
                    'record_sku_id'  => $item['sku'][0]['sku_id'] ?? 0,
                    'record_base_id' => $item['record_base_id'],
                    'sku_id'         => 0,
                    'goods'          => [
                        'cc_checked' => empty($item['cc']) ? 0 : 1,
                        'bc_checked' => empty($item['bc']) ? 0 : 1,
                        'goods_name' => $item['goods_name'],
                        'brand_name' => $item['brand_name'],
                        'goods_code' => $item['goods_code'],
                        'buy_link'   => $item['buy_link'],
                        'category'   => [
                            'parent_string' => $recordCategoryGoodsCache[$item['cc']['tax_number']]['parent_string'] ?? []
                        ],
                    ],
                ];
            }

        }
        return $this->response->json(['code' => 200, 'msg' => '查找成功', 'data' => ['total' => $history->total(), 'data' => $data]]);
    }

    /**
     * @DOC 更改关联商品编码
     */
    #[RequestMapping(path: 'goods/code/edit', methods: 'post')]
    public function goodsCodeEdit(RequestInterface $request)
    {
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $params        = $LibValidation->validate($request->all(),
            [
                'record_sku_id' => ['required', 'integer'],
                'sku_code'      => ['required'],
            ],
            [
                'record_sku_id.required' => '缺少备案id',
                'sku_code.required'      => '缺少商家编码'
            ]
        );
        $member        = $request->UserInfo;
        $record        = GoodsConvertRecordModel::where('member_uid', $member['uid'])
            ->where('record_sku_id', $params['record_sku_id'])
            ->first();
        if (!$record) {
            return $this->response->json(['code' => 201, 'msg' => '备案信息不存在']);
        }
        // 查询当前编码唯一
        $check = GoodsConvertRecordModel::where('member_uid', $member['uid'])
            ->where('record_sku_id', '<>', $params['record_sku_id'])
            ->where('item_code', $params['sku_code'])
            ->exists();
        if ($check) {
            return $this->response->json(['code' => 201, 'msg' => '编码已存在']);
        }

        if ($record->item_code != $params['sku_code']) {
            GoodsConvertRecordModel::where('record_sku_id', $params['record_sku_id'])
                ->where('member_uid', $member['uid'])->update(['item_code' => $params['sku_code']]);
            return $this->response->json(['code' => 200, 'msg' => '更新成功']);
        }
        return $this->response->json(['code' => 201, 'msg' => '编码未改变']);
    }


    /**
     * @DOC 新增官方备案库
     */
    #[RequestMapping(path: 'add', methods: 'post')]
    public function add(RequestInterface $request)
    {
        $member     = $request->UserInfo;
        $recordData = $this->parentData($member['parent_agent_uid']);
        if (empty($recordData['app_key'])) {
            return $this->response->json(['code' => 201, 'msg' => '代理备案未授权第三方，禁止添加远程备案库']);
        }
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($request->all(),
            [
                'source_base_id'       => ['required', 'integer'],
                'category_item_id'     => ['required', 'integer'],
                'goods_code'           => ['string', 'max:255', 'nullable'],
                'goods_name'           => ['required', 'string', 'max:255'],
                'goods_name_en'        => ['string', 'max:255', 'nullable'],
                'short_name'           => ['required', 'string', 'max:255'],
                'brand_id'             => ['integer'],
                'brand_name'           => ['string', 'max:100'],
                'brand_en'             => ['string', 'max:100'],
                'origin_country'       => ['integer'],//原产地（国家地区）
                'send_country'         => ['integer'], //发出港口或者国家
                'buy_link'             => ['string', 'url', 'nullable'],
                'record_status'        => ['required', 'integer', Rule::in([0, 1, 2, 3, 4])],
                'record_info'          => ['string', 'nullable'], //审核备注
                'goods_info'           => ['string', 'nullable'], //商品备注
                'sku'                  => ['required', 'array'],
                'sku.*.source_sku_id'  => ['required', 'integer'],
                'sku.*.sku_code'       => ['required', 'string'],
                'sku.*.price'          => ['required', 'numeric'],
                'sku.*.price_unit'     => ['required', 'string'],
                'sku.*.inner_quantity' => ['required', 'numeric'],//内置数量
                'sku.*.spec'           => ['required', 'string'],//规格、含量、容量等
                'sku.*.spec_unit'      => ['required', 'string'],//规格、含量、容量单位
                'sku.*.component'      => ['string', 'nullable'],//成分
                'sku.*.model_number'   => ['string', 'nullable'], //型号
                'sku.*.barcode'        => ['required', 'string'], //条码
                'sku.*.gross_weight'   => ['numeric'], //毛重
                'sku.*.suttle_weigh'   => ['required', 'numeric'], //净重
                'cc'                   => ['array'],
                'cc.tax_number'        => ['required_with:cc', 'string'],//行邮税号
                'cc.tax_rate'          => ['required_with:cc', 'integer'],//行邮税率
                'cc.dutiable_value'    => ['required_with:cc', 'numeric'],//完税价格
                'bc'                   => ['array'],
                'bc.hs_code'           => ['required_with:bc', 'string'],//海关编码
                'bc.tax_unit'          => ['required_with:bc', 'string'],//完税单位
                'bc.tax_rate'          => ['required_with:bc', 'numeric'],//商品税率(BC)
                'bc.vat_rate'          => ['numeric'],  //商品税率(BC)
                'bc.suit_country'      => ['integer'],   //适用标准（国别）：当前商品是否适用当前国家的标准。
                'bc.first_unit'        => ['string', 'nullable'],   //第一法定单位
                'bc.second_unit'       => ['string', 'nullable'],   //第二法定单位
                'bc.first_quantity'    => ['numeric'],  //第一法定数量
                'bc.second_quantity'   => ['numeric'],  //第二法定数量
                'bc.supplier'          => ['string', 'nullable'],   //供应商
                'bc.component'         => ['string', 'nullable'],   //商品成分
                'bc.desc'              => ['string', 'nullable'],   //商品描叙
                'bc.goods_function'    => ['string', 'nullable'],   //商品功能
                'bc.goods_purpose'     => ['string', 'nullable'],   //商品用途
            ]
        );
        // 验证和过滤输入参数
        $validatedParams = [];
        if (isset($param['origin_country']) && is_numeric($param['origin_country'])) {
            $validatedParams['origin_country'] = intval($param['origin_country']);
        }
        if (isset($param['send_country']) && is_numeric($param['send_country'])) {
            $validatedParams['send_country'] = intval($param['send_country']);
        }
        if (isset($param['bc']['suit_country']) && is_numeric($param['bc']['suit_country'])) {
            $validatedParams['bc']['suit_country'] = intval($param['bc']['suit_country']);
        }

        $ids = array_values(array_filter($validatedParams, function ($value) {
            return !empty($value);
        }));

        if (!empty($ids)) {
            try {
                // 查询数据库并创建 ID 到名称的映射表
                $data        = CountryAreaModel::whereIn('id', $ids)->get()->toArray();
                $idToNameMap = array_column($data, 'name', 'id');

                // 更新参数中的国家名称
                if (isset($validatedParams['origin_country'])) {
                    $param['origin_country'] = $idToNameMap[$validatedParams['origin_country']] ?? null;
                }
                if (isset($validatedParams['send_country'])) {
                    $param['send_country'] = $idToNameMap[$validatedParams['send_country']] ?? null;
                }
                if (isset($validatedParams['bc']['suit_country'])) {
                    $param['bc']['suit_country'] = $idToNameMap[$validatedParams['bc']['suit_country']] ?? null;
                }

                $interface = 'App\\Service\\ThirdService\\Interfaces\\record\\Parameter';
                # 实例化 接口类
                $third  = \Hyperf\Support\make(record::class, [['app_key' => $recordData['app_key'] ?? 0, 'app_secret' => $recordData['app_secret'] ?? 0]]);
                $result = $third->interfaces(['interface' => $interface, 'method' => 'goods.record.add'], $param);

                return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $result]);
            } catch (\Exception $e) {
                return $this->response->json(['code' => 201, 'msg' => '错误' . $e->getMessage()]);
            }
        }
    }


}
