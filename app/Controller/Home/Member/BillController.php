<?php

namespace App\Controller\Home\Member;


use App\Common\Lib\Arr;
use App\Controller\Home\HomeBaseController;
use App\Exception\HomeException;
use App\Model\OrderCostJoinModel;
use App\Model\OrderCostMemberModel;
use App\Request\LibValidation;
use App\Service\BillSettlementService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Validation\Rule;
use Psr\Http\Message\ResponseInterface;
use App\Service\Cache\BaseCacheService;
use Vtiful\Kernel\Format;
use function App\Common\Format;


#[Controller(prefix: "member/bill")]
class BillController extends HomeBaseController
{

    protected int $AdjustmentPayCode = 14304; //调剂支付
    protected int $SystemDeductionPayCode = 14305; //系统扣款

    #[Inject]
    protected BaseCacheService $baseCacheService;

    /**
     * @DOC 月结账单
     * @Name   month
     * @Author wangfei
     * @date   2023/11/15 2023
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: "month", methods: "get,post")]
    public function month(RequestInterface $request): ResponseInterface
    {

    }

    /**
     * @DOC 平台结算与加盟商之间的账单
     * @Name   platformSettlement
     * @Author wangfei
     * @date   2023/10/11 2023
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: "platform/settlement", methods: "get,post")]
    public function platformSettlement(RequestInterface $request): ResponseInterface
    {
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params        = $LibValidation->validate(params: $request->all(), rules: [
            'order_sys_sn'   => ['required', 'array'],
            'order_sys_sn.*' => ['required', 'string', 'min:10'],
        ]);
        switch ($member['role_id']) {
            case 1:
                break;
            default:
                throw new HomeException('非平台代理禁止访问此接口');
                break;
        }
        $BillSettlementService = \Hyperf\Support\make(BillSettlementService::class, [$member]);
        $logger                = \Hyperf\Support\make(LoggerFactory::class)->get('log', 'AsyncBillSettlementProcess');
        $BillSettlementService->logger($logger);
        $platformSettlement = $BillSettlementService->platformSettlement(params: $params);
        return $this->response->json($platformSettlement);
    }

    /**
     * @DOC  加盟商主动发起的，与客户的结算
     * @Name   joinSettlement
     * @Author wangfei
     * @date   2023/10/12 2023
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: "join/settlement", methods: "get,post")]
    public function joinSettlement(RequestInterface $request): ResponseInterface
    {
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params        = $LibValidation->validate(params: $request->all(), rules: [
            'order_sys_sn'   => ['required', 'array'],
            'order_sys_sn.*' => ['required', 'string', 'min:10'],
        ]);
        switch ($member['role_id']) {
            case 3:
                break;
            default:
                throw new HomeException('非平台代理禁止访问此接口');
                break;
        }
        $BillSettlementService = \Hyperf\Support\make(BillSettlementService::class, [$member]);
        $logger                = \Hyperf\Support\make(LoggerFactory::class)->get('log', 'AsyncBillSettlementProcess');
        $BillSettlementService->logger($logger);
        $platformSettlement = $BillSettlementService->joinSettlement(params: $params);
        return $this->response->json($platformSettlement);
    }

    /**
     * @DOC 用户账单
     */
    #[RequestMapping(path: "user", methods: "get,post")]
    public function user(RequestInterface $request): ResponseInterface
    {
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params        = $LibValidation->validate(params: $request->all(), rules: [
            'page'         => ['required', 'integer'],
            'limit'        => ['required', 'integer'],
            'order_sys_sn' => ['string'],
            'transport_sn' => ['string'],
            'line_id'      => ['integer'],
            'ware_id'      => ['integer'],
            'product_id'   => ['integer'],
            'start_time'   => ['date_format:Y-m-d H:i:s'],
            'end_time'     => ['after:start_time', 'date_format:Y-m-d H:i:s'],
        ]);
        $perPage       = Arr::hasArr($params, 'limit') ? $params['limit'] : 15;

        $query = OrderCostMemberModel::query();
        if (Arr::hasArr($params, 'order_sys_sn')) {
            $query->where('order_sys_sn', '=', $params['order_sys_sn']);
        }
        if (Arr::hasArr($params, 'transport_sn')) {
            $query->whereHas('parcel', function ($query) use ($params) {
                $query->where('transport_sn', '=', $params['transport_sn'])->select(['order_sys_sn']);
            });
        }
        $query->where('parent_agent_uid', '=', $member['parent_agent_uid']);
        $query->where('parent_join_uid', '=', $member['parent_join_uid']);
        $query->where('member_uid', '=', $member['uid']);

        if (Arr::hasArr($params, 'line_id') || Arr::hasArr($params, 'ware_id')) {
            $query->whereHas('order', function ($query) use ($params) {
                if (Arr::hasArr($params, 'line_id')) {
                    $query->where('line_id', '=', $params['line_id']);
                }
                if (Arr::hasArr($params, 'ware_id')) {
                    $query->where('ware_id', '=', $params['ware_id']);
                }
                $query->select(['order_sys_sn']);
            });
        }

        if (Arr::hasArr($params, 'product_id')) {
            $query->where('product_id', '=', $params['product_id']);
        }
        if (Arr::hasArr($params, ['start_time', 'end_time'])) {
            $query->whereBetween('settlement_time', [strtotime($params['start_time']), strtotime($params['end_time'])]);
        }


        $painter = $query->with([
            'order'       => function ($query) {
                $query->select(["order_sys_sn", 'member_uid', 'parent_agent_uid', 'line_id', 'ware_id', 'pro_id', 'channel_id', 'add_time']);
            },
            'member_cost' => function ($query) {
                $query->with([
                    'item' => function ($query) {
                        $query->select(['order_sys_sn', 'charge_code', 'charge_code_name', 'payment_status', 'payment_code', 'payment_method', 'payment_currency', 'original_total_fee', 'discount', 'payment_amount']);
                    }])->select(['order_sys_sn', 'transport_sn', 'member_uid', 'payment_sn', 'settlement_status', 'member_template_id', 'member_version_id', 'length', 'width', 'height', 'discount', 'member_total_fee', 'member_cost_payment', 'member_has_payment', 'member_need_payment', 'settlement_status', 'change_amount_sign']);
            },
        ])->select(
            [
                'order_sys_sn'
            ])
            ->paginate($perPage)->toArray();

        $painter['code'] = 200;
        $painter['msg']  = '加载完成';
        $painter['data'] = $this->handleOrderCost($painter['data'], $member);
        return $this->response->json($painter);
    }

