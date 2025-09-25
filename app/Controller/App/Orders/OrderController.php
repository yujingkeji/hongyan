<?php

namespace App\Controller\App\Orders;

use App\Common\Lib\Arr;
use App\Controller\Home\HomeBaseController;
use App\Exception\HomeException;
use App\Model\ChannelImportModel;
use App\Model\ChannelSendModel;
use App\Model\OrderModel;
use App\Model\WarehouseModel;
use App\Request\LibValidation;
use App\Service\AnalyseChannelService;
use App\Service\AuthWayService;
use App\Service\Cache\BaseCacheService;
use App\Service\CalcService;
use App\Service\LineProductCalcService;
use App\Service\OrderNoteService;
use App\Service\OrderParcelLogService;
use App\Service\OrdersService;
use App\Service\ParcelWeightCalcService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Validation\Rule;

#[Controller(prefix: 'app/orders/order')]
class OrderController extends HomeBaseController
{
    //计算订单支付金额、使用优惠的时候，需要去掉优惠
    #[RequestMapping(path: 'pay/calc', methods: 'post')]
    public function ordersPayCalc(RequestInterface $request)
    {
        $result['code'] = 200;
        $result['msg']  = '操作成功';
        $params         = $request->all();
        $params         = make(LibValidation::class)->validate(
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
            $data                    = $parcelWeightCalcService->orderToParcelCalc($params['order_sys_sn'], $member, $params);
            //取消批量计算、批量付款功能，故此处只返回 单个订单数据
            $result['data'] = current($data['data']);
        }

        return $this->response->json($result);
    }

    /**
     * @DOC 获取产品
     */
    #[RequestMapping(path: 'calc', methods: 'post')]
    public function calc(RequestInterface $request)
    {
        $params                 = $this->request->all();
        $LibValidation          = \Hyperf\Support\make(LibValidation::class);
        $LineCache              = \Hyperf\Support\make(BaseCacheService::class)->LineCache();
        $LineIdData             = array_column($LineCache, 'line_id');
        $param                  = $LibValidation->validate($params,
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
                'line_id.required'                         => '线路不存在',
                'line_id.numeric'                          => '线路不存在',
                'line_id.in'                               => '线路不存在',
                'product_id.numeric'                       => '产品选择失败',
                'weight.required'                          => '请填写商品重量',
                'weight.numeric'                           => '商品重量必须为数字',
                'province_id.numeric'                      => '未选择收件省份',
                'city_id.numeric'                          => '未选择收件城市',
                'item.*.category_item_id.required_without' => '请选择商品分类',
                'item.*.item_sku_name.required_without'    => '请输入商品分类名称',
                'item.*.category_item_id.integer'          => '请选择商品分类',
                'item.*.item_sku_name.string'              => '请输入商品分类名称',
            ]
        );
        $member                 = $request->UserInfo;
        $member['member_uid']   = $member['uid'];
        $province_id            = Arr::hasArr($param, 'province_id') ? (int)$param['province_id'] : 0;
        $city_id                = Arr::hasArr($param, 'city_id') ? (int)$param['city_id'] : 0;
        $weight                 = Arr::hasArr($param, 'weight') ? $param['weight'] : 1;
        $product_id             = Arr::hasArr($param, 'product_id') ? $param['product_id'] : 0;
        $LineProductCalcService = \Hyperf\Support\make(LineProductCalcService::class, [$param['line_id'], $member, $product_id]);
        $CalcFreight            = $LineProductCalcService->memberPriceCalc(weight: $weight, provinceId: $province_id, cityId: $city_id, use_member_uid: $member['uid'], use_join_uid: $member['parent_join_uid']);
        // 处理禁止到达
        if (!empty($param['item'])) {
            $CalcFreight = (\Hyperf\Support\make(OrderNoteService::class))->makeOrderBatchAnalysis($CalcFreight, $param, $member);
        }

        $result['code'] = 200;
        $result['data'] = $CalcFreight;

