<?php

declare(strict_types=1);

namespace App\Controller\Work;

use App\Exception\HomeException;
use App\Model\AgentMemberModel;
use App\Request\LibValidation;
use App\Service\AnalyseChannelService;
use App\Service\AuthWayService;
use App\Service\CalcService;
use App\Service\CouponsService;
use App\Service\OrderParcelLogService;
use App\Service\OrdersService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: "/", server: 'httpWork')]
class OrderController extends WorkBaseController
{
    #[Inject]
    protected OrdersService $orderService;

    /**
     * @DOC 制单
     */
    #[RequestMapping(path: 'order/make', methods: 'post')]
    public function makeOrder(RequestInterface $request)
    {
        $param            = $request->all();
        $JoinMember       = $this->request->UserInfo;
        $param['role_id'] = $JoinMember['role_id'];
        \Hyperf\Support\make(LibValidation::class)
            ->validate($param,
                [
                    'member_uid' => ['required', 'numeric', Rule::exists('agent_member')->where(function ($query) use ($param, $JoinMember) {
                        $query->where('parent_agent_uid', $JoinMember['parent_agent_uid'])
                            ->where('parent_join_uid', $JoinMember['uid'])
                            ->where('member_uid', $param['member_uid']);
                    })],
                ],
                [
                    'member_uid.required' => '请选择客户',
                    'member_uid.exists'   => '当前客户不是你的下级客户',
                ]
            );

        // 参数匹配
        $member['uid']              = $param['member_uid'];
        $member['parent_join_uid']  = $JoinMember['uid'];
        $member['parent_agent_uid'] = $JoinMember['parent_agent_uid'];
        $param['from_platform_id']  = 1795; // 来源订单：加盟商工作台 - 1795
        // 匹配线路
        $param['line_id'] = $this->orderService->matchLine($param, $member);
        $result           = $this->orderService->makeOrder($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 订单编辑
     */
    #[RequestMapping(path: 'order/edit', methods: 'post')]
    public function editOrder(RequestInterface $request)
    {
        $param    = $request->all();
        $userInfo = $this->request->UserInfo;
        if (empty($param['member_uid'])) {
            throw new HomeException('请选择客户');
        }
        if (in_array($this->request->UserInfo['role_id'], [1, 2])) {
            return $this->response->json(['code' => 201, 'msg' => '仅加盟商可编辑']);
        }
        $userInfo['role_id']         = 5;
        $userInfo['parent_join_uid'] = $userInfo['uid'];
        $userInfo['uid']             = $param['member_uid'];
        $param['from_platform_id']   = 1795; // 来源
        $result                      = $this->orderService->orderEdit($param, $userInfo);
        return $this->response->json($result);

    }

    /**
     * @DOC 订单详情
     */
    #[RequestMapping(path: 'order/detail', methods: 'post')]
    public function orderDetail(RequestInterface $request)
    {
        $param    = $request->all();
        $userInfo = $this->request->UserInfo;
        if (empty($param['member_uid'])) {
            throw new HomeException('请选择客户');
        }
        $userInfo['uid']     = $param['member_uid'];
        $userInfo['role_id'] = 5;
        $result              = $this->orderService->orderDetail($param, $userInfo);
        return $this->response->json($result);

    }

    /**
     * @DOC 订单自检
     */
    #[RequestMapping(path: 'order/analyse', methods: 'post')]
    public function analyse(RequestInterface $request)
    {
        $param  = $request->all();
        $result = $this->orderService->analyse($param);
        return $this->response->json($result);
    }

    /**
     * @DOC 获取实名认证提交信息
     */
    #[RequestMapping(path: 'order/element', methods: 'post')]
    public function getElement(RequestInterface $request)
    {
        $param = $request->all();
        if (empty($param['member_uid'])) {
            throw new HomeException('请选择客户');
        }
        $userInfo = $this->request->UserInfo;
        $where[]  = ['parent_agent_uid', '=', $userInfo['parent_agent_uid']];
        $where[]  = ['member_uid', '=', $param['member_uid']];
        $result   = (new AuthWayService())->getElement($param, $where);
        return $this->response->json($result);
    }

    /**
     * @DOC 提交实名认证信息
     */
    #[RequestMapping(path: 'order/identity/binding', methods: 'get,post')]
    public function binding(RequestInterface $request)
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
     * @DOC   : 领取优惠券
     * @Name  : couponsReceive
     * @Author: wangfei
     * @date  : 2025-03 15:23
     * @param RequestInterface $request
     * @return ResponseInterface
     *
     */
    #[RequestMapping(path: 'order/coupons/receive', methods: 'get,post')]
    public function couponsReceive(RequestInterface $request)
    {
        $params              = $this->request->all();
        $member              = $request->UserInfo;
        $params              = \Hyperf\Support\make(LibValidation::class)
            ->validate($params, [
                'member_uid' => ['required', 'numeric'],
                'coupon_id'  => ['required', 'string', Rule::exists('coupons', 'coupon_id')]
            ]);
        $member              = AgentMemberModel::query()
            ->where('parent_agent_uid', $member['parent_agent_uid'])
            ->where('parent_join_uid', $member['uid'])
            ->where('member_uid', $params['member_uid'])
            ->select(['member_uid as uid', 'parent_join_uid', 'role_id', 'parent_agent_uid'])
            ->first()->toArray();
        $member['child_uid'] = 0;
        $couponsService      = \Hyperf\Support\make(CouponsService::class);
        $ret                 = $couponsService->receiveCoupons($member, $params);
        return $this->response->json($ret);
    }

    /**
     * @DOC 支付
     */
    #[RequestMapping(path: 'order/pay', methods: 'post')]
    public function orderPay(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;

        $LibValidation       = \Hyperf\Support\make(LibValidation::class);
        $params              = $LibValidation->validate($param,
            [
                'member_uid'   => ['required', 'numeric', Rule::exists('agent_member')->where(function ($query) use ($param, $member) {
                    $query->where('parent_agent_uid', $member['parent_agent_uid'])
                        ->where('parent_join_uid', $member['uid'])
                        ->where('member_uid', $param['member_uid']);
                })],
                'order_sys_sn' => 'required|array',
                'payment_code' => ['required'],
                'coupons_code' => ['string'], //优惠券code
            ], [
                'member_uid.required'   => '请选择客户',
                'member_uid.numeric'    => '客户参数错误',
                'member_uid.exists'     => '客户参数错误',
                'order_sys_sn.required' => '请选择订单',
                'order_sys_sn.array'    => '订单参数错误',
                'payment_code.required' => '请选择支付方式',
                'payment_code.string'   => '支付方式参数错误',
                'coupons_code.string'   => '优惠券参数错误',
            ]
        );
        $member              = AgentMemberModel::query()
            ->where('parent_agent_uid', $member['parent_agent_uid'])
            ->where('parent_join_uid', $member['uid'])
            ->where('member_uid', $param['member_uid'])
            ->select(['member_uid as uid', 'parent_join_uid', 'role_id', 'parent_agent_uid'])
            ->first()->toArray();
        $member['child_uid'] = 0;
        // 工作台同步取号
        $type   = 1;
        $result = (new CalcService())->packPay($params, $member, $type);
        return $this->response->json($result);
    }

    /**
     * @DOC 订单转包裹取号
     */
    #[RequestMapping(path: 'order/waybill', methods: 'post')]
    public function orderToParcelWaybill(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = $this->orderService->orderToParcelWaybill($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 订单列表
     */
    #[RequestMapping(path: 'order/lists', methods: 'post')]
    public function orderList(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;

        $result = $this->orderService->workOrderLists($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 订单日志
     */
    #[RequestMapping(path: 'order/log', methods: 'post')]
    public function orderLog(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        if (empty($params['order_sys_sn'])) {
            throw new HomeException('请输入订单号');
        }
        $result['code']        = 200;
        $where['order_sys_sn'] = $params['order_sys_sn'];
        if (!empty($where)) {
            $orderParcelLogService = \Hyperf\Support\make(OrderParcelLogService::class);
            $result['msg']         = '查询成功';
            $result['data']        = $orderParcelLogService->LogOutput(where: $where);
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 订单改价
     */
    #[RequestMapping(path: 'order/change/price', methods: 'post')]
    public function orderChangePrice(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = $this->orderService->orderChangePrice($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 订单获取费用
     */
    #[RequestMapping(path: 'order/cost', methods: 'post')]
    public function orderCost(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = $this->orderService->orderCost($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 订单创建之间进行分析
     */
    #[RequestMapping(path: 'order/analysis', methods: 'post')]
    public function orderAnalysis(RequestInterface $request)
    {
        $params        = $request->all();
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($params,
            [
                'member_uid'           => ['required', 'numeric', Rule::exists('agent_member')->where(function ($query) use ($params, $member) {
                    $query->where('parent_agent_uid', $member['parent_agent_uid'])
                        ->where('parent_join_uid', $member['uid'])
                        ->where('member_uid', $params['member_uid']);
                })],
                'receiver'             => ['required', 'array'],
                'receiver.country_id'  => ['required', 'integer'],
                'receiver.province_id' => ['required', 'integer'],
                'receiver.province'    => ['nullable', 'string'],
                'receiver.city_id'     => ['nullable', 'integer'],
                'receiver.city'        => ['nullable', 'string'],
                'sender'               => ['required', 'array'],
                'sender.country_id'    => ['required', 'integer'],
                'sender.province_id'   => ['required', 'integer'],
                'sender.province'      => ['nullable', 'string'],
                'sender.city_id'       => ['nullable', 'integer'],
                'sender.city'          => ['nullable', 'string'],
            ], [
                'member_uid.required' => '请选择客户',
                'member_uid.numeric'  => '客户参数错误',
                'member_uid.exists'   => '客户参数错误',
                'receiver.required'   => '请选择收件人',
                'receiver.array'      => '收件人参数错误',
                'sender.required'     => '请选择寄件人',
                'sender.array'        => '寄件人参数错误',
            ]
        );

        // 获取线路
        $params['line_id'] = $this->orderService->matchLine($params, $member);
        // 获取选择用户信息
        $member = AgentMemberModel::query()
            ->where('parent_agent_uid', $member['parent_agent_uid'])
            ->where('parent_join_uid', $member['uid'])
            ->where('member_uid', $params['member_uid'])
            ->select(['member_uid as uid', 'parent_join_uid', 'role_id', 'parent_agent_uid'])
            ->first()->toArray();

        $params               = $LibValidation->validate($params,
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
                'item.*.item_record_sn'   => ['nullable', 'string'],
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

        return $this->response->json($result);
    }

    /**
     * @DOC 订单的收发件人地址信息
     */
    #[RequestMapping(path: 'order/address', methods: 'post')]
    public function address(RequestInterface $request, OrdersService $ordersService)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        if (empty($param['member_uid'])) {
            throw new HomeException('请选择客户');
        }
        $member = AgentMemberModel::query()
            ->where('parent_agent_uid', $member['parent_agent_uid'])
            ->where('parent_join_uid', $member['uid'])
            ->where('member_uid', $param['member_uid'])
            ->select(['member_uid as uid', 'parent_join_uid', 'role_id', 'parent_agent_uid'])
            ->first()->toArray();
        $result = $ordersService->address($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 新订单列表
     */
    #[RequestMapping(path: 'order/lists/new', methods: 'get,post')]
    public function listsPlatformNew(RequestInterface $request, OrdersService $ordersService): ResponseInterface
    {
        $param    = $request->all();
        $useWhere = $this->useWhere();
        $result   = $ordersService->orderParcelLists($param, $useWhere['where'], 'work');
        return $this->response->json($result);
    }


}
