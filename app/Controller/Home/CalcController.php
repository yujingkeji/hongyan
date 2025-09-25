<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Controller\Home;

use App\Common\Lib\Arr;
use App\Exception\HomeException;
use App\Model\OrderPaymentModel;
use App\Request\LibValidation;
use App\Service\Cache\BaseCacheService;
use App\Service\CalcService;
use App\Service\LineProductCalcService;
use App\Service\OrderNoteService;
use App\Service\ParcelWeightCalcService;
use App\Service\ParcelPaymentService;
use App\Service\PriceVersionCalcService;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Redis\Redis;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\Rule;

#[Controller(prefix: 'calc')]
class CalcController extends HomeBaseController
{
    protected $agentPlatformCache;
    #[Inject]
    protected ValidatorFactoryInterface $validationFactory;

    #[Inject]
    protected BaseCacheService $baseCacheService;

    #[RequestMapping(path: '', methods: 'get,post')]
    public function index(RequestInterface $request)
    {
        $member     = $request->UserInfo;
        $LineCache  = $this->baseCacheService->LineCache();
        $LineIdData = array_column($LineCache, 'line_id');
        $param      = $this->request->all();
        $param      = make(LibValidation::class)->validate(
            $param,
            [
                'line_id'                 => ['required', 'numeric', Rule::in($LineIdData)],
                'weight'                  => 'required|numeric',
                'product_id'              => 'numeric', //产品
                'province_id'             => ['numeric'],
                'city_id'                 => ['numeric'],
                'item'                    => ['array'],
                'item.*.item_num'         => ['integer'],
                'item.*.sku_id'           => ['integer'],
                'item.*.item_record_sn'   => ['nullable'],
                'item.*.category_item_id' => ['required_without:item.*.item_sku_name', 'integer'],
                'item.*.item_sku_name'    => ['required_without:item.*.category_item_id', 'string'],
            ],
            [
                'line_id.required'                         => 'line_id  must be required',
                'line_id.numeric'                          => 'line_id must be numeric',
                'line_id.in'                               => 'line_id not in [' . implode(',', $LineIdData) . ']',
                'product_id.numeric'                       => 'product_id must be numeric',
                'weight.required'                          => 'weight  must be required',
                'weight.numeric'                           => 'weight must be numeric',
                'province_id.numeric'                      => 'province_id must be numeric',
                'city_id.numeric'                          => 'city_id must be numeric',
                'item.*.category_item_id.required_without' => '请选择商品分类',
                'item.*.item_sku_name.required_without'    => '请输入商品分类名称',
                'item.*.category_item_id.integer'          => '请选择商品分类',
                'item.*.item_sku_name.string'              => '请输入商品分类名称',
            ]
        );


        $province_id = Arr::hasArr($param, 'province_id') ? (int)$param['province_id'] : 0;
        $city_id     = Arr::hasArr($param, 'city_id') ? (int)$param['city_id'] : 0;
        $weight      = Arr::hasArr($param, 'weight') ? $param['weight'] : 1;
        $CalcFreight = [];
        $product_id  = Arr::hasArr($param, 'product_id') ? $param['product_id'] : 0;
        switch ($member['role_id']) {
            default:
            case 1:
            case 2:
                $member['member_uid']       = 0;
                $member['parent_join_uid']  = 0;
                $member['parent_agent_uid'] = $member['parent_agent_uid'];
                $LineProductCalcService     = \Hyperf\Support\make(LineProductCalcService::class, [$param['line_id'], $member, $product_id]);
                $CalcFreight                = $LineProductCalcService->platformPriceCalc(weight: $weight, provinceId: $province_id, cityId: $city_id);
                break;
            case 3:
                $member['member_uid']       = $member['uid'];
                $member['parent_join_uid']  = 0;
                $member['parent_agent_uid'] = $member['parent_agent_uid'];
                $LineProductCalcService     = \Hyperf\Support\make(LineProductCalcService::class, [$param['line_id'], $member, $product_id]);
                $CalcFreight                = $LineProductCalcService->joinPriceCalc(weight: $weight, provinceId: $province_id, cityId: $city_id, use_member_uid: $member['uid']);
                break;
                break;
            case 4:
            case 5:
                $member['member_uid']       = $member['uid'];
                $member['parent_join_uid']  = $member['parent_join_uid'];
                $member['parent_agent_uid'] = $member['parent_agent_uid'];
                $LineProductCalcService     = \Hyperf\Support\make(LineProductCalcService::class, [$param['line_id'], $member, $product_id]);
                $CalcFreight                = $LineProductCalcService->memberPriceCalc(weight: $weight, provinceId: $province_id, cityId: $city_id, use_member_uid: $member['uid'], use_join_uid: $member['parent_join_uid']);

                break;
        }

        // 处理禁止到达
        if (!empty($param['item'])) {
            $CalcFreight = (\Hyperf\Support\make(OrderNoteService::class))->makeOrderBatchAnalysis($CalcFreight, $param, $member);
        }

        $result['code'] = 200;
        $result['data'] = $CalcFreight;

        return $this->response->json($result);
    }

