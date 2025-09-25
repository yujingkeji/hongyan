<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Controller\Home\Orders;

use App\Common\Lib\Arr;
use App\Exception\HomeException;
use App\Model\AgentMemberModel;
use App\Model\OrderModel;
use App\Request\LibValidation;
use App\Service\AnalyseChannelService;
use App\Service\Cache\BaseCacheService;
use App\Service\OrderParcelLogService;
use App\Service\OrdersService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\Rule;
use Psr\Http\Message\ResponseInterface;


#[Controller(prefix: 'orders/order')]
class OrderController extends OrderBaseController
{
    #[Inject]
    protected BaseCacheService $baseCacheService;
    #[Inject]
    protected LibValidation $libValidation;

    /**
     * @DOC   订单详情
     * @param RequestInterface $request
     * @param OrdersService $ordersService
     * @return ResponseInterface
     * @Author wangfei
     * @date   2024/2/29 2024
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
     * @Doc
     * @Author wangfei
     * @Date 2024/6/28 下午6:34
     * @param RequestInterface $request
     */
    #[RequestMapping(path: 'analyse', methods: 'get,post')]
    public function analyse(RequestInterface $request)
    {
        $member = $request->UserInfo;
        $params = $request->all();
        #验证line_id是否存在
        $this->libValidation->validate($params,
            [
                'line_id' => ['required', 'integer', Rule::exists('member_line', 'line_id')->where(function ($query) use ($member) {
                    $query->where('uid', '=', $member['parent_agent_uid'])->where('status', 1);
                })],
            ],
            [
                'line_id.required' => '请确定线路',
                'line_id.exists'   => '您的线路已下架',
            ]
        );
        $params = $this->libValidation->validate($params,
            [
                'line_id'                 => ['required', 'bail', 'integer'],
                'product_id'              => ['required', 'integer', Rule::exists('product', 'pro_id')->where(function ($query) use ($params, $member) {
                    $query->where('member_uid', '=', $member['parent_agent_uid'])
                        ->where('line_id', '=', $params['line_id'])
                        ->where('status', 1);
                })],
                'item'                    => ['required', 'array'],
                'item.*.item_num'         => ['required', 'integer'],
                'item.*.sku_id'           => ['integer'],
                'item.*.item_record_sn'   => ['nullable'],
                'item.*.category_item_id' => ['required_without:item.*.item_sku_name', 'integer'],
                'item.*.item_sku_name'    => ['required_without:item.*.category_item_id', 'string'],
                'receiver'                => ['array'],
                'receiver.country_id'     => ['integer'],
                'receiver.province_id'    => ['integer'],
                'receiver.province'       => ['string'],
                'receiver.city_id'        => ['integer'],
                'receiver.city'           => ['string'],

            ],
            [
                'product_id.required'                      => '请确定物流产品',
                'product_id.exists'                        => '请选择物流产品',
                'line_id.required'                         => '请确定线路',
                'item.required'                            => '请选择商品',
                'line_id.exists'                           => '您的线路已下架',
                'item.*.category_item_id.required_without' => '请选择商品分类',
                'item.*.item_sku_name.required_without'    => '请输入商品分类名称',
                'item.*.category_item_id.integer'          => '请选择商品分类',
                'item.*.item_sku_name.string'              => '请输入商品分类名称',
            ]
        );

        $result = \Hyperf\Support\make(AnalyseChannelService::class)
            ->makeOrderAnalysis(params: $params, member: $member);
        return $this->response->json($result);
    }


    /**
     * @DOC 创建订单
     * @Name   add
     * @Author wangfei
     * @date   2023-09-12 2023
     * @param RequestInterface $request
     * @return array
     * @throws \Exception
     */
    #[RequestMapping(path: 'add', methods: 'get,post')]
    public function add(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '操作失败';
        $member         = $request->UserInfo;
        $params         = $request->all();
        switch ($member['role_id']) {
            default:
            case 1:
            case 2:
            case 3:
                throw new HomeException('仅仅客户本人才能编辑订单');
                break;
            case 4:
            case 5:

                break;
        }

        // 制单
        $result = (new OrdersService())->makeOrder($params, $member);
        // 推送自检
        if ($result['code'] == 200) {
            $AnalyseChannelService = \Hyperf\Support\make(AnalyseChannelService::class);
            if ($AnalyseChannelService->lPush([$result['data']['order_sys_sn']], $member['uid'])) {
                $result['code'] = 200;
                $result['msg']  = '创建成功、已加入自检';
            }
        }
        return $result;
    }