    //用户账单详情
    #[RequestMapping(path: "user/detail", methods: "get,post")]
    public function userDetail(RequestInterface $request): ResponseInterface
    {
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params        = $LibValidation->validate(params: $request->all(), rules: [
            'order_sys_sn' => ['required', 'string']
        ]);
        $query         = OrderCostMemberModel::query();
        switch ($member['role_id']) {
            default:
                throw new HomeException('只有客户才能访问');
                break;
            case 4:
            case 5:

                break;
        }
        $query->where('order_sys_sn', '=', $params['order_sys_sn']);
        $query->where('member_uid', '=', $member['uid']);
        $query->where('parent_agent_uid', '=', $member['parent_agent_uid']);
        $memberCostDb                = $query->with(
            [
                'order'       => function ($query) {
                    $query->select(["order_sys_sn", 'member_uid', 'parent_agent_uid', 'line_id', 'ware_id', 'pro_id', 'channel_id', 'add_time']);
                },
                'member_cost' => function ($query) {
                    $query->with([
                        'item' => function ($query) {
                            $query->select(['order_sys_sn', 'charge_code', 'charge_code_name', 'payment_status', 'payment_code', 'payment_method', 'payment_currency', 'original_total_fee', 'discount', 'payment_amount', 'exchange_rate', 'exchange_amount']);
                        }])->select(['order_sys_sn', 'member_uid', 'payment_sn', 'settlement_status', 'member_template_id', 'member_version_id', 'length', 'width', 'height', 'discount', 'member_total_fee', 'member_cost_payment', 'member_has_payment', 'member_need_payment', 'member_join_weight', 'change_amount_sign']);
                },


            ])->select(['order_sys_sn'])
            ->first()->toArray();
        $memberCostDb['member_cost'] = $this->handleMemberItem($memberCostDb['member_cost']);
        $handleOrderCost             = $this->handleOrderCost(OrderCost: [$memberCostDb], member: $member);
        $result['code']              = 200;
        $result['msg']               = '查询成功';
        $result['data']              = current($handleOrderCost);
        return $this->response->json($result);
    }