        return $this->response->json($result);
    }


    /**
     * @DOC 制单前检测订单
     */
    #[RequestMapping(path: 'analysis', methods: 'post')]
    public function orderAnalysis(RequestInterface $request)
    {
        $params        = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $member        = $request->UserInfo;
        $params        = $LibValidation->validate($params,
            [
                'line_id'                 => ['required', 'bail', 'integer', Rule::exists('member_line', 'line_id')->where(function ($query) use ($member) {
                    $query->where('uid', '=', $member['parent_agent_uid'])->where('status', 1);
                })],
                'pro_id'                  => ['required', 'integer', Rule::exists('product', 'pro_id')->where(function ($query) use ($params, $member) {
                    $query->where('member_uid', '=', $member['parent_agent_uid'])
                        ->where('line_id', '=', $params['line_id'])
                        ->where('status', 1);
                })],
                'item'                    => ['required', 'array'],
                'item.*.item_num'         => ['required', 'integer'],
                'item.*.sku_id'           => ['integer'],
                'item.*.record_sku_id'    => ['nullable', 'integer'],
                'item.*.item_record_sn'   => ['nullable'],
                'item.*.category_item_id' => ['required_without:item.*.item_sku_name', 'integer'],
                'item.*.item_sku_name'    => ['required_without:item.*.category_item_id', 'string'],
                'receiver'                => ['required', 'array'],
                'sender'                  => ['required', 'array'],
            ],
            [
                'pro_id.required'                          => '请确定物流产品',
                'pro_id.exists'                            => '请选择物流产品',
                'line_id.required'                         => '请确定线路',
                'item.required'                            => '请选择商品',
                'line_id.exists'                           => '您的线路已下架',
                'item.*.category_item_id.required_without' => '请选择商品分类',
                'item.*.item_sku_name.required_without'    => '请输入商品分类名称',
                'item.*.category_item_id.integer'          => '请选择商品分类',
                'item.*.item_sku_name.string'              => '请输入商品分类名称',
            ]
        );

        $params['product_id'] = $params['pro_id'];
        $result               = \Hyperf\Support\make(AnalyseChannelService::class)
            ->makeOrderAnalysis(params: $params, member: $member);
        $result['warehouse']  = [];
        // 查询渠道的仓库地址
        if (!empty($result['channel']['channel_id'])) {
            $channel_id          = $result['channel']['channel_id'];
            $ware_id             = ChannelSendModel::where('channel_id', $channel_id)->value('ware_id');
            $result['warehouse'] = WarehouseModel::where('ware_id', $ware_id)
                ->select(['ware_id', 'ware_code', 'ware_name', 'phone_before', 'contact_phone', 'contact_address'])
                ->first();
            // 处理是否需要实名认证
            $result['card']       = true; // 需要实名
            $result['channel_id'] = $channel_id; // 渠道ID
            $channel_import       = ChannelImportModel::where('channel_id', $channel_id)->value('supervision_id');
            if (in_array($channel_import, [0, 1, 6])) {
                $result['card'] = false;
            }
            unset($channel_id, $ware_id);
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 创建订单
     */
    #[RequestMapping(path: 'make', methods: 'post')]
    public function makeOrder(RequestInterface $request)
    {
        $param                     = $request->all();
        $member                    = $this->request->UserInfo;
        $param['from_platform_id'] = 1796; // 来源订单
        $result                    = \Hyperf\Support\make(OrdersService::class)->makeOrder($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 订单编辑
     */
    #[RequestMapping(path: 'edit', methods: 'post')]
    public function editOrder(RequestInterface $request)
    {
        $param                     = $request->all();
        $userInfo                  = $this->request->UserInfo;
        $param['from_platform_id'] = 1796; // 来源
        $result                    = \Hyperf\Support\make(OrdersService::class)->orderEdit($param, $userInfo);
        return $this->response->json($result);

    }

    /**
     * @DOC 订单获取费用
     */
    #[RequestMapping(path: 'cost', methods: 'post')]
    public function orderCost(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(OrdersService::class)->orderCost($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 订单余额支付
     */
    #[RequestMapping(path: 'pay', methods: 'post')]
    public function orderPay(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $type   = 1;
        $result = (new CalcService())->packPay($param, $member, $type);
        return $this->response->json($result);
    }


    /**
     * @DOC 订单转包
     */
    #[RequestMapping(path: 'waybill', methods: 'post')]
    public function orderToParcelWaybill(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(OrdersService::class)->orderToParcelWaybill($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 订单列表
     */
    #[RequestMapping(path: 'lists', methods: 'get,post')]
    public function orderList(RequestInterface $request)
    {
        $param    = $request->all();
        $useWhere = $this->useWhere();
        $result   = \Hyperf\Support\make(OrdersService::class)->orderParcelLists($param, $useWhere['where'], 'app');
        return $this->response->json($result);
    }

    /**
     * @DOC 订单详情
     */
    #[RequestMapping(path: 'details', methods: 'get,post')]
    public function details(RequestInterface $request, OrdersService $ordersService)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = $ordersService->OrderDetail($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 订单的收发件人地址信息
     */
    #[RequestMapping(path: 'address', methods: 'post')]
    public function address(RequestInterface $request, OrdersService $ordersService)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = $ordersService->address($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 提交实名认证信息
     */
    #[RequestMapping(path: 'identity', methods: 'get,post')]
    public function identity(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '绑定失败';
        $member         = $request->UserInfo;
        $params         = $request->all();
        // 提交认证
        $result = (new AuthWayService())->apply($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 订单取消
     */
    #[RequestMapping(path: 'cancel', methods: 'post')]
    public function cancel(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(OrdersService::class)->cancel($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 订单删除
     */
    #[RequestMapping(path: 'del', methods: 'post')]
    public function del(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(OrdersService::class)->del($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 订单自检
     */
    #[RequestMapping(path: 'analyse', methods: 'post')]
    public function analyse(RequestInterface $request)
    {
        $param  = $request->all();
        $result = \Hyperf\Support\make(OrdersService::class)->analyse($param);
        return $this->response->json($result);
    }

    /**
     * @DOC 订单日志
     */
    #[RequestMapping(path: 'log', methods: 'post')]
    public function log(RequestInterface $request)
    {
        $params        = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'order_sys_sn' => ['required_without:transport_sn'],
                'transport_sn' => ['required_without:order_sys_sn'],
            ],
            [
                'order_sys_sn.required' => 'order_sys_sn  and transport_sn must be filled in one of them',
                'transport_sn.required' => 'order_sys_sn  and transport_sn must be filled in one of them'
            ]
        );

        $where                 = [];
        $where['order_sys_sn'] = $param['order_sys_sn'];
        $member                = $request->UserInfo;

        $orderParcelLogService = \Hyperf\Support\make(OrderParcelLogService::class);
        $result['code']        = 200;
        $result['msg']         = '查询成功';
        $result['data']        = $orderParcelLogService->LogOutput(where: $where);
        return $this->response->json($result);
    }

    /**
     * @DOC 订单备注修改
     */
    #[RequestMapping(path: 'remark', methods: 'post')]
    public function orderRemark(RequestInterface $request)
    {
        $params        = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'order_sys_sn' => ['required'],
                'desc'         => ['required'],
            ],
            [
                'order_sys_sn.required' => '订单号错误',
                'desc.required'         => '请填写订单备注'
            ]
        );

        $orderDb = OrderModel::with([
            'parcel' => function ($query) {
                $query->select('order_sys_sn', 'parcel_status');
            }
        ])
            ->where('order_sys_sn', $param['order_sys_sn'])
            ->select(['order_sys_sn', 'order_status'])
            ->first();
        if (empty($orderDb)) {
            return $this->response->json(['code' => 201, 'msg' => '订单号错误']);
        }
        $orderDb = $orderDb->toArray();
        $status  = $orderDb['parcel']['parcel_status'] ?? $orderDb['order_status'];
        if (!in_array($status, [26, 27, 28, 29, 30, 40, 41, 42, 43])) {
            return $this->response->json(['code' => 201, 'msg' => '当前订单已寄送，无法修改备注']);
        }

        if (OrderModel::where('order_sys_sn', $param['order_sys_sn'])->update(['desc' => $param['desc']])) {
            return $this->response->json(['code' => 200, 'msg' => '修改备注成功']);
        }
        return $this->response->json(['code' => 201, 'msg' => '未检测到修改信息']);

    }

    /**
     * @DOC 获取实名认证提交信息
     */
    #[RequestMapping(path: 'element', methods: 'post')]
    public function getElement(RequestInterface $request)
    {
        $param    = $request->all();
        $userInfo = $this->request->UserInfo;
        $where[]  = ['parent_agent_uid', '=', $userInfo['parent_agent_uid']];
        $result   = (new AuthWayService())->getElement($param, $where);
        return $this->response->json($result);
    }

    /**
     * @DOC 小程序首页统计
     */
    #[RequestMapping(path: 'statistics', methods: 'post')]
    public function statistics()
    {
        $useWhere = $this->useWhere();
        $order    = OrderModel::query()->where($useWhere['where']);
        $no_pay   = $order
            ->where('order_status', '!=', 220)
            ->where(function ($query) {
                $query->orWhereHas('cost_member_item', function ($parcel) {
                    $parcel->where('payment_status', '=', 0)->select(['order_sys_sn']);
                });
                $query->orWhere('order_status', 28);
            })->whereDoesntHave('parcelException', function ($query) {
                $query->whereIn('status', [0, 1, 2]);
            })->whereDoesntHave('prediction', function ($query) {
                $query->where('parcel_type', 26102);
            })
            ->count();
        $order    = OrderModel::query()->where($useWhere['where']);
        $no_send  = $order
            ->where(function ($query) {
                $query->orWhereIn('order_status', [29])
                    ->orWhere(function ($query) {
                        $query->where('order_status', 30)->whereHas('parcel', function ($parcel) {
                            $parcel->whereIn('parcel_status', [41, 42]);
                        });
                    });
            })->whereDoesntHave('parcelException', function ($query) {
                $query->whereIn('status', [0, 1, 2]);
            })->count();
        return $this->response->json(['code' => 200, 'msg' => '查询成功', 'data' => ['no_pay' => $no_pay, 'no_send' => $no_send]]);
    }


}
