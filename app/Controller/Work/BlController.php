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
use App\Common\Lib\Str;
use App\Common\Lib\UserDefinedIdGenerator;
use App\Exception\HomeException;
use App\Model\BlModel;
use App\Model\ParcelSendModel;
use App\Request\BlRequest;
use App\Request\OrdersRequest;
use App\Service\BlService;
use App\Service\Cache\BaseCacheService;
use App\Service\CostMemberService;
use App\Service\ParcelExceptionService;
use App\Service\ParcelService;
use App\Service\QueueService;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\Rule;

#[Controller(prefix: "/", server: 'httpWork')]
class BlController extends WorkBaseController
{
    #[Inject]
    protected BaseCacheService $baseCacheService;

    #[Inject]
    protected ParcelService $parcelService;

    #[Inject]
    protected ValidatorFactoryInterface $validationFactory;

    /**
     * @DOC  提单查询
     * @Name   query
     * @Author wangfei
     * @date   2023-07-25 2023
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'bl/query', methods: 'post')]
    public function query(RequestInterface $request)
    {
        $result['code'] = 200;
        $result['msg']  = '查询成功';
        $param          = $this->request->all();
        $validator      = $this->validationFactory->make(
            $param,
            [
                'bl_main_sn' => 'required|min:1',
            ],
            [
                'bl_main_sn.required' => 'bl_main_sn  must be required'
            ]
        );
        if ($validator->fails()) {
            throw new HomeException($validator->errors()->first(), 201);
        }
        //$validator->validated();
        $member['member_uid']       = $this->member_uid;
        $member['parent_join_uid']  = $this->parent_join_uid;
        $member['parent_agent_uid'] = $this->parent_agent_uid;
        $queryBlDb                  = $this->parcelService->queryBl(blMainSn: $param['bl_main_sn'], member: $member);
        $result['data']             = $queryBlDb;
        return $this->response->json($result);
    }

    /**
     * @DOC   提单明细查询
     * @Name   queryItem
     * @Author wangfei
     * @date   2023-07-28 2023
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'bl/queryItem', methods: 'post')]
    public function queryItem(RequestInterface $request)
    {
        $result['code'] = 200;
        $result['msg']  = '查询成功';
        $result['data'] = [];
        $param          = $this->request->all();
        $validator      = $this->validationFactory->make(
            $param,
            [
                'bl_sn' => 'required|min:1',
                "page"  => "required|numeric",
                "limit" => "required|numeric",
            ],
            [
                'bl_main_sn.required' => 'bl_main_sn  must be required',
                'page.required'       => 'page  must be required',
                'limit.required'      => 'limit  must be required',
                'page.numeric'        => 'page  must be numeric',
                'limit.numeric'       => 'limit  must be numeric',
            ]
        );
        if ($validator->fails()) {
            throw new HomeException($validator->errors()->first(), 201);
        }
        $filed          = ['order_sys_sn', 'transport_sn', 'channel_id', 'send_status', 'send_time', 'send_weight'];
        $data           = $validator->validated();
        $perPage        = Arr::hasArr($data, 'limit') ? $data['limit'] : 15;
        $member         = $request->UserInfo;
        $where['bl_sn'] = $data['bl_sn'];
        $blService      = \Hyperf\Support\make(BlService::class);
        $blCheckResult  = $blService->blCheck($where);
        $result['bl']   = $blCheckResult;
        try {
            $ParcelSendDb = ParcelSendModel::query()->where($where)
                ->with([
                    'channel'                 => function ($query) {
                        $query->select(['channel_id', 'channel_name']);
                    },
                    'import'                  => function ($query) {
                        $query->with([
                            'port' => function ($query) {
                                $query->select(['port_id', 'name']);
                            }
                        ])->select(['channel_id', 'port_id']);
                    },
                    'parcel'                  => function ($query) {
                        $query->with([
                            'line'    => function ($query) {
                                $query->select(['line_id', 'line_name']);
                            },
                            'product' => function ($query) {
                                $query->select(['pro_id', 'pro_name']);
                            },
                        ])->select(['order_sys_sn', 'line_id', 'product_id']);
                    },
                    'pack'                    => function ($query) {
                        $query->select(['order_sys_sn', 'weight', 'length', 'width', 'height']);
                    },
                    'delivery_station_parcel' => function ($query) {
                        $query->select(['order_sys_sn', 'weight', 'length', 'width', 'height']);
                    }])
                ->select($filed)->paginate($perPage);

            if (!empty($ParcelSendDb)) {
                $ParcelSendData = $this->handleQueryItem($ParcelSendDb->items());
                $result['data'] = [
                    'total' => $ParcelSendDb->total(),
                    'data'  => $ParcelSendData,
                    //                    'query' => $ParcelSendDb,
                ];
            }
        } catch (\Exception $e) {
            $result['code'] = 201;
            $result['msg']  = $e->getMessage() . $e->getFile() . $e->getLine();
            return $this->response->json($result);
        }
        return $this->response->json($result);
    }

    public function handleQueryItem($ParcelSendData)
    {
        $data = [];
        foreach ($ParcelSendData as $datum) {
            $value           = [
                'order_sys_sn' => $datum['order_sys_sn'],
                'transport_sn' => $datum['transport_sn'],
                'line'         => $datum['parcel']['line']['line_name'],
                'product'      => $datum['parcel']['product']['pro_name'],
                'channel'      => $datum['channel']['channel_name'],
                'port'         => $datum['import']['port']['name'],
            ];
            $value['weight'] = !empty($datum['pack']) ? $datum['pack']['weight'] : $datum['delivery_station_parcel']['weight'];
            $value['volume'] = !empty($datum['pack']) ? $datum['pack']['length'] * $datum['pack']['width'] * $datum['pack']['height']
                : $datum['delivery_station_parcel']['length'] * $datum['delivery_station_parcel']['width'] * $datum['delivery_station_parcel']['height'];
            $data[]          = $value;
        }
        return $data;
    }


    /**
     * @DOC   订单结单
     * @Name   done
     * @Author wangfei
     * @date   2023-07-28 2023
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    #[RequestMapping(path: 'bl/done', methods: 'post')]
    public function done(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '操作失败';
        $param          = $this->request->all();
        $MemberRequest  = $this->container->get(BlRequest::class);
        $doneData       = $MemberRequest->scene('done')->validated();
        $useWhere       = $this->useWhere();
        switch ($this->request->UserInfo['role_id']) {
            default:
                throw new HomeException('禁止非平台代理访问、创建提单等', 201);
                break;
            case 1:
            case 2:
            case 10:
                $blService = \Hyperf\Support\make(BlService::class);
                if ($blService->NodeDone(blSn: $doneData['bl_sn'], op_member_uid: $this->request->UserInfo['parent_agent_uid'], node: 'send')) {
                    $bool           = $blService->lPushBlDone(bl_sn: $doneData['bl_sn'], table: 'parcel_send');
                    $result['code'] = 200;
                    $result['msg']  = '操作成功';
                }
                break;
        }
        return $this->response->json($result);
    }


    /**
     * @DOC 生成单号
     */
    #[RequestMapping(path: 'bl/singe/number', methods: 'get,post')]
    public function singeNumber(RequestInterface $request)
    {
        $member         = $request->UserInfo;
        $generator      = \Hyperf\Support\make(UserDefinedIdGenerator::class);
        $singeNumber    = $generator->generate($member['uid']);
        $result['code'] = 200;
        $result['data'] = (string)$singeNumber;
        return $this->response->json($result);
    }