    /**
     * @throws \Exception
     */
    #[RequestMapping(path: 'edit', methods: 'get,post')]
    public function edit(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '操作失败';
        $member         = $request->UserInfo;
        $params         = $request->all();
        $result         = (new OrdersService())->orderEdit($params, $member);
        return $this->response->json($result);
    }


    /**
     * @DOC 订单列表（客户）
     */
    #[RequestMapping(path: 'lists', methods: 'post')]
    public function lists(RequestInterface $request, OrdersService $ordersService): ResponseInterface
    {
        $param    = $request->all();
        $useWhere = $this->useWhere();

        $where = $useWhere['where'];
        $order = OrderModel::query()->where($where);
        // 系统单号
        if (Arr::hasArr($param, 'order_sys_sn')) {
            $order = $order->where('order_sys_sn', $param['order_sys_sn']);
        }
        //自定义编号
        if (Arr::hasArr($param, 'user_custom_sn')) {
            $order = $order->where('user_custom_sn', $param['user_custom_sn']);
        }
        //批次号
        if (Arr::hasArr($param, 'batch_sn')) {
            $order = $order->where('batch_sn', $param['batch_sn']);
        }
        //线路
        if (Arr::hasArr($param, 'line_id')) {
            $order = $order->where('line_id', $param['line_id']);
        }

        //产品类型
        if (Arr::hasArr($param, 'pro_id')) {
            $order = $order->where('pro_id', $param['pro_id']);
        }

        if (Arr::hasArr($param, 'order_status')) {
            $orderStatus = explode(',', $param['order_status']);
            $order       = $order->whereIn('order_status', $orderStatus);
        } else {
            $order = $order->whereIn('order_status', [
                26,
                28,
                29]);//26：订单创建 28 ：校验完成,29：支付完成
        }
        //查询时间
        if (Arr::hasArr($param, [
            'start_time',
            'end_time'])) {
            $start_time = strtotime($param['start_time']);
            $end_time   = strtotime($param['end_time']);
            $order      = $order->where('add_time', '>=', $start_time)->where('add_time', '<=', $end_time);
        }
        //下单账号昵称
        if (Arr::hasArr($param, 'sender_nick')) {
            $uid   = $ordersService->joinUserCheck($param);
            $order = $order->where('member_uid', $uid);
        }
        $data = $order->with([
            'fromPlatform' => function ($query) {
                $query->select([
                    'cfg_id',
                    'title']);
            },
            'ware'         => function ($query) {
                $query->select(['ware_id', 'ware_code', 'ware_name', 'ware_no']);
            },
            'line'         => function ($query) {
                $query->select([
                    'line_id',
                    'line_name',
                    'send_country_id',
                    'send_country',
                    'target_country_id',
                    'target_country']);
            },
            'product'      => function ($query) {
                $query->select([
                    'pro_id',
                    'pro_name']);
            },
            'channel.import.channelSupervision',
            'member'       => function ($query) {
                $query->select([
                    'uid',
                    'user_name',
                    'nick_name']);
            },
            'joins'        => function ($query) {
                $query->select([
                    'uid',
                    'user_name',
                    'nick_name']);
            },
            'item'         => function ($query) {
                $query->select([
                    'order_sys_sn',
                    'item_num']);
            },
            'receiver'])->orderBy('add_time')->paginate($param['limit'] ?? 20);

        $list = $data->items();
        $list = $ordersService->orderListsHandle($list);
        return $this->response->json([
            'code' => 200,
            'msg'  => '查询成功',
            'data' => [
                'total' => $data->total(),
                'data'  => $list,]]);
    }