    /** 加盟商账单详情
     * @DOC
     * @Name   joinDetail
     * @Author wangfei
     * @date   2023/9/27 2023
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: "join/detail", methods: "get,post")]
    public function joinDetail(RequestInterface $request): ResponseInterface
    {
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params        = $LibValidation->validate(params: $request->all(), rules: [
            'order_sys_sn' => ['required', 'string']
        ]);
        $query         = OrderCostJoinModel::query();
        $query->where('order_sys_sn', '=', $params['order_sys_sn']);

        switch ($member['role_id']) {
            case 1:
            case 2:
                break;
            case 3:
                $query->where('member_uid', '=', $member['uid']);
                break;
            default:
                throw new HomeException("无权限查看");
                break;
        }
        $query->where('parent_agent_uid', '=', $member['parent_agent_uid']);
        $memberCostDb = $query->with(
            [
                'order'       => function ($query) {
                    $query
                        ->with([
                            'member' => function ($query) {
                                $query->select(['user_name', 'nick_name', 'uid']);
                            },
                            'joins'  => function ($query) {
                                $query->select(['user_name', 'nick_name', 'uid']);
                            },

                        ])
                        ->select(["order_sys_sn", 'member_uid', 'parent_join_uid', 'parent_agent_uid', 'line_id', 'ware_id', 'pro_id', 'order_type', 'channel_id', 'add_time']);
                },
                'cost'        => function ($query) {
                    $query->select(['order_sys_sn', 'settlement_status', 'platform_payment', 'member_cost_payment', 'member_has_payment', 'join_cost_payment', 'join_self_payment', 'member_adjustment_payment', 'join_has_payment', 'join_profit_amount', 'platform_refund_money', 'platform_has_refund_money', 'change_amount_sign']);
                },
                'join_cost'   => function ($query) {
                    $query->with(
                        [
                            'price_template' => function ($query) {
                                $query->select(['template_name', 'template_id']);
                            },
                            'item'           => function ($query) {
                                $query->select(['order_sys_sn', 'payment_status', 'charge_code', 'charge_code_name', 'payment_type', 'payment_code', 'payment_method', 'original_total_fee', 'discount', 'payment_currency', 'payment_amount']);
                            }
                        ])->select(["order_sys_sn", 'transport_sn', 'member_uid', 'join_platform_weight', 'settlement_status', 'discount', 'join_total_fee', 'join_cost_payment', 'discount', 'join_has_payment', 'need_pay_amount', 'adjustment_amount', 'join_self_amount', 'member_version_id', 'member_template_id', 'settlement_status', 'change_amount_sign']);
                },
                'member_cost' => function ($query) {
                    $query->with([
                        'item' => function ($query) {
                            $query->select(['order_sys_sn', 'charge_code', 'charge_code_name', 'payment_status', 'payment_code', 'info', 'payment_method', 'payment_currency', 'original_total_fee', 'discount', 'payment_amount']);
                        }])->select(['order_sys_sn', 'transport_sn', 'member_uid', 'payment_sn', 'settlement_status', 'member_template_id', 'member_version_id', 'length', 'width', 'height', 'discount', 'member_total_fee', 'member_cost_payment', 'member_has_payment', 'member_need_payment', 'settlement_status', 'change_amount_sign']);
                }

            ])->select(["order_sys_sn"])->first();
        if (!empty($memberCostDb)) {
            $memberCostDb    = $memberCostDb->toArray();
            $handleOrderCost = $this->handleOrderCost(OrderCost: [$memberCostDb], member: $member);
            $handleOrderCost = current($handleOrderCost);
            $result['code']  = 200;
            $result['msg']   = '查询成功';
            $handleOrderCost = $this->handleJoinCost(orderCost: $handleOrderCost);
            $checkUpdateCost = $this->checkUpdateCost(handleOrderCost: $handleOrderCost);
            if (Arr::hasArr($checkUpdateCost, 'orderCostUpdate')) {
                $orderCostUpdate         = $checkUpdateCost['orderCostUpdate'];
                $handleOrderCost['cost'] = array_merge($handleOrderCost['cost'], $orderCostUpdate);
            }
            //$handleOrderCost['orderCost'] = $checkUpdateCost['orderCostUpdate'];
            unset($handleOrderCost['member_cost']['item'], $handleOrderCost['join_cost']['item']);
            $result['data'] = $handleOrderCost;
            return $this->response->json($result);
        }
        throw new HomeException("订单不存在");
    }

    protected function checkUpdateCost(array $handleOrderCost)
    {
        $order['order_sys_sn']           = $handleOrderCost['order_sys_sn'];
        $order['parcel']['transport_sn'] = $handleOrderCost['join_cost']['transport_sn'];
        $order['cost_join']              = $handleOrderCost['join_cost'];
        $order['cost_member']            = $handleOrderCost['member_cost'];
        $order['cost']                   = $handleOrderCost['cost'];
        $BillSettlementService           = \Hyperf\Support\make(BillSettlementService::class);
        $logger                          = \Hyperf\Support\make(LoggerFactory::class)->get('log', 'AsyncBillSettlementProcess');
        $BillSettlementService->logger($logger);
        $handleOrdersResult = $BillSettlementService->handleOrdersResult([$order]);
        $BillSettlementService->handleOrdersResultTosave($handleOrdersResult);
        return $handleOrdersResult;
    }

    /**
     * @DOC 判断加盟商的价格，是否可以转嫁
     * @Name   handleJoinCost
     * @Author wangfei
     * @date   2023/10/10 2023
     * @param array $orderCost
     * @return array
     */
    protected function handleJoinCost(array $orderCost)
    {
        $hasMemberChargeCodeData = $hasChargeCodeData = $hasJoinChargeCodeData = $hasCodeItems = [];
        $memberCostItems         = [];
        if (Arr::hasArr($orderCost, 'member_cost')) {
            $memberCostItems         = $orderCost['member_cost']['item'];
            $hasMemberChargeCodeData = array_column($memberCostItems, 'charge_code');
            $memberCostItems         = array_column($memberCostItems, null, 'charge_code');
        }
        $joinCostItems = [];
        if (Arr::hasArr($orderCost, 'join_cost')) {
            $joinCostItems         = $orderCost['join_cost']['item'];
            $hasJoinChargeCodeData = array_column($joinCostItems, 'charge_code');
            $joinCostItems         = array_column($joinCostItems, null, 'charge_code');
        }
        $hasChargeCodeData = array_unique(array_merge($hasMemberChargeCodeData, $hasJoinChargeCodeData)); //获取用户、加盟商所有的收费项、然后合并
        foreach ($hasChargeCodeData as $key => $code) {
            $hasCodeItems[$key]['member'] = [];
            $hasCodeItems[$key]['join']   = [];
            if (isset($memberCostItems[$code])) {
                $hasCodeItems[$key]['charge_code_name'] = $memberCostItems[$code]['charge_code_name'];
                $hasCodeItems[$key]['member']           = $memberCostItems[$code];
            }
            if (isset($joinCostItems[$code])) {
                $hasCodeItems[$key]['charge_code_name']    = $joinCostItems[$code]['charge_code_name'];
                $hasCodeItems[$key]['join']                = $joinCostItems[$code];
                $hasCodeItems[$key]['join']['mayTransfer'] = 0;
                if (!in_array($code, $hasMemberChargeCodeData) && $joinCostItems[$code]['payment_type'] != 14304) { //14304 调剂支付 不可以转嫁
                    $hasCodeItems[$key]['join']['mayTransfer'] = 1;//可以转嫁
                }
            }
        }
        sort($hasCodeItems);
        $orderCost['hasCodeItems'] = $hasCodeItems;
        return $orderCost;
    }