    /**
     * @DOC 箱型箱量
     */
    #[RequestMapping(path: 'bl/box', methods: 'get,post')]
    public function ConfigBoxCache(RequestInterface $request)
    {
        $result['code'] = 200;
        $result['data'] = $this->baseCacheService->ConfigBoxCache();
        return $this->response->json($result);
    }

    /**
     * @DOC 提单数量单位 17200
     */
    #[RequestMapping(path: 'bl/unit', methods: 'post')]
    public function ConfigBlUnitCache()
    {
        $result['code'] = 200;
        $result['msg']  = '查询成功';
        $result['data'] = array_values($this->baseCacheService->ConfigPidCache(17200));
        return $this->response->json($result);
    }

    /**
     * @DOC 运输条款 17600
     */
    #[RequestMapping(path: 'bl/transit/clause', methods: 'post')]
    public function ConfigTransitClauseCache()
    {
        $result['code'] = 200;
        $result['msg']  = '查询成功';
        $result['data'] = array_values($this->baseCacheService->ConfigPidCache(17600));
        return $this->response->json($result);
    }

    /**
     * @DOC 付款方式 17800
     */
    #[RequestMapping(path: 'bl/pay/type', methods: 'post')]
    public function ConfigPayTypeCache()
    {
        $result['code'] = 200;
        $result['msg']  = '查询成功';
        $result['data'] = array_values($this->baseCacheService->ConfigPidCache(17800));
        return $this->response->json($result);
    }

    /**
     * @DOC 提单形式 17900
     */
    #[RequestMapping(path: 'bl/form', methods: 'post')]
    public function ConfigBlformCache()
    {
        $result['code'] = 200;
        $result['msg']  = '查询成功';
        $result['data'] = array_values($this->baseCacheService->ConfigPidCache(17900));
        return $this->response->json($result);
    }

    /**
     * @DOC 提单列表
     */
    #[RequestMapping(path: 'bl/lists', methods: 'get,post')]
    public function lists(RequestInterface $request)
    {
        $param     = $this->request->all();
        $member    = $request->UserInfo;
        $blService = \Hyperf\Support\make(BlService::class);
        $result    = $blService->blLists($param, $member);

        return $this->response->json($result);
    }

    /**
     * @DOC 提单新增
     */
    #[RequestMapping(path: 'bl/add', methods: 'get,post')]
    public function blAdd(RequestInterface $request)
    {
        $param         = $this->request->all();
        $useWhere      = $this->useWhere();
        $where         = $useWhere['where'];
        $member        = $request->UserInfo;
        $member['uid'] = $member['parent_agent_uid'];
        $blService     = \Hyperf\Support\make(BlService::class);
        $result        = $blService->addBl($param, $member, $where);
        return $this->response->json($result);
    }

    /**
     * @DOC 提单编辑
     */
    #[RequestMapping(path: 'bl/edit', methods: 'get,post')]
    public function edit(RequestInterface $request)
    {
        $param         = $this->request->all();
        $member        = $request->UserInfo;
        $member['uid'] = $member['parent_agent_uid'];
        $blService     = \Hyperf\Support\make(BlService::class);
        $result        = $blService->editBl($param, $member);
        return $this->response->json($result);
    }


    /**
     * @DOC 提单废弃
     */
    #[RequestMapping(path: 'bl/del', methods: 'get,post')]
    public function delBl(RequestInterface $request)
    {
        $param     = $this->request->all();
        $member    = $request->UserInfo;
        $blService = \Hyperf\Support\make(BlService::class);
        $result    = $blService->delBl($param, $member);
        return $this->response->json($result);
    }


}