    #[Inject]
    protected Redis $redis;

    //费用计算
    #[RequestMapping(path: 'orders', methods: 'get,post')]
    public function orders(RequestInterface $request)
    {
        $result['code']       = 200;
        $result['msg']        = '操作成功';
        $params               = $request->all();
        $params               = make(LibValidation::class)->validate(
            $params,
            [
                'coupons_code'   => ['string'], //优惠券code
                'product_id'     => ['string'], //产品ID,一般情况不用传入
                'order_sys_sn'   => ['required', 'array'],
                'order_sys_sn.*' => ['required', 'string', 'min:10'],
            ],
            [
                'order_sys_sn.required' => 'order_sys_sn  must be required',
                'order_sys_sn.array'    => 'order_sys_sn  must be required',
                'order_sys_sn.*.string' => 'order_sys_sn.*.min  must be string',
                'order_sys_sn.*.min'    => 'order_sys_sn.*.min size of :attribute must be :min',
            ]
        );
        $member               = $request->UserInfo;
        $member['member_uid'] = $member['uid'];
        switch ($member['role_id']) {
            case 1:
            case 2:
            case 3:
            default:
                throw new HomeException("平台代理、加盟商禁止访问。");
                break;
            case 4:
            case 5:

                break;
        }
        if (Arr::hasArr($params, 'order_sys_sn')) {
            $logger                  = $this->container->get(LoggerFactory::class)->get('log', 'AsyncSupplementWeightCalcProcess');
            $parcelWeightCalcService = \Hyperf\Support\make(ParcelWeightCalcService::class, [$logger, $member]);
            $result                  = $parcelWeightCalcService->orderToParcelCalc($params['order_sys_sn'], $member, $params);
            $result['data']          = current($result['data']);
        }

        return $this->response->json($result);
    }

    /**
     * @DOC  确认支付下单
     */
    #[RequestMapping(path: 'orders/confirmPay', methods: 'get,post')]
    public function confirmPay(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '操作失败';
        $param          = $request->all();
        $member         = $request->UserInfo;
        $type           = 2;
        if (Arr::hasArr($param, 'type')) {
            $type = 1;
        }
        $result = (new CalcService)->packPay($param, $member, $type);
        return $this->response->json($result);
    }


