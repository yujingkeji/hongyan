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

namespace App\Controller\Home\Orders;

use App\Common\Lib\Arr;
use App\Common\Lib\UserDefinedIdGenerator;
use App\Exception\HomeException;
use App\Model\ParcelModel;
use App\Model\ParcelSendModel;
use App\Request\OrdersRequest;
use App\Service\Cache\BaseCacheService;
use App\Service\OrdersService;
use App\Service\ParcelService;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\Rule;


#[Controller(prefix: 'orders/parcel')]
class ParcelController extends OrderBaseController
{
    #[Inject]
    protected BaseCacheService $baseCacheService;
    #[Inject]
    protected ValidatorFactoryInterface $validationFactory;


    //包裹分析
    #[RequestMapping(path: 'analyse', methods: 'get,post')]
    public function analyse(RequestInterface $request)
    {
        $param  = $request->all();
        $result = (new OrdersService())->analyse($param);
        return $this->response->json($result);
    }

    #[RequestMapping(path: 'details', methods: 'get,post')]
    public function details(RequestInterface $request, ParcelService $parcelService)
    {
        $result['code'] = 201;
        $result['msg']  = '操作失败';
        $validator      = $this->validationFactory->make(
            $request->all(), ['order_sys_sn' => 'required|string|bail'],
            [
                'order_sys_sn.required' => '系统单号必填',
                'order_sys_sn.string'   => 'order_sys_sn is string',
            ]
        );
        if ($validator->fails()) {
            throw new HomeException($validator->errors()->first(), 201);
        }
        $data                  = $validator->validated();
        $where['order_sys_sn'] = $data['order_sys_sn'];
        $member                = $request->UserInfo;
        switch ($member['role_id']) {
            case 1:
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
                throw new HomeException('无权限访问包裹详情');
                break;
        }

        $parcelDb = $parcelService->parcelDb(where: $where);

        if (!empty($parcelDb)) {
            $result['code'] = 200;
            $result['msg']  = '查询成功';

            $parcelDbHandle = $parcelService->parcelDbHandle(ParcelDb: $parcelDb);
            $result['data'] = $parcelService->parcelItemHandle($parcelDbHandle);
        }

        return $result;
    }