    /**
     * @DOC  创建加盟商收费项
     * @Name   createJoinItems
     * @Author wangfei
     * @date   2023/9/27 2023
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: "create/items", methods: "get,post")]
    public function createJoinItems(RequestInterface $request): ResponseInterface
    {
        $member = $request->UserInfo;
        switch ($member['role_id']) {
            case 1:
                break;
            default:
                throw new HomeException("非平台代理禁止操作");
                break;
        }
        $LibValidation         = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params                = $LibValidation->validate(params: $request->all(), rules: [
            'order_sys_sn'   => ['required', 'string'],
            'transfer'       => ['string', 'in:yes,no'],
            'items'          => ['required', 'array'],
            'items.*.code'   => ['required', 'integer'],
            'items.*.amount' => ['required', 'numeric', 'min:0'],
        ]);
        $logger                = \Hyperf\Support\make(LoggerFactory::class)->get('log', 'AsyncSupplementWeightCalcProcess');
        $BillSettlementService = \Hyperf\Support\make(BillSettlementService::class, [$member]);
        $BillSettlementService->logger($logger);
        $params['transfer'] = $params['transfer'] ?? 'no';
        $createJoinItems    = $BillSettlementService->createJoinItems(params: $params, local_member: $member);
        $result['code']     = 200;
        $result['msg']      = $createJoinItems['msg'];
        $result['data']     = $createJoinItems;
        return $this->response->json($result);
    }

    /**
     * @DOC  加盟商角色 转移平台给加盟商的费用给 客户
     * @Name   joinTransfer
     * @Author wangfei
     * @date   2023/9/27 2023
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: "join/transfer", methods: "get,post")]
    public function joinTransfer(RequestInterface $request): ResponseInterface
    {
        $member = $request->UserInfo;
        switch ($member['role_id']) {
            case 3:
                break;
            default:
                throw new HomeException("非平台加盟商禁止操作");
                break;
        }
        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params        = $LibValidation->validate(params: $request->all(), rules: [
            'order_sys_sn'   => ['required', 'string'],
            'items'          => ['required', 'array'],
            'items.*.code'   => ['required', 'integer'],
            'items.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        $BillSettlementService = \Hyperf\Support\make(BillSettlementService::class, [$member]);
        $joinTransfer          = $BillSettlementService->joinTransfer(params: $params, member: $member);
        return $this->response->json($joinTransfer);
    }

    /**
     * @DOC  平台代理给加盟商新增收费项
     * @Name   mayJoinItems
     * @Author wangfei
     * @date   2023/9/27 2023
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: "may/items", methods: "get,post")]
    public function mayJoinItems(RequestInterface $request)/*: ResponseInterface*/
    {
        $member = $request->UserInfo;
        switch ($member['role_id']) {
            case 1:
                break;
            default:
                throw new HomeException("非平台代理禁止操作");
                break;
        }
        $LibValidation         = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params                = $LibValidation->validate(params: $request->all(), rules: [
            'order_sys_sn' => ['required', 'string']
        ]);
        $logger                = \Hyperf\Support\make(LoggerFactory::class)->get('log', 'AsyncSupplementWeightCalcProcess');
        $BillSettlementService = \Hyperf\Support\make(BillSettlementService::class, [$member]);
        $BillSettlementService->logger($logger);
        $mayCreateItem  = $BillSettlementService->mayCreateItem($params['order_sys_sn']);
        $result['code'] = 200;
        $result['msg']  = '查询成功';
        $result['data'] = $mayCreateItem;
        return $this->response->json($result);
    }