    /**
     * @DOC  取消支付
     * @Name   cancelPay
     * @Author wangfei
     * @date   2023-08-26 2023
     * @param RequestInterface $request
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    #[RequestMapping(path: 'orders/cancelPay', methods: 'get,post')]
    public function cancelPay(RequestInterface $request)
    {
        throw new HomeException("功能已取消");
        $result['code'] = 200;
        $result['msg']  = '支付单补存在、或者取消失败';
        $param          = $request->all();
        $member         = $request->UserInfo;
        $validator      = $this->validationFactory->make(
            $param,
            [
                'payment_sn'   => ['required', 'array'],
                'payment_sn.*' => ['required', 'string', 'min:10'],
            ],
            [
                'payment_sn.required' => 'order_sys_sn  must be required',
                'payment_sn.array'    => 'order_sys_sn  must be required',
                'payment_sn.*.string' => 'order_sys_sn.*.min  must be string',
                'payment_sn.*.min'    => 'order_sys_sn.*.min size of :attribute must be :min',
            ]
        );
        if ($validator->fails()) {
            throw new HomeException($validator->errors()->first(), 201);
        }

        switch ($member['role_id']) {
            case 1:
            case 2:
            case 3:
            default:
                throw new HomeException("平台代理、加盟商禁止访问。");
                break;
            case 4:
            case 5:

                break;
        }
        $params = $validator->validated();
        if (Arr::hasArr($params, 'payment_sn')) {
            $where['child_uid']  = $member['child_uid'];
            $where['member_uid'] = $member['uid'];
            $paymentSnData       = $params['payment_sn'];
            $OrderPaymentDb      = OrderPaymentModel::query()
                ->with(['cost_member' => function ($query) {
                    $query
                        ->with(['parcel' => function ($query) {
                            $query->select(['transport_sn', 'order_sys_sn']);
                        }])
                        ->select(['payment_sn', 'order_sys_sn']);
                }])
                ->whereIn('payment_sn', $paymentSnData)
                ->where('member_uid', '=', $member['uid'])
                ->where('parent_agent_uid', '=', $member['parent_agent_uid'])
                ->whereIn('payment_status', [0, 1, 2, 3])
                ->get()->toArray();

            if (empty($OrderPaymentDb)) {
                throw new HomeException('当前支付订单不存在或已支付完成，禁止取消');
            }
            $ParcelData      = [];
            $hasDelPaySnData = [];
            foreach ($OrderPaymentDb as $key => $item) {
                if (Arr::hasArr($item, 'cost_member')) {
                    foreach ($item['cost_member'] as $k => $parcels) {
                        if (Arr::hasArr($parcels, 'parcel')) {
                            $parcel               = $parcels['parcel'];
                            $parcel['payment_sn'] = $parcels['payment_sn'];
                            array_push($ParcelData, $parcel);
                        }
                    }
                }
            }
            if (!empty($ParcelData)) {
                throw new HomeException(implode(',', $ParcelData) . ' 已转包，禁止取消');
            }
            Db::beginTransaction();
            try {
                Db::table("order_payment")->whereIn('payment_sn', $paymentSnData)->where('member_uid', '=', $member['uid'])
                    ->whereIn('payment_status', [0, 1, 2, 3])
                    ->delete();
                Db::table("order_cost")->whereIn('payment_sn', $paymentSnData)->where('member_uid', '=', $member['uid'])->delete();
                Db::table("order_cost_member")->whereIn('payment_sn', $paymentSnData)
                    ->where('member_uid', '=', $member['uid'])->delete();
                Db::table("order_cost_member_item")->whereIn('payment_sn', $paymentSnData)
                    ->where('member_uid', '=', $member['uid'])
                    ->where('payment_status', '=', 0)
                    ->delete();
                Db::table("order_cost_join")->whereIn('payment_sn', $paymentSnData)
                    ->where('member_uid', '=', $member['uid'])->delete();
                Db::table("order_cost_join_item")->whereIn('payment_sn', $paymentSnData)
                    ->where('real_member_uid', '=', $member['uid'])
                    ->where('payment_status', '=', 0)
                    ->delete();
                Db::commit();
                $msg = "取消成功";
            } catch (\Throwable $e) {
                // 回滚事务
                Db::rollback();
                $msg = "取消失败";
            }
            $result['code'] = 200;
            $result['msg']  = $msg;
        }
        return $this->response->json($result);
    }


    /**
     * @DOC    收货补重核算
     * @Name   supplementWeightCalc
     * @Author wangfei
     * @date   2023-07-18 2023
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'supplementWeightCalc', methods: 'get,post')]
    public function supplementWeightCalc(RequestInterface $request)
    {
        $result['code']          = 200;
        $result['msg']           = '查询成功';
        $param                   = $this->request->all();
        $logger                  = $this->container->get(LoggerFactory::class)->get('log', 'AsyncSupplementWeightCalcProcess');
        $parcelWeightCalcService = \Hyperf\Support\make(ParcelWeightCalcService::class, [$logger]);
        $order_sys_sn            = $param['order_sys_sn'];
        $weight                  = $param['weight'];
        $supplementWeightCalc    = $parcelWeightCalcService->supplementWeightCalc($order_sys_sn, $weight, $weight);
        if ($supplementWeightCalc['memberSupplementFee'] > 0) {
            $supplementWeightCalcToSave = $parcelWeightCalcService->supplementWeightCalcToSave($supplementWeightCalc);
            if ($supplementWeightCalcToSave['code'] == 201) {
                throw new HomeException($supplementWeightCalcToSave['msg']);
            }
        }
        $result['data'] = $supplementWeightCalc;
        return $this->response->json($result);

    }

}