    /**
     * @DOC 异常列表（客户）
     */
    #[RequestMapping(path: 'exceptions', methods: 'post')]
    public function exceptions(RequestInterface $request, OrdersService $ordersService)
    {
        $param    = $request->all();
        $useWhere = $this->useWhere();
        $where    = [];
        // 系统单号
        if (Arr::hasArr($param, 'order_sys_sn')) {
            $where[] = [
                'order_sys_sn',
                '=',
                $param['order_sys_sn']];
        }
        //自定义编号
        if (Arr::hasArr($param, 'user_custom_sn')) {
            $where[] = [
                'user_custom_sn',
                '=',
                $param['user_custom_sn']];
        }
        //批次号
        if (Arr::hasArr($param, 'batch_sn')) {
            $where[] = [
                'batch_sn',
                '=',
                $param['batch_sn']];
        }
        //线路
        if (Arr::hasArr($param, 'line_id')) {
            $where[] = [
                'line_id',
                '=',
                $param['line_id']];
        }
        //产品类型
        if (Arr::hasArr($param, 'pro_id')) {
            $where[] = [
                'pro_id',
                '=',
                $param['pro_id']];
        }
        //查询时间
        if (Arr::hasArr($param, [
            'time_type',
            'start_time',
            'end_time'])) {
            $start_time = strtotime($param['start_time']);
            $end_time   = strtotime($param['end_time']);
            $where[]    = [
                $param['time_type'],
                '>=',
                $start_time];
            $where[]    = [
                $param['time_type'],
                '<=',
                $end_time];
        }
        $data = OrderModel::query()->with([
            'fromPlatform' => function ($query) {
                $query->select([
                    'cfg_id',
                    'title']);
            },
            'ware'         => function ($query) {
                $query->select([
                    'ware_id',
                    'ware_code',
                    'ware_name',
                    'ware_no']);
            },
            'line'         => function ($query) {
                $query->select([
                    'line_id',
                    'line_name',
                    'send_country_id',
                    'send_country',
                    'target_country_id',
                    'target_country']);
            },
            'product'      => function ($query) {
                $query->select([
                    'pro_id',
                    'pro_name']);
            },
            'member'       => function ($query) {
                $query->select([
                    'uid',
                    'user_name',
                    'nick_name']);
            },
            'joins'        => function ($query) {
                $query->select([
                    'uid',
                    'user_name',
                    'nick_name']);
            },
            'receiver',
            'item'         => function ($query) {
                $query->with([
                    'record' => function ($query) {
                        $query->with([
                            'goods' => function ($query) {
                                $query->select([
                                    'goods_base_id',
                                    'goods_name',
                                    'record_status',
                                    'brand_id',
                                    'brand_name',
                                    'brand_en']);
                            },
                            'cc'])->select([
                            'sku_code',
                            'goods_base_id',
                            'image_url',
                            'in_number',
                            'barcode',
                            'sku_id']);
                    }]);
            },
            'exception.item'])->where($where)->where($useWhere['where'])->whereIn('order_status', [27])->orderBy('add_time')->paginate($param['limit'] ?? 20);
        $list = $data->items();
        $list = $ordersService->orderListsHandle($list);
        return $this->response->json([
            'code' => 200,
            'msg'  => '查询成功',
            'data' => [
                'total' => $data->total(),
                'data'  => $list,]]);
    }