    #[RequestMapping(path: 'lists', methods: 'get,post')]
    public function lists(RequestInterface $request, ParcelService $parcelService)
    {
        $param         = $this->request->all();
        $MemberRequest = $this->container->get(OrdersRequest::class);
        $MemberRequest->scene('parcelList')->validated();
        $perPage  = Arr::hasArr($param, 'limit') ? $param['limit'] : 15;
        $useWhere = $this->useWhere();
        $where    = $useWhere['where'];

        $member = $this->request->UserInfo;
        $query  = ParcelModel::query()->where($where);
        if (Arr::hasArr($param, 'order_sys_sn')) {
            $query = $query->where('order_sys_sn', '=', $param['order_sys_sn']);
        }
        if (Arr::hasArr($param, 'transport_sn')) {
            $query = $query->where('transport_sn', '=', $param['transport_sn']);
        }
        if (Arr::hasArr($param, 'batch_sn')) {
            $query = $query->where('batch_sn', '=', $param['batch_sn']);
        }
        if (Arr::hasArr($param, 'ware_id')) {
            $query = $query->where('ware_id', '=', $param['ware_id']);
        }
        if (Arr::hasArr($param, 'line_id')) {
            $query = $query->where('line_id', '=', $param['line_id']);
        }
        # 用户
        if (Arr::hasArr($param, 'member_uid')) {
            $query = $query->where('member_uid', '=', $param['member_uid']);
        }
        # 加盟商
        if (Arr::hasArr($param, 'parent_join_uid')) {
            $query = $query->where('parent_join_uid', '=', $param['parent_join_uid']);
        }
        # 物流公司
        if (Arr::hasArr($param, 'logistics_platform_id')) {
            $query = $query->where('logistics_platform_id', '=', $param['logistics_platform_id']);
        }
        # 判断是否换单
        if (Arr::hasArr($param, 'is_swap')) {
            if ($param['is_swap'] == 1) {
                $query = $query->whereDoesntHave('swap');
            }
            if ($param['is_swap'] == 2) {
                $query = $query->whereHas('swap');
            }
        }
        # 判断导入换单时间
        if (Arr::hasArr($param, 'swap_start_time') && Arr::hasArr($param, 'swap_end_time')) {
            $query = $query->whereHas('swap', function ($swap) use ($param) {
                $swap->where('add_time', '>=', strtotime($param['swap_start_time']))
                    ->where('add_time', '<=', strtotime($param['swap_end_time']));
            });
        }
        if (isset($param['parcel_status']) && $param['parcel_status'] > 0) {

            //状态：15001-》补交运费，15000-》其他异常（除补交运费以外），待入库：56，已入库：65，全部：0
            switch ($param['parcel_status']) {
                case 15000; //其他异常
                    $query = $query->whereHas('exception', function ($parcel) {
                        $parcel->whereIn('status', [0, 2])->select(['order_sys_sn']);
                    });
                    break;
                case 15001: //补费   //补交运费 ，存在为付款的，即为需要补交运费
                    //TODO 存在异常的时候，不显示补交运费
                    $query = $query->doesntHave('exception', 'and', function ($parcel) {
                        $parcel->whereIn('status', [0, 2])->select(['order_sys_sn']);
                    });
                    $query = $query->whereHas('cost_member_item', function ($parcel) {
                        $parcel->where('payment_status', '=', 0)->select(['order_sys_sn']);
                    });

                    break;
                default:
                    $query = $query->where('parcel_status', '=', $param['parcel_status']);
                    break;
            }

        }
        if (Arr::hasArr($param, 'product_id')) {
            $query = $query->where('product_id', '=', $param['product_id']);
        }

        if (Arr::hasArr($param, 'start_time') && Arr::hasArr($param, 'end_time')) {
            $query = $query->where('add_time', '>=', strtotime($param['start_time']));
            $query = $query->where('add_time', '<=', strtotime($param['end_time']));
        }

        $filed = ['order_sys_sn', 'transport_sn', 'member_uid', 'parent_join_uid', 'parent_agent_uid', 'batch_sn', 'line_id', 'ware_id', 'parcel_status',
                  'parcel_weight', 'product_id', 'channel_id', 'logistics_platform_id as logistics_id', 'logistics_platform_name as logistics_name', 'add_time', 'print_count', 'print_time'];

        $painter = $query->select(['order_sys_sn'])->paginate($perPage)->toArray();

        if (!empty($painter['data'])) {
            $painter['data'] = ParcelModel::whereIn('order_sys_sn', $painter['data'])->with([
                'exception'   => function ($query) {
                    $query->whereIn("status", [0, 1, 2])->select(['order_sys_sn', 'code', 'msg', 'add_time']);
                },
                'cost_member' => function ($query) {
                    $query->with(['item' => function ($query) {
                        $query->select(['order_sys_sn', 'payment_sn', 'charge_code', 'charge_code_name', 'payment_status', 'payment_currency', 'original_total_fee', 'payment_amount', 'exchange_rate', 'exchange_amount', 'income_currency']);
                    }])->select(['order_sys_sn', 'member_join_weight']);
                },
                'order'       => function ($query) {
                    $query->with(['sender' => function ($query) {
                        $query->select(['sender_id', 'batch_sn', 'order_sys_sn', 'name', 'phone', 'mobile', 'country', 'country_id', 'province', 'province_id', 'city', 'city_id', 'district', 'district_id', 'street', 'street_id', 'address']);
                    }, 'receiver'          => function ($query) {
                        $query->select(['receiver_id', 'order_sys_sn', 'name', 'phone', 'mobile', 'country', 'province', 'city', 'city_id', 'province_id', 'country_id', 'district', 'district_id', 'street', 'street_id', 'address', 'zip', 'address_status']);
                    }])->select(['order_sys_sn', 'batch_sn', 'member_uid', 'parent_join_uid', 'line_id', 'ware_id', 'order_type', 'order_status', 'pro_id', 'add_time']);
                },
                'swap'        => function ($query) {
                    $query->select(['order_sys_sn', 'transport_sn', 'logistics_cod']);
                }])->select($filed)->get()->toArray();
        }
        $painter['code'] = 200;
        $painter['msg']  = '加载完成';
        $painter['data'] = $parcelService->handleBatchParcels($painter['data'], $member);

        return $this->response->json($painter);
    }


    /**
     * @DOC   发往货站
     * @Name   deliverStation
     * @Author wangfei
     * @date   2023-06-29 2023
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'deliverStation', methods: 'get,post')]
    public function deliverStation(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(ParcelService::class)->parcelSend($param, $member);
        return $this->response->json($result);
    }
}