    /**
     * @DOC
     * @Name   handleJoinItem
     * @Author wangfei
     * @date   2023-09-23 2023
     * @param array $Order
     * @return array
     */
    public function handleJoinItem(array $Order)
    {
        $join_total_fee    = 0;
        $adjustment_amount = 0;//调剂支付金额（已付）
        $join_self_amount  = 0;//加盟商自己账号支付的金额
        $join_cost_payment = 0;//加盟商应付成本（包含已付、未付）
        $join_has_payment  = 0;//已经付的费用
        $need_pay_amount   = 0;//需要支付的金额
        if (Arr::hasArr($Order, 'item')) {
            foreach ($Order['item'] as $key => $item) {
                $join_total_fee    += $item['original_total_fee'];
                $join_cost_payment += $item['payment_amount'];
                switch ($item['payment_status']) {
                    case 0: //未付
                        $need_pay_amount += $item['payment_amount'];
                        break;
                    case 1: //已付
                        $join_has_payment += $item['payment_amount'];
                        break;
                }

                if ($item['payment_status'] == 1) {
                    switch ($item['payment_type']) {
                        case $this->AdjustmentPayCode: //调剂支付
                            $adjustment_amount += $item['payment_amount'];
                            break;
                        case $this->SystemDeductionPayCode://加盟商自己账号支付金额
                            $join_self_amount += $item['payment_amount'];
                            break;
                    }
                }
            }
        }
        $Order['join_total_fee']    = Format($join_total_fee, 2);
        $Order['join_cost_payment'] = Format($join_cost_payment, 2);
        $Order['join_has_payment']  = Format($join_has_payment, 2);
        $Order['need_pay_amount']   = Format($need_pay_amount, 2);
        $Order['adjustment_amount'] = Format($adjustment_amount, 2);
        $Order['join_self_amount']  = Format($join_self_amount, 2);
        return $Order;
    }


