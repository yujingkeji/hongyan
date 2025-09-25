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

namespace App\Controller\Work;


use App\Common\Lib\Arr;
use App\Exception\HomeException;
use App\Model\DeliveryStationModel;
use App\Model\ParcelSendModel;
use App\Request\LibValidation;
use App\Service\BlService;
use App\Service\Cache\BaseCacheService;
use App\Service\OrderParcelLogService;
use App\Service\ParcelService;
use App\Service\ParcelWeightCalcService;
use App\Service\PredictionParcelService;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\Rule;

#[Controller(prefix: "/", server: 'httpWork')]
class ParcelController extends WorkBaseController
{

    #[Inject]
    protected BaseCacheService $baseCacheService;

    #[Inject]
    protected ParcelService $parcelService;

    #[Inject]
    protected BlService $blService;

    #[Inject]
    protected ValidatorFactoryInterface $validationFactory;

    /**
     * @DOC   查询包裹信息
     * return: type 1:直邮 2:集运
     */
    #[RequestMapping(path: 'parcel/query', methods: 'post')]
    public function query(RequestInterface $request)
    {
        $result['code'] = 200;
        $result['msg']  = '查询成功';
        $param          = $this->request->all();
        $member         = $request->UserInfo;
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $param          = $LibValidation->validate($param,
            [
                'order_sys_sn' => 'required|min:6',
            ],
            [
                'order_sys_sn.required' => '请输入订单号|包裹号|预报号',
                'order_sys_sn.min'      => '订单号至少6位',
            ],
        );
        try {
            $queryParcelDb = $this->parcelService->parcelSendDb($member['parent_agent_uid'], $param['order_sys_sn']);
            foreach ($queryParcelDb as $k => $order) {
                foreach ($order['item'] as $k1 => $goods) {
                    $goods['category'] = [];
                    if (Arr::hasArr($goods, 'category_item_id')) {
                        $recordCategoryGoodsCache = $this->baseCacheService->recordCategoryGoodsCache();
                        $handleGoodsCategory      = Arr::handleGoodsCategory($recordCategoryGoodsCache, $goods['category_item_id']);
                        unset($handleGoodsCategory['data']);
                        $goods['category'] = $handleGoodsCategory;
                    }
                    $queryParcelDb[$k]['item'][$k1] = $goods;
                }

            }
            $result['data'] = $queryParcelDb;
        } catch (HomeException $e) {
            $result['msg'] = $e->getMessage();
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 工作台入库
     */
    #[RequestMapping(path: 'parcel/confirm', methods: 'get,post')]
    public function confirm(RequestInterface $request)
    {
        $param                      = $this->request->all();
        $feeItemsCache              = $this->baseCacheService->ConfigChannelSendFeeItemsCache();
        $charge_code                = array_column($feeItemsCache, 'cfg_id');
        $ConfigParcelExceptionCache = $this->baseCacheService->SendExceptionCache();
        $exception_code             = array_column($ConfigParcelExceptionCache, 'cfg_id');

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($param,
            [
                'order_sys_sn'           => 'nullable|min:10',
                'send_station_sn'        => 'required',
                'weight'                 => 'required|numeric|min:0.01',
                'storage_location_id'    => 'integer', // 库位
                'item'                   => 'required|array', // 入库商品
                'length'                 => 'nullable|required_with:width,height|numeric|min:1',
                'width'                  => 'nullable|required_with:length,height|numeric|min:1',
                'height'                 => 'nullable|required_with:width,length|numeric|min:1',
                'confirm_desc'           => 'nullable',
                'exception'              => '',
                'exception.code'         => ['array', 'distinct', Rule::in($exception_code)],
                'cost_item'              => 'array',
                'cost_item.*.code'       => ['numeric', 'distinct', Rule::in($charge_code)],
                'cost_item.*.amount'     => ['numeric', 'required_with:cost_item.*.code'],
                'check'                  => ['nullable', 'array'], // 验货信息
                'check.check_id'         => ['nullable', 'integer'], // 验货ID
                'check.picture'          => ['nullable', 'array'], // 验货图片
                'check.video'            => ['nullable', 'array'], // 验货视频
                'check.admin_check_desc' => ['nullable', 'string'], // 验货备注
            ],
            [
                'order_sys_sn.required'            => '请输入订单号',
                'cost_item.numeric'                => '增值收费项必须数组',
                'exception.code.array'             => '异常数据必须数组',
                'exception.code.distinct'          => '异常数据存在重复项',
                'exception.code.in'                => '异常编码不存在',
                'cost_item.*.code.numeric'         => '增值收费编码错误',
                'cost_item.*.code.distinct'        => '增值收费项存在重复项',
                'cost_item.*.code.in'              => '增值收费编码不存在',
                'cost_item.*.amount.numeric'       => '增值收费金额必须数值',
                'cost_item.*.amount.required_with' => '增值收费金额必须填写',
                'weight.required'                  => '包裹重量必填',
                'weight.numeric'                   => '包裹重量必须数值',
                'weight.min'                       => '包裹重量不小于0.01kg',
                'length.required_with'             => '包裹长宽高必须填写',
                'length.numeric'                   => '包裹长宽高必须数值',
                'length.min'                       => '包裹长宽高不小于1CM',
                'width.required_with'              => '包裹长宽高必须填写',
                'width.numeric'                    => '包裹长宽高必须数值',
                'width.min'                        => '包裹长宽高不小于1CM',
                'height.required_with'             => '包裹长宽高必须填写',
                'height.numeric'                   => '包裹长宽高必须数值',
                'height.min'                       => '包裹长宽高不小于1CM',
                'send_station_sn.required'         => '请输入集运站编码',
                'item.required'                    => '请输入商品信息',
                'item.array'                       => '商品信息必须数组',
            ]
        );
        $member        = $request->UserInfo;
        switch ($member['role_id']) {
            case 1:
            case 2:
            case 10:
                break;
            default:
                throw new HomeException('角色权限不足，无法操作收货');
                break;
        }
        try {
            // 查询delivery_station
            $sendStationDb = $this->parcelService->confirmDeliveryStationDb(send_station_sn: $param['send_station_sn']);
            if (empty($sendStationDb)) {
                throw new HomeException('未查询的包裹单号');
            }
            // 处理仓库是否正确
            $this->parcelService->checkParcelWarehouse($sendStationDb, $member);
            switch ($sendStationDb['parcel_type']) {
                // 集运
                case DeliveryStationModel::TYPE_COLLECT:
                    if (!empty($deliveryStationDb['item_exception'])) {
                        throw new HomeException('入库失败，当前预报包裹存在商品异常');
                    }
                    // 处理集运订单
                    return $this->parcelService->confirmDeliveryStationDbHandle($param, $member, $sendStationDb);
                    break;
                // 直邮
                case DeliveryStationModel::TYPE_DIRECT:
                    // 直邮订单
                    $confirmParcelDb = $this->parcelService->confirmParcelDb(member_uid: $member['uid'], order_sys_sn: $sendStationDb['order_sys_sn'], charge_code: $charge_code);
                    if (empty($confirmParcelDb)) {
                        throw new HomeException('未查询到订单信息');
                    }
                    return $this->parcelService->confirmParcelSendDbHandle($param, $member, $confirmParcelDb);
                    break;
                default:
                    throw new HomeException('包裹错误，请联系管理员');
            }
        } catch (\Throwable $e) {
            return ['code' => 201, 'msg' => $e->getMessage(), 'data' => ['file' => $e->getFile(), 'line' => $e->getLine()]];
        }

    }

    /**
     * @DOC 验收商品
     */
    #[RequestMapping(path: 'parcel/check/goods', methods: 'post')]
    public function checkGoods(RequestInterface $request)
    {
        $params        = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'send_station_sn' => 'required',
                'order_sys_sn'    => 'nullable',
                'item'            => 'required|array',
            ],
            [
                'send_station_sn.required' => '请输入包裹编码',
                'item.required'            => '请输入商品信息',
                'item.array'               => '商品信息必须数组',
            ]
        );

        $stationDb = DeliveryStationModel::where('send_station_sn', $params['send_station_sn'])->first();
        if (empty($stationDb)) {
            throw new HomeException('包裹编码不存在');
        }
        $stationDb = $stationDb->toArray();
        $member    = $request->UserInfo;
        $this->parcelService->checkParcelWarehouse($stationDb, $member);
        if (!in_array($stationDb['delivery_status'], [DeliveryStationModel::STATUS_WAIT_IN, DeliveryStationModel::STATUS_RECEIVE, DeliveryStationModel::STATUS_IN])) {
            throw new HomeException('验收失败，仅限待入库/已验货可验收');
        }
        // 存储工作台验收商品信息
        [$deliveryStationDb, $deliveryParcelItem, $exception_item] = $this->parcelService->deliveryStationParcel($param, $member, DeliveryStationModel::STATUS_WAIT_IN, $param['order_sys_sn']);
        Db::beginTransaction();
        try {
            // 更新预报单 已收货
//            Db::table('delivery_station')->where('send_station_sn', $param['send_station_sn'])
//                ->where('delivery_status', DeliveryStationModel::STATUS_WAIT_IN)->update(['delivery_status' => DeliveryStationModel::STATUS_WAIT_IN]);
            if (!empty($deliveryStationDb)) {
                Db::table('delivery_station_parcel')->updateOrInsert(['station_parcel_sn' => $deliveryStationDb['station_parcel_sn']], $deliveryStationDb);
            }
            if (!empty($deliveryParcelItem)) {
                Db::table('delivery_station_parcel_item')->where('station_parcel_sn', $deliveryStationDb['station_parcel_sn'])->delete();
                Db::table('delivery_station_parcel_item')->insert($deliveryParcelItem);
            }
            // 删除上次验收异常
            Db::table('delivery_station_parcel_item_exception')->where('station_parcel_sn', $deliveryStationDb['station_parcel_sn'])->delete();
            if (!empty($exception_item)) {
                Db::table('delivery_station_parcel_item_exception')->insert($exception_item);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollBack();
            echo $e->getMessage() . $e->getLine() . $e->getFile();
            return ['code' => 201, 'msg' => '验收失败', 'data' => [$e->getMessage() . $e->getLine() . $e->getFile()]];
        }
        return ['code' => 200, 'msg' => '操作成功'];
    }

    /**
     * @DOC 配舱发货查询
     * @Name   querySend
     * @Author wangfei
     * @date   2023-07-26 2023
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    #[RequestMapping(path: 'parcel/querySend', methods: 'get,post')]
    public function querySend(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '查询失败';
        $result['data'] = [];
        $param          = $this->request->all();
        $validator      = $this->validationFactory->make(
            $param,
            [
                'transport_sn' => 'required|min:5'
            ],
            [
                'transport_sn.required' => 'bl_main_sn  must be required',
                'transport_sn.min'      => 'min size of :attribute must be :min'

            ]
        );
        if ($validator->fails()) {
            throw new HomeException($validator->errors()->first(), 201);
        }
        $member       = $request->UserInfo;
        $sendParcelDb = $this->parcelService->sendParcelDb(transport_sn: $param['transport_sn'], member: $member);
        if (empty($sendParcelDb)) {
            throw new HomeException('未查询到可出库订单');
        }
        if (Arr::hasArr($sendParcelDb, 'exception') || Arr::hasArr($sendParcelDb, 'order_exception')) {
            foreach ($sendParcelDb['exception'] as $exceptionError) {
                if ($exceptionError['status'] != 3) {
                    throw new HomeException('该订单存在异常、禁止发出', 201);
                }
            }
            foreach ($sendParcelDb['order_exception'] as $exceptionError) {
                if ($exceptionError['status'] != 3) {
                    throw new HomeException('该订单存在异常、禁止发出', 201);
                }
            }
        }
        // 查询状态是否可以入提单
        if ($sendParcelDb['parcel_send_status'] != ParcelSendModel::OUT_BOUND) {
            throw new HomeException('订单仅可出库发出');
        }
        // 检测订单是否未取号，及未付款
        if ($sendParcelDb['order_sys_sn'] == $sendParcelDb['transport_sn']) {
            throw new HomeException('当前订单未取号，禁止发出');
        }

        if (Arr::hasArr($sendParcelDb, 'cost_member_item')) {
            $memberItem = array_column($sendParcelDb['cost_member_item'], null, 'payment_status');
            if (isset($memberItem[0])) {
                throw new HomeException('该订单存在未付款的收费项、禁止发出', 201);
            }
        }
        if (!empty($sendParcelDb)) {
            $result['code'] = 200;
            $result['msg']  = '查询成功';
            $result['data'] = $sendParcelDb;
        }
        return $this->response->json($result);
    }

    /**
     * @DOC   配舱发货
     * @Name   send
     * @Author wangfei
     * @date   2023-07-24 2023
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    #[RequestMapping(path: 'parcel/send', methods: 'get,post')]
    public function send(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '发货失败';
        $param          = $this->request->all();
        $validator      = $this->validationFactory->make(
            $param,
            [
                'bl_sn'        => 'required|min:10',
                'weight'       => 'numeric',
                'transport_sn' => 'required|min:5'
            ],
            [
                'bl_main_sn.required'   => 'order_sys_sn  must be required',
                'transport_sn.required' => 'bl_main_sn  must be required',
                'bl_main_sn.numeric'    => 'bl_main_sn must be numeric',
                'bl_main_sn.min'        => 'min size of :attribute must be :min',
                'transport_sn.min'      => 'min size of :attribute must be :min'

            ]
        );
        if ($validator->fails()) {
            throw new HomeException($validator->errors()->first(), 201);
        }
        $member       = $request->UserInfo;
        $sendParcelDb = $this->parcelService->sendParcelDb(transport_sn: $param['transport_sn'], member: $member);
        if (Arr::hasArr($sendParcelDb, 'exception')) {
            foreach ($sendParcelDb['exception'] as $exceptionError) {
                if ($exceptionError['status'] != 3) {
                    throw new HomeException('该订单存在异常、禁止发出', 201);
                }
            }
        }

        if (Arr::hasArr($sendParcelDb, 'cost_member_item')) {
            $memberItem = array_column($sendParcelDb['cost_member_item'], null, 'payment_status');
            if (isset($memberItem[0])) {
                throw new HomeException('该订单存在未付款的收费项、禁止发出', 201);
            }
        }

        //获取提单数据：
//        $blWhere['member_uid']       = $member['uid'];
        $blWhere['parent_agent_uid'] = $member['parent_agent_uid'];
        $blWhere['bl_sn']            = $param['bl_sn'];
        $blService                   = \Hyperf\Support\make(BlService::class);
        $blCheckResult               = $blService->blCheck($blWhere);
        if (empty($blCheckResult)) {
            throw new HomeException('当前提单不存在、禁止配舱操作', 201);
        }
        if (isset($blCheckResult['bl_send_status']) && $blCheckResult['bl_send_status'] == 2) {
            throw new HomeException('当前提单已完成截单、禁止配舱操作', 201);
        }

        $order_sys_sn = $sendParcelDb['order_sys_sn'];
        //判断是否提交过补费计算
        //第一次发货的时候，采取计算运费事项
        $logger = $this->container->get(LoggerFactory::class)->get('log', 'AsyncSupplementWeightCalcProcess');
        if ($sendParcelDb['send_status'] == 0) {
            //如果不存在补重 code 说明需要重新计算
            $parcelCalcWeight = $this->parcelService->parcelCalcWeight(parcelDb: $sendParcelDb, weight: $param['weight']);
            if ($parcelCalcWeight['calc']) {
                $parcelWeightCalcService = \Hyperf\Support\make(ParcelWeightCalcService::class, [$logger]);
                $parcelWeightCalcService
                    ->lPush(order_sys_sn: $parcelCalcWeight['order_sys_sn'], member_uid: $parcelCalcWeight['member_uid'], memberWeight: $parcelCalcWeight['member_max_weight'], joinWeight: $parcelCalcWeight['join_max_weight']);
            }
        }

        //
        $parcelSendUpdate = [
            'parcel_send_status' => 78,//config表 model=40
            'send_status'        => 1,
            'send_time'          => time(),
            'send_weight'        => $param['weight'],
            'bl_sn'              => $blCheckResult['bl_sn'],

        ];

        // 查询包裹是否入库
        $parcelSendDb = ParcelSendModel::where('order_sys_sn', $order_sys_sn)->first();
        if (empty($parcelSendDb)) {
            throw new HomeException('当前包裹未入库、禁止配舱操作', 201);
        }

        //回写提单分单号
        $partItemSn = $blCheckResult['part_item_sn'];
        array_push($partItemSn, $param['transport_sn']);
        $partItemSn = array_unique($partItemSn);
        unset($BlUpdate);
        $BlUpdate['part_item_sn'] = json_encode($partItemSn, JSON_UNESCAPED_UNICODE);
        $msg                      = '';
        Db::beginTransaction();
        try {
            Db::table('parcel_send')->where('order_sys_sn', '=', $order_sys_sn)->update($parcelSendUpdate);
            if (!empty($partItemSn)) {
                Db::table('bl')->where('bl_sn', '=', $blCheckResult['bl_sn'])->update($BlUpdate);
            }
            Db::commit();
            $msg = '配舱发货成功';
            if (isset($param['weight']) && $param['weight'] > 0) {
                $msg .= ',重量为' . $param['weight'] . 'Kg';
            }
            $result['code'] = 200;
            $result['msg']  = '配舱发货完成';

        } catch (\Throwable $e) {
            Db::rollBack();
            $msg = '错误：' . $e->getMessage();
            throw new HomeException($msg);
        }
        unset($parcelUpdate);
        try {
            $OrderParcelLogService  = \Hyperf\Support\make(OrderParcelLogService::class);
            $opMember               = $this->request->UserInfo;
            $opMember['member_uid'] = $member['uid'];
            // 丢入任务，切换到下一个节点：
            if ($result['code'] == 200) {

                //检测提单节点是否存在数据，不存在补充
                $BlNodeCacheCheck = $this->blService->BlNodeCacheCheck(blSn: $blCheckResult['bl_sn'], node_cfg_id: $sendParcelDb['node_cfg_id'], op_member_uid: $sendParcelDb['op_member_uid']);
                if (!$BlNodeCacheCheck) {
                    $BlNodeData['bl_sn']         = $blCheckResult['bl_sn'];
                    $BlNodeData['node_cfg_id']   = $sendParcelDb['node_cfg_id'];
                    $BlNodeData['op_member_uid'] = $sendParcelDb['op_member_uid'];
                    $BlNodeData['sort']          = $sendParcelDb['sort'];
                    $BlNodeData['status']        = 1;
                    $BlNodeData['op_start_time'] = time();
                    Db::table('bl_node')->insert($BlNodeData);
                    unset($BlNodeData);
                }

            }
//            $OrderParcelLogService->insert($sendParcelDb, $opMember, $msg, []);
        } catch (\Throwable $e) {
            throw new HomeException($e->getMessage());
        }
        return $this->response->json($result);
    }

    //移除发货
    #[RequestMapping(path: 'parcel/removeSend', methods: 'get,post')]
    public function removeSend(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '移除失败';
        $param          = $this->request->all();
        $validator      = $this->validationFactory->make(
            $param,
            [
                'bl_sn'        => 'required|min:10',
                'transport_sn' => 'required|min:5'
            ],
            [
                'bl_sn.required'        => 'order_sys_sn  must be required',
                'transport_sn.required' => 'bl_main_sn  must be required',
                'bl_sn.min'             => 'min size of :attribute must be :min',
                'transport_sn.min'      => 'min size of :attribute must be :min'
            ]
        );
        if ($validator->fails()) {
            throw new HomeException($validator->errors()->first(), 201);
        }
        $data         = $validator->validated();
        $member       = $request->UserInfo;
        $sendParcelDb = $this->parcelService->sendParcelDb(transport_sn: $param['transport_sn'], member: $member);
        if (empty($sendParcelDb)) {
            throw new HomeException('当前订单不存在、禁止移除操作', 201);
        }


        //获取提单数据：
        unset($blWhere);
//        $blWhere['member_uid']       = $member['uid'];
        $blWhere['parent_agent_uid'] = $member['parent_agent_uid'];
        $blWhere['bl_sn']            = $param['bl_sn'];
        $blService                   = \Hyperf\Support\make(BlService::class);
        $blCheckResult               = $blService->blCheck($blWhere);
        if (empty($blCheckResult)) {
            throw new HomeException('当前提单不存在、禁止配舱操作', 201);
        }

        if ($sendParcelDb['bl_sn'] !== $blCheckResult['bl_sn']) {
            throw new HomeException('提单号与包裹提单号不一致、禁止移除操作', 201);
        }

        if ($blCheckResult['bl_send_status'] == 2) {
            throw new HomeException('当前提单已完成截单、禁止配舱操作', 201);
        }


        //移除包裹的时候，发出状态清楚
        unset($parcelSendUpdate);
        $parcelSendUpdate = [
            'send_status' => 0,
            'send_time'   => 0,
            'send_weight' => 0,
            'bl_sn'       => 0
        ];

        // 判断返回状态
        if (!empty($sendParcelDb['delivery_station'])) {
            switch ($sendParcelDb['delivery_station']['parcel_type']) {
                case 26102:
                case 26101:
                    $parcelSendUpdate['parcel_send_status'] = 70;
                    break;
            }
        }
        //回写提单分单号
        $partItemSn = $blCheckResult['part_item_sn'];
        foreach ($partItemSn as $key => $val) {
            if ($val == $param['transport_sn']) {
                unset($partItemSn[$key]);
                break;
            }
        }
        $partItemSn = array_unique($partItemSn);
        unset($BlUpdate);
        $BlUpdate['part_item_sn'] = json_encode($partItemSn, JSON_UNESCAPED_UNICODE);
        Db::beginTransaction();
        try {
            Db::table('parcel_send')->where('order_sys_sn', '=', $sendParcelDb['order_sys_sn'])->update($parcelSendUpdate);
            Db::table('parcel_export')->where('order_sys_sn', '=', $sendParcelDb['order_sys_sn'])->delete();
            unset($parcelSendWhere);
            if (!empty($partItemSn)) {
                Db::table('bl')->where('bl_sn', '=', $blCheckResult['bl_sn'])->update($BlUpdate);
            }
            Db::commit();
            $result['code'] = 200;
            $result['msg']  = '移除完成';
        } catch (\Throwable $e) {
            Db::rollBack();
            $msg = '错误：' . $e->getMessage();
            throw new HomeException($msg);
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 发往货站
     */
    #[RequestMapping(path: 'parcel/deliver/station', methods: 'get,post')]
    public function deliverStation(RequestInterface $request)
    {
        $param = $request->all();
        if (empty($param['member_uid'])) {
            throw new HomeException('请选择客户');
        }
        $member                    = $request->UserInfo;
        $member['parent_join_uid'] = $member['uid'];
        $member['uid']             = $param['member_uid'];
        $member['role_id']         = 5;
        $result                    = \Hyperf\Support\make(ParcelService::class)->parcelSend($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 包裹列表
     */
    #[RequestMapping(path: 'parcel/list', methods: 'get,post')]
    public function parcelList(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(ParcelService::class)->parcelList($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 获取包裹详情
     */
    #[RequestMapping(path: 'parcel/detail', methods: 'get,post')]
    public function parcelDetail(RequestInterface $request)
    {
        $param  = $request->all();
        $result = \Hyperf\Support\make(ParcelService::class)->parcelDetail($param);
        return $this->response->json($result);
    }

    /**
     * @DOC 验货完成
     */
    #[RequestMapping(path: 'parcel/check', methods: 'post')]
    public function parcelCheck(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->parcelCheck($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 创建认领包裹
     */
    #[RequestMapping(path: 'parcel/claim/add', methods: 'post')]
    public function createClaimParcel(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->createClaimParcel($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 工作台待验货|待入库|异常任务列表
     */
    #[RequestMapping(path: 'parcel/task/list', methods: 'get,post')]
    public function parcelTaskList(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->parcelTaskList($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 待出库列表
     */
    #[RequestMapping(path: 'parcel/out/list', methods: 'get,post')]
    public function parcelOutList(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->parcelOutList($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 工作台任务列表统计
     */
    #[RequestMapping(path: 'parcel/task/count', methods: 'get,post')]
    public function parcelTaskCount(RequestInterface $request)
    {
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->parcelTaskCount($member);
        return $this->response->json($result);
    }

    /**
     * @DOC 打包页面，订单号/包裹号搜索
     */
    #[RequestMapping(path: 'parcel/pack/list', methods: 'post')]
    public function ParcelPackList(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->ParcelPackList($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 查询包裹单条查询
     */
    #[RequestMapping(path: 'parcel/pack/detail', methods: 'post')]
    public function ParcelPackDetail(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->ParcelPackDetail($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 拣货详情中扫描包裹
     */
    #[RequestMapping(path: 'parcel/pack/scan', methods: 'post')]
    public function ParcelPickScan(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->ParcelPickScan($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 打包确定
     */
    #[RequestMapping(path: 'parcel/pack/confirm', methods: 'post')]
    public function ParcelPackConfirm(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->ParcelPackConfirm($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 打包取号
     */
    #[RequestMapping(path: 'parcel/pack/number', methods: 'post')]
    public function ParcelPackNumber(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->ParcelPackNumber($params, $member);
        return $this->response->json($result);
    }


    /**
     * @DOC 工作台历史列表统计
     */
    #[RequestMapping(path: 'parcel/history/count', methods: 'get,post')]
    public function parcelHistoryCount(RequestInterface $request)
    {
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->parcelHistoryCount($member);
        return $this->response->json($result);
    }

    /**
     * @DOC 工作台历史列表
     */
    #[RequestMapping(path: 'parcel/history', methods: 'get,post')]
    public function parcelHistory(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->parcelHistory($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 已出库列表
     */
    #[RequestMapping(path: 'parcel/history/out/list', methods: 'get,post')]
    public function parcelHistoryOutList(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->parcelHistoryOutList($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 获取预报包裹详情
     */
    #[RequestMapping(path: 'parcel/prediction/detail', methods: 'get,post')]
    public function parcelPredictionDetail(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->parcelDetail($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 拣货任务列表
     */
    #[RequestMapping(path: 'parcel/pick/lists', methods: 'post')]
    public function parcelPickLists(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->parcelPickLists($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 拣货任务新增
     */
    #[RequestMapping(path: 'parcel/pick/add', methods: 'post')]
    public function parcelPickAdd(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->parcelPickAdd($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 拣货任务详情
     */
    #[RequestMapping(path: 'parcel/pick/detail', methods: 'post')]
    public function parcelPickDetail(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->parcelPickDetail($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 获取拣货任务所有打印拣货单的单号
     */
    #[RequestMapping(path: 'parcel/pick/print/list', methods: 'post')]
    public function parcelPickPrintList(RequestInterface $request)
    {
        $param  = $request->all();
        $result = \Hyperf\Support\make(PredictionParcelService::class)->parcelPickPrintList($param);
        return $this->response->json($result);
    }


    /**
     * @DOC 拣货任务单个取消任务
     */
    #[RequestMapping(path: 'parcel/pick/single/removal', methods: 'post')]
    public function parcelPickSingleRemoval(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->parcelPickSingleRemoval($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 拣货任务取消任务
     */
    #[RequestMapping(path: 'parcel/pick/cancel', methods: 'post')]
    public function parcelPickCancel(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->parcelPickCancel($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 任务列表未取号
     */
    #[RequestMapping(path: 'parcel/unnumbered', methods: 'post')]
    public function parcelUnnumbered(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->parcelUnnumbered($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 未取号列表：填写面单
     */
    #[RequestMapping(path: 'parcel/unnumbered/fill', methods: 'post')]
    public function parcelUnnumberedFill(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->parcelUnnumberedFill($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 未取号列表：取号
     */
    #[RequestMapping(path: 'parcel/unnumbered/take', methods: 'post')]
    public function parcelUnnumberedTake(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->parcelUnnumberedTake($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 未取号列表：导入面单
     */
    #[RequestMapping(path: 'parcel/unnumbered/import', methods: 'post')]
    public function parcelUnnumberedImport(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->parcelUnnumberedImport($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 任务列表未备案
     */
    #[RequestMapping(path: 'parcel/unrecorded', methods: 'post')]
    public function parcelUnrecorded(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->parcelUnrecorded($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 任务列表可出库
     */
    #[RequestMapping(path: 'parcel/outbound', methods: 'post')]
    public function parcelOutbound(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->parcelOutbound($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 复核：任务编号
     */
    #[RequestMapping(path: 'parcel/review', methods: 'post')]
    public function parcelReview(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->parcelReview($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 复核：查询拣货任务包裹号详情
     */
    #[RequestMapping(path: 'parcel/review/detail', methods: 'post')]
    public function parcelReviewDetail(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->parcelReviewDetail($param, $member);
        return $this->response->json($result);
    }


}