    /**
     * @DOC 订单列表（加盟商）
     */
    #[RequestMapping(path: 'lists/join', methods: 'post')]
    public function listsJoin(RequestInterface $request, OrdersService $ordersService)
    {
        $param    = $request->all();
        $useWhere = $this->useWhere();
        $order    = OrderModel::query()->where($useWhere['where']);

        // 系统单号
        if (Arr::hasArr($param, 'order_sys_sn')) {
            $order = $order->where('order_sys_sn', $param['order_sys_sn']);
        }
        //自定义编号
        if (Arr::hasArr($param, 'user_custom_sn')) {
            $order = $order->where('user_custom_sn', $param['user_custom_sn']);
        }
        //批次号
        if (Arr::hasArr($param, 'batch_sn')) {
            $order = $order->where('batch_sn', $param['batch_sn']);
        }
        //线路
        if (Arr::hasArr($param, 'line_id')) {
            $order = $order->where('line_id', $param['line_id']);
        }
        //产品类型
        if (Arr::hasArr($param, 'pro_id')) {
            $order = $order->where('pro_id', $param['pro_id']);
        }
        if (Arr::hasArr($param, 'order_status')) {
            $orderStatus = explode(',', $param['order_status']);
            $order       = $order->whereIn('order_status', $orderStatus);
        } else {
            //26：订单创建 28 ：校验完成,29：支付完成
            $order = $order->whereIn('order_status', [
                26,
                28,
                29]);
        }
        //查询时间
        if (Arr::hasArr($param, [
            'start_time',
            'end_time'])) {
            $start_time = strtotime($param['start_time']);
            $end_time   = strtotime($param['end_time']);
            $order      = $order->where('add_time', '>=', $start_time)->where('add_time', '<=', $end_time);
        }

        $data = $order->with([
            'fromPlatform' => function ($query) {
                $query->select([
                    'cfg_id',
                    'title']);
            },
            'ware'         => function ($query) {
                $query->select([
                    'ware_id',
                    'ware_code',
                    'ware_name',
                    'ware_no']);
            },
            'line'         => function ($query) {
                $query->select([
                    'line_id',
                    'line_name',
                    'send_country_id',
                    'send_country',
                    'target_country_id',
                    'target_country']);
            },
            'product'      => function ($query) {
                $query->select([
                    'pro_id',
                    'pro_name']);
            },
            'member'       => function ($query) {
                $query->select([
                    'uid',
                    'user_name',
                    'nick_name']);
            },
            'joins'        => function ($query) {
                $query->select([
                    'uid',
                    'user_name',
                    'nick_name']);
            },
            'item'         => function ($query) {
                $query->select([
                    'order_sys_sn',
                    'item_num']);
            },
            'receiver'])->orderBy('add_time')->paginate($param['limit'] ?? 20);
        $list = $data->items();
        $list = $ordersService->orderListsHandle($list);
        return $this->response->json([
            'code' => 200,
            'msg'  => '查询成功',
            'data' => [
                'total' => $data->total(),
                'data'  => $list,]]);
    }

    /**
     * @DOC 异常列表（加盟商）
     */
    #[RequestMapping(path: 'exceptions/join', methods: 'post')]
    public function exceptionsJoin(RequestInterface $request, OrdersService $ordersService)
    {
        $param    = $request->all();
        $useWhere = $this->useWhere();
        $where    = [];
        // 系统单号
        if (Arr::hasArr($param, 'order_sys_sn')) {
            $where[] = [
                'order_sys_sn',
                '=',
                $param['order_sys_sn']];
        }
        //自定义编号
        if (Arr::hasArr($param, 'user_custom_sn')) {
            $where[] = [
                'user_custom_sn',
                '=',
                $param['user_custom_sn']];
        }
        //批次号
        if (Arr::hasArr($param, 'batch_sn')) {
            $where[] = [
                'batch_sn',
                '=',
                $param['batch_sn']];
        }
        //线路
        if (Arr::hasArr($param, 'line_id')) {
            $where[] = [
                'line_id',
                '=',
                $param['line_id']];
        }
        //订单类型
        if (Arr::hasArr($param, 'order_type')) {
            $where[] = [
                'order_type',
                '=',
                $param['order_type']];
        }
        //送货方式
        if (Arr::hasArr($param, 'delivery_cfg')) {
            $where[] = [
                'delivery_cfg',
                '=',
                $param['delivery_cfg']];
        }
        //产品类型
        if (Arr::hasArr($param, 'pro_id')) {
            $where[] = [
                'pro_id',
                '=',
                $param['pro_id']];
        }
        //查询时间
        if (Arr::hasArr($param, [
            'time_type',
            'start_time',
            'end_time'])) {
            $start_time = strtotime($param['start_time']);
            $end_time   = strtotime($param['end_time']);
            $where[]    = [
                $param['time_type'],
                '>=',
                $start_time];
            $where[]    = [
                $param['time_type'],
                '<=',
                $end_time];
        }
        //下单账号昵称
        if (Arr::hasArr($param, 'member_uid')) {
            $where[] = [
                'member_uid',
                '=',
                $param['member_uid']];
        }
        $data = OrderModel::query()->with([
            'fromPlatform' => function ($query) {
                $query->select([
                    'cfg_id',
                    'title']);
            },
            'ware'         => function ($query) {
                $query->select([
                    'ware_id',
                    'ware_code',
                    'ware_name',
                    'ware_no']);
            },
            'line'         => function ($query) {
                $query->select([
                    'line_id',
                    'line_name',
                    'send_country_id',
                    'send_country',
                    'target_country_id',
                    'target_country']);
            },
            'product'      => function ($query) {
                $query->select([
                    'pro_id',
                    'pro_name']);
            },
            'member'       => function ($query) {
                $query->select([
                    'uid',
                    'user_name',
                    'nick_name']);
            },
            'joins'        => function ($query) {
                $query->select([
                    'uid',
                    'user_name',
                    'nick_name']);
            },
            'receiver',
            'item'         => function ($query) {
                $query->select([
                    'order_sys_sn',
                    'item_num']);
            },
            'exception.item'])->where($where)->where($useWhere['where'])->whereIn('order_status', [27])->paginate($param['limit'] ?? 20);
        $list = $data->items();
        $list = $ordersService->orderListsHandle($list);
        return $this->response->json([
            'code' => 200,
            'msg'  => '查询成功',
            'data' => [
                'total' => $data->total(),
                'data'  => $list,]]);
    }