    /**
     * @DOC  统计金额
     * @Name   handleMemberItem
     * @Author wangfei
     * @date   2023-09-23 2023
     * @param array $Order
     * @return array
     */
    public function handleMemberItem(array $Order)
    {
        $member_cost_payment = 0;//用户预估成本（包含已付，未付）
        $member_need_payment = 0;//用户预估成本（包含已付，未付）
        $member_has_payment  = 0;//用户预估成本（包含已付，未付）
        if (Arr::hasArr($Order, 'item')) {
            foreach ($Order['item'] as $key => $item) {
                $member_cost_payment += $item['payment_amount'];
                switch ($item['payment_status']) {
                    case 0: //未付
                        $member_need_payment += $item['payment_amount'];
                        break;
                    case 1: //已付
                        $member_has_payment += $item['payment_amount'];
                        break;
                }
            }
        }
        $Order['transport_sn']        = Arr::hasArr($Order, 'parcel') ? $Order['parcel']['transport_sn'] : '';
        $Order['member_cost_payment'] = Format($member_cost_payment, 2);
        $Order['member_has_payment']  = Format($member_has_payment, 2);
        $Order['member_need_payment'] = Format($member_need_payment, 2);
        return $Order;


    }

    /**
     * @DOC  整理数据
     * @Name   handleOrderCost
     * @Author wangfei
     * @date   2023-09-21 2023
     * @param array $OrderCost //成本信息
     * @param array $member 用户信息
     */
    protected function handleOrderCost(array $OrderCost, array $member)
    {
        //线路
        $LineCache = $this->baseCacheService->LineCache();
        //产品
        $ProductCache = $this->baseCacheService->ProductCache(member_uid: $member['parent_agent_uid']);
        //仓库
        $WareHouseCache = $this->baseCacheService->WareHouseCache(member_uid: $member['parent_agent_uid']);
        $ChannelCache   = $this->baseCacheService->ChannelCache(member_uid: $member['parent_agent_uid']);
        //订单类型：
        $OrderType = $this->baseCacheService->ConfigCache(model: 18);
        foreach ($OrderCost as $key => $cost) {
            $order = $cost['order'];
            if (empty($order)) {
                continue;
            }
            if (isset($LineCache[$order['line_id']])) {
                $OrderCost[$key]['line'] = $LineCache[$order['line_id']];
            }
            if (isset($WareHouseCache[$order['ware_id']])) {
                $OrderCost[$key]['ware'] = $WareHouseCache[$order['ware_id']];
            }
            if (Arr::hasArr($order, 'pro_id') && isset($ProductCache[$order['pro_id']])) {
                $OrderCost[$key]['product'] = $ProductCache[$order['pro_id']];
            }

            if (Arr::hasArr($order, 'channel_id') && isset($ChannelCache[$order['channel_id']])) {
                $OrderCost[$key]['channel'] = $ChannelCache[$order['channel_id']];
            }

            if (Arr::hasArr($order, 'order_type') && isset($OrderType[$order['order_type']])) {
                $OrderCost[$key]['order_type'] = $OrderType[$order['order_type']];
            }
            $OrderCost[$key]['order']['cn_add_time'] = date('Y-m-d H:i:s', $order['add_time']);

        }
        return $OrderCost;
    }

