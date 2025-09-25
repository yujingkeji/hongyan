<?php

declare(strict_types=1);
/**换单操作
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Controller\Home\Parcels;

use App\Common\Lib\Arr;
use App\Exception\HomeException;
use App\Model\OrderCostMemberModel;
use App\Model\OrderModel;
use App\Model\ParcelExceptionItemModel;
use App\Model\ParcelModel;
use App\Request\BlRequest;
use App\Request\LibValidation;
use App\Service\BlService;
use App\Request\OrdersRequest;
use App\Service\Cache\BaseCacheService;
use App\Service\ParcelService;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\Rule;
use function App\Common\batchUpdateSql;


#[Controller(prefix: 'parcels/swap')]
class SwapController extends ParcelBaseController
{
    #[Inject]
    protected BaseCacheService $baseCacheService;
    #[Inject]
    protected ValidatorFactoryInterface $validationFactory;

    /**
     * @DOC 上传换单结果
     * @Name   upload
     * @Author wangfei
     * @date   2023/11/16 2023
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    #[RequestMapping(path: 'upload/result', methods: 'post')]
    public function upload(RequestInterface $request)
    {
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params        = $LibValidation->validate(params: $request->all(), rules: [
            '*.order_sys_sn'  => ['required', 'string'],
            '*.transport_sn'  => ['required', 'string'],
            '*.logistics_cod' => ['required', 'string'],
            // '*.overwrite_add' => ['required', 'integer', Rule::in([1, 2])],//覆盖还是新增 1：新增，2：覆盖
        ]);

        switch ($this->request->UserInfo['role_id']) {
            default:
                throw new HomeException('禁止非平台代理访问', 201);
                break;
            case 1:
                break;
        }
        $order_sys_snArr = array_column($params, 'order_sys_sn');

        $parcelDb = ParcelModel::query()->whereIn('order_sys_sn', $order_sys_snArr)->select(['order_sys_sn', 'member_uid', 'parent_join_uid', 'parent_agent_uid', 'logistics_platform_code'])->get()->toArray();

        $parcelDb   = array_column($parcelDb, null, 'order_sys_sn');
        $insertData = [];
        $time       = time();
        $parcelDel  = [];
        $parcelErr  = [];
        foreach ($params as $key => $item) {
            if (Arr::hasArr($parcelDb, $item['order_sys_sn'])) {
//                if ($parcelDb[$item['order_sys_sn']]['logistics_platform_code'] == 'ddwl') {
                $parcel                     = $parcelDb[$item['order_sys_sn']];
                $item['add_time']           = $time;
                $item['member_uid']         = $parcel['member_uid'];
                $item['parent_join_uid']    = $parcel['parent_join_uid'];
                $item['parent_agent_uid']   = $parcel['parent_agent_uid'];
                $item['operate_member_uid'] = $member['uid'];
                $item['operate_child_uid']  = $member['child_uid'];
                $insertData[]               = $item;
                $parcelDel[]                = $parcel['order_sys_sn'];
//                } else {
//                    $parcelErr[$key]['order_sys_sn'] = $item['order_sys_sn'];
//                    $parcelErr[$key]['reason']       = '该包裹无需换单';
//                }
            } else {
                $parcelErr[$key]['order_sys_sn'] = $item['order_sys_sn'];
                $parcelErr[$key]['reason']       = '导入的单号不正确';
            }
        }

        Db::beginTransaction();
        try {
            if (!empty($insertData)) {
                Db::table('parcel_swap')
                    ->where('operate_member_uid', '=', $member['uid'])
                    ->whereIn('order_sys_sn', $parcelDel)
                    ->delete();
                Db::table('parcel_swap')->insert($insertData);

            }
            Db::commit();
            $result['code'] = 200;
            $result['msg']  = '操作完成';
        } catch (\Throwable $e) {
            Db::rollBack();
            $result['code'] = 200;
            $result['msg']  = '错误：' . $e->getMessage();
        }
        $result['err'] = $parcelErr;
        return $this->response->json($result);
    }


    /**
     * @DOC 到处换单数据
     * @Name   export
     * @Author wangfei
     * @date   2023/11/16 2023
     * @param RequestInterface $request
     */
    #[RequestMapping(path: 'export', methods: 'post')]
    public function export(RequestInterface $request)
    {
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params        = $LibValidation->validate(params: $request->all(), rules: [
            'page'              => ['required', 'integer'],
            'limit'             => ['required', 'integer'],
            'sn_field'          => ['string', Rule::in(['order_sys_sn', 'transport_sn'])],
            'sn_field_value'    => ['string'],
            'line_id'           => ['integer'],
            'customer_type'     => ['string', Rule::in(['member', 'join'])],
            'ware_id'           => ['integer'],
            'time'              => ['array'],
            'time.channel_node' => ['string', Rule::in(['send'])],
            'time.time_type'    => ['string', Rule::in(['send_time'])],
            'time.start_time'   => ['date_format:Y-m-d H:i:s'],
            'time.end_time'     => ['after:time.start_time', 'date_format:Y-m-d H:i:s'],
            'swap_status'       => ['integer'],
            // '*.overwrite_add' => ['required', 'integer', Rule::in([1, 2])],//覆盖还是新增 1：新增，2：覆盖
        ]);

        switch ($this->request->UserInfo['role_id']) {
            default:
                throw new HomeException('禁止非平台代理访问', 201);
                break;
            case 1:
                break;
        }
        $perPage = Arr::hasArr($params, 'limit') ? $params['limit'] : 15;

        $query = ParcelModel::query();
        if (Arr::hasArr($params, ['sn_field', 'sn_field_value'])) {
            $query->where($params['sn_field'], '=', $params['sn_field_value']);
        }
        if (Arr::hasArr($params, ['line_id'])) {
            $query->where('line_id', '=', $params['line_id']);
        }
        if (Arr::hasArr($params, ['ware_id'])) {
            $query->where('ware_id', '=', $params['ware_id']);
        }
        if (Arr::hasArr($params, ['customer_type', 'customer_type_value'])) {
            switch ($params['customer_type']) {
                case 'member':
                    $query->where('member_uid', '=', $params['customer_type_value']);
                    break;
                case 'join':
                    $query->where('parent_join_uid', '=', $params['customer_type_value']);
                    break;
            }
        }

        if (Arr::hasArr($params, 'time')) {
            $timeParams = $params['time'];
            if (Arr::hasArr($timeParams, ['channel_node', 'time_type', 'start_time', 'end_time'])) {
                switch ($timeParams['channel_node']) {
                    case 'send'://发货集货
                        $query->whereHas('send', function ($query) use ($timeParams) {
                            $query->whereBetween($timeParams['time_type'], [strtotime($timeParams['start_time']), strtotime($timeParams['end_time'])])
                                ->select(['order_sys_sn']);
                        });
                        break;
                }

            }
        }

        //是否换单
        if (Arr::hasArr($params, 'swap_status')) {
            switch ($params['swap_status']) {
                case 1: //未换单
                    $query->whereDoesntHave('swap', function ($query) use ($params) {
                        $query->select(['order_sys_sn']);
                    });
                    break;
                case 2: //已换单
                    $query->whereHas('swap', function ($query) use ($params) {
                        $query->select(['order_sys_sn']);
                    });
                    break;
            }
        }

        $painter = $query->with(['send', 'order' => function ($query) {
            $query->with('sender')->select(['order_sys_sn', 'batch_sn']);
        }, 'receiver', 'item'])->paginate($perPage)->toArray();

        $painter['code'] = 200;
        $painter['msg']  = '查询成功';
        $painter['data'] = $this->handleOrderCost($painter['data'], $member);
        return $this->response->json($painter);
    }

    protected function handleOrderCost(array $Orders, array $member)
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
        foreach ($Orders as $key => $order) {
            if (!empty($order['receiver'])) {
                $Orders[$key]['receiver'] = $this->handleDecrypt($order['receiver']);
            }
            $Orders[$key]['sender'] = [];
            if (!empty($order['order']['sender'])) {
                $Orders[$key]['sender'] = $this->handleDecrypt($order['order']['sender']);
            }

            if (isset($LineCache[$order['line_id']])) {
                $Orders[$key]['line'] = $LineCache[$order['line_id']];
            }
            if (isset($WareHouseCache[$order['ware_id']])) {
                $Orders[$key]['ware'] = $WareHouseCache[$order['ware_id']];
            }
            if (Arr::hasArr($order, 'pro_id') && isset($ProductCache[$order['pro_id']])) {
                $Orders[$key]['product'] = $ProductCache[$order['pro_id']];
            }

            if (Arr::hasArr($order, 'channel_id') && isset($ChannelCache[$order['channel_id']])) {
                $Orders[$key]['channel'] = $ChannelCache[$order['channel_id']];
            }

            if (Arr::hasArr($order, 'order_type') && isset($OrderType[$order['order_type']])) {
                $Orders[$key]['order_type'] = $OrderType[$order['order_type']];
            }
        }
        return $Orders;
    }
}