    /**
     * @DOC 订单列表（平台）
     */
    #[RequestMapping(path: 'lists/platform', methods: 'post')]
    public function listsPlatform(RequestInterface $request, OrdersService $ordersService): ResponseInterface
    {
        $param    = $request->all();
        $useWhere = $this->useWhere();
        $member   = $request->UserInfo;

        $where = $useWhere['where'];
        $order = OrderModel::query()->where($where);
        // 系统单号
        if (Arr::hasArr($param, 'order_sys_sn')) {
            $order = $order->where('order_sys_sn', $param['order_sys_sn']);
        }
        //自定义编号
        if (Arr::hasArr($param, 'user_custom_sn')) {
            $order = $order->where('user_custom_sn', $param['user_custom_sn']);
        }
        //批次号
        if (Arr::hasArr($param, 'batch_sn')) {
            $order = $order->where('batch_sn', $param['batch_sn']);
        }
        //线路
        if (Arr::hasArr($param, 'line_id')) {
            $order = $order->where('line_id', $param['line_id']);
        }
        //订单类型
        if (Arr::hasArr($param, 'order_type')) {
            $where[] = [
                'order_type',
                '=',
                $param['order_type']];
        }

        //产品类型
        if (Arr::hasArr($param, 'pro_id')) {
            $order = $order->where('pro_id', $param['pro_id']);
        }

        if (Arr::hasArr($param, 'order_status')) {
            $orderStatus = explode(',', $param['order_status']);
            $order       = $order->whereIn('order_status', $orderStatus);
        } else {
            $order = $order->whereIn('order_status', [
                26,
                28,
                29]);//26：订单创建 28 ：校验完成,29：支付完成
        }
        //查询时间
        if (Arr::hasArr($param, [
            'start_time',
            'end_time'])) {
            $start_time = strtotime($param['start_time']);
            $end_time   = strtotime($param['end_time']);
            $order      = $order->where('add_time', '>=', $start_time)->where('add_time', '<=', $end_time);
        }
        //下单账号昵称
        if (Arr::hasArr($param, 'sender_nick')) {
            $uid   = $ordersService->joinUserCheck($param);
            $order = $order->where('member_uid', $uid);
        }
        //下单账号昵称
        if (Arr::hasArr($param, 'member_uid')) {
            $memberWhere[]       = [
                'member_uid',
                '=',
                $param['member_uid']];
            $memberWhere[]       = [
                'parent_agent_uid',
                '=',
                $member['parent_agent_uid']];
            $agentPlatformMember = AgentMemberModel::where($memberWhere)->first();
            if (!$agentPlatformMember) {
                throw new HomeException('未查询到用户信息');
            }
            switch ($agentPlatformMember['role_id']) {
                case 1:
                case 2:
                    break;
                case 3:
                    $order = $order->where('parent_join_uid', $param['member_uid']);
                    break;
                default:
                case 4:
                case 5:
                    $order = $order->where('member_uid', $param['member_uid']);
                    break;
            }
        }

        $data = $order->with([
            'fromPlatform' => function ($query) {
                $query->select([
                    'cfg_id',
                    'title']);
            },
            'ware'         => function ($query) {
                $query->select([
                    'ware_id',
                    'ware_code',
                    'ware_name',
                    'ware_no']);
            },
            'line'         => function ($query) {
                $query->select([
                    'line_id',
                    'line_name',
                    'send_country_id',
                    'send_country',
                    'target_country_id',
                    'target_country']);
            },
            'product'      => function ($query) {
                $query->select([
                    'pro_id',
                    'pro_name']);
            },
            'member'       => function ($query) {
                $query->select([
                    'uid',
                    'user_name',
                    'nick_name']);
            },
            'joins'        => function ($query) {
                $query->select([
                    'uid',
                    'user_name',
                    'nick_name']);
            },
            'item'         => function ($query) {
                $query->select([
                    'order_sys_sn',
                    'item_num']);
            },
            'receiver'])->orderBy('add_time')->paginate($param['limit'] ?? 20);

        $list = $data->items();
        $list = $ordersService->orderListsHandle($list);
        return $this->response->json([
            'code' => 200,
            'msg'  => '查询成功',
            'data' => [
                'total' => $data->total(),
                'data'  => $list,]]);
    }