    /**
     * 加盟商账单
     */
    #[RequestMapping(path: "join", methods: "get,post")]
    public function join(RequestInterface $request): ResponseInterface
    {
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params        = $LibValidation->validate(params: $request->all(), rules: [
            'page'                       => ['required', 'numeric'],
            'limit'                      => ['required', 'numeric'],
            'order_sys_sn'               => ['string'],
            'transport_sn'               => ['string'],
            'line_id'                    => ['integer'],
            'ware_id'                    => ['integer'],
            'member_uid'                 => ['integer'],
            'join_uid'                   => ['integer'],
            'product_id'                 => ['integer'],
            'platform_settlement_status' => ['integer', Rule::in([0, 1])],
            'member_settlement_status'   => ['integer', Rule::in([0, 1])],
            'start_time'                 => ['date_format:Y-m-d H:i:s'],
            'end_time'                   => ['after:start_time', 'date_format:Y-m-d H:i:s'],
        ]);
        $perPage       = Arr::hasArr($params, 'limit') ? $params['limit'] : 15;

        $query = OrderCostJoinModel::query();
        if (Arr::hasArr($params, 'order_sys_sn')) {
            $query->where('order_sys_sn', '=', $params['order_sys_sn']);
        }
        if (Arr::hasArr($params, 'transport_sn')) {
            $query->whereHas('parcel', function ($query) use ($params) {
                $query->where('transport_sn', '=', $params['transport_sn']);
            });
        }
        //订单表关联
        if (Arr::hasArr($params, 'line_id') || Arr::hasArr($params, 'ware_id')) {
            $query->whereHas('order', function ($query) use ($params) {
                if (Arr::hasArr($params, 'line_id')) {
                    $query->where('line_id', '=', $params['line_id']);
                }
                if (Arr::hasArr($params, 'ware_id')) {
                    $query->where('ware_id', '=', $params['ware_id']);
                }
                $query->select(['order_sys_sn']);
            });
        }
        //用户cost_member 关联
        if (Arr::hasArr($params, 'member_settlement_status', true) || Arr::hasArr($params, 'member_uid')) {
            $query->whereHas('member_cost', function ($query) use ($params) {
                if (Arr::hasArr($params, 'member_settlement_status', true)) {
                    $query->where('settlement_status', '=', $params['member_settlement_status']);
                }
                if (Arr::hasArr($params, 'member_uid')) {
                    $query->where('member_uid', '=', $params['member_uid']);
                }
                $query->select(['order_sys_sn']);
            });
        }
        if (Arr::hasArr($params, 'product_id')) {
            $query->where('product_id', '=', $params['product_id']);
        }
        if (Arr::hasArr($params, 'platform_settlement_status', true)) {
            $query->where('settlement_status', '=', $params['platform_settlement_status']);
        }
        if (Arr::hasArr($params, 'join_uid')) {
            $query->where('member_uid', '=', $params['join_uid']);
        }

        if (Arr::hasArr($params, ['start_time', 'end_time'])) {
            $query->whereBetween('settlement_time', [strtotime($params['start_time']), strtotime($params['end_time'])]);
        }

        switch ($member['role_id']) {
            case 1:
            case 2:
                break;
            case 3:
                $query->where('member_uid', '=', $member['uid']);
                break;
            default:
                throw new HomeException("无权限查看");
                break;
        }

        $query->where('parent_agent_uid', '=', $member['parent_agent_uid']);

        $painter         = $query->with(
            [
                'order'     => function ($query) {
                    $query->select(["order_sys_sn", 'member_uid', 'parent_agent_uid', 'line_id', 'ware_id', 'pro_id', 'channel_id', 'add_time']);
                },
                'cost'      => function ($query) {
                    $query->select(["order_sys_sn", 'platform_payment', 'member_cost_payment', 'join_cost_payment', 'join_profit_amount', 'change_amount_sign', 'platform_refund_money', 'platform_has_refund_money']);
                },
                'join_cost' => function ($query) {
                    $query->select(["order_sys_sn", 'transport_sn', 'member_uid', 'join_platform_weight', 'settlement_status', 'discount', 'join_total_fee', 'join_cost_payment', 'discount', 'join_has_payment', 'need_pay_amount', 'adjustment_amount', 'join_self_amount', 'member_version_id', 'member_template_id', 'change_amount_sign']);
                },

                'member_cost' => function ($query) {
                    $query->select(['order_sys_sn', 'member_uid', 'payment_sn', 'settlement_status', 'member_template_id', 'member_version_id', 'length', 'width', 'height', 'discount', 'member_total_fee', 'member_cost_payment', 'member_has_payment', 'member_need_payment', 'member_join_weight', 'change_amount_sign']);
                }
            ])->select(["order_sys_sn"])->paginate($perPage)->toArray();
        $painter['code'] = 200;
        $painter['msg']  = '加载完成';
        $painter['data'] = $this->handleOrderCost($painter['data'], $member);
        return $this->response->json($painter);
    }


}