    /**
     * @DOC 异常列表（平台）
     */
    #[RequestMapping(path: 'exceptions/platform', methods: 'post')]
    public function exceptionsPlatform(RequestInterface $request, OrdersService $ordersService)
    {
        $param    = $request->all();
        $useWhere = $this->useWhere();
        $member   = $request->UserInfo;
        $where    = [];
        // 系统单号
        if (Arr::hasArr($param, 'order_sys_sn')) {
            $where[] = [
                'order_sys_sn',
                '=',
                $param['order_sys_sn']];
        }
        //自定义编号
        if (Arr::hasArr($param, 'user_custom_sn')) {
            $where[] = [
                'user_custom_sn',
                '=',
                $param['user_custom_sn']];
        }
        //批次号
        if (Arr::hasArr($param, 'batch_sn')) {
            $where[] = [
                'batch_sn',
                '=',
                $param['batch_sn']];
        }
        //线路
        if (Arr::hasArr($param, 'line_id')) {
            $where[] = [
                'line_id',
                '=',
                $param['line_id']];
        }
        //产品类型
        if (Arr::hasArr($param, 'pro_id')) {
            $where[] = [
                'pro_id',
                '=',
                $param['pro_id']];
        }
        //查询时间
        if (Arr::hasArr($param, [
            'time_type',
            'start_time',
            'end_time'])) {
            $start_time = strtotime($param['start_time']);
            $end_time   = strtotime($param['end_time']);
            $where[]    = [
                $param['time_type'],
                '>=',
                $start_time];
            $where[]    = [
                $param['time_type'],
                '<=',
                $end_time];
        }
        //下单账号昵称
        if (Arr::hasArr($param, 'member_uid')) {
            $memberWhere[]       = [
                'member_uid',
                '=',
                $param['member_uid']];
            $memberWhere[]       = [
                'parent_agent_uid',
                '=',
                $member['parent_agent_uid']];
            $agentPlatformMember = AgentMemberModel::where($memberWhere)->first();
            if (!$agentPlatformMember) {
                throw new HomeException('未查询到用户信息');
            }
            switch ($agentPlatformMember['role_id']) {
                case 1:
                case 2:
                    break;
                case 3:
                    $where[] = [
                        'parent_join_uid',
                        '=',
                        $param['member_uid']];
                    break;
                default:
                case 4:
                case 5:
                    $where[] = [
                        'member_uid',
                        '=',
                        $param['member_uid']];
                    break;
            }
        }
        $data = OrderModel::query()->with([
            'fromPlatform' => function ($query) {
                $query->select([
                    'cfg_id',
                    'title']);
            },
            'ware'         => function ($query) {
                $query->select([
                    'ware_id',
                    'ware_code',
                    'ware_name',
                    'ware_no']);
            },
            'line'         => function ($query) {
                $query->select([
                    'line_id',
                    'line_name',
                    'send_country_id',
                    'send_country',
                    'target_country_id',
                    'target_country']);
            },
            'product'      => function ($query) {
                $query->select([
                    'pro_id',
                    'pro_name']);
            },
            'member'       => function ($query) {
                $query->select([
                    'uid',
                    'user_name',
                    'nick_name']);
            },
            'joins'        => function ($query) {
                $query->select([
                    'uid',
                    'user_name',
                    'nick_name']);
            },
            'receiver',
            'item'         => function ($query) {
                $query->select([
                    'order_sys_sn',
                    'item_num']);
            },
            'exception.item'])->where($where)->where($useWhere['where'])->whereIn('order_status', [27])->paginate($param['limit'] ?? 20);
        $list = $data->items();
        $list = $ordersService->orderListsHandle($list);
        return $this->response->json([
            'code' => 200,
            'msg'  => '查询成功',
            'data' => [
                'total' => $data->total(),
                'data'  => $list,]]);
    }

    /**
     * @DOC 新订单列表
     */
    #[RequestMapping(path: 'lists/platform/new', methods: 'get,post')]
    public function listsPlatformNew(RequestInterface $request, OrdersService $ordersService): ResponseInterface
    {
        $param    = $request->all();
        $useWhere = $this->useWhere();
        $result   = $ordersService->orderParcelLists($param, $useWhere['where']);
        return $this->response->json($result);
    }

    /**
     * @DOC 订单转包裹取号
     */
    #[RequestMapping(path: 'waybill/sync', methods: 'post')]
    public function waybillSync(RequestInterface $request, OrdersService $ordersService)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = $ordersService->orderToParcelWaybill($param, $member);
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
     * @DOC  订单包裹缓存
     * @Name   log
     * @Author wangfei
     * @date   2023-07-27 2023
     */
    #[RequestMapping(path: 'log', methods: 'get,post')]
    public function log(RequestInterface $request)
    {
        $param                 = $request->all();
        $validationFactory     = \Hyperf\Support\make(ValidatorFactoryInterface::class);
        $validator             = $validationFactory->make($param,
            [
                'order_sys_sn' => ['required_without:transport_sn'],
                'transport_sn' => ['required_without:order_sys_sn'],
            ],
            [
                'order_sys_sn.required' => 'order_sys_sn  and transport_sn must be filled in one of them',
                'transport_sn.required' => 'order_sys_sn  and transport_sn must be filled in one of them'
            ]
        );
        $data                  = $validator->validated();
        $where                 = [];
        $where['order_sys_sn'] = $data['order_sys_sn'];
        $member                = $request->UserInfo;
        switch ($member['role_id']) {
            case 1:
            case 2:
                $where['parent_agent_uid'] = $member['uid'];
                break;
            case 3:
                $where['parent_join_uid'] = $member['uid'];
                break;
            case 4:
            case 5:
                $where['member_uid'] = $member['uid'];
                break;
            default:
                throw new HomeException('无权限查询订单日志');
                break;
        }


        $result['code'] = 200;
        $result['msg']  = '查询成功-结果为空';
        if (!empty($where)) {
            $orderParcelLogService = \Hyperf\Support\make(OrderParcelLogService::class);
            $result['msg']         = '查询成功';
            $result['data']        = $orderParcelLogService->LogOutput(where: $where);
        }
        return $this->response->json($result);
    }



}
