<?php

namespace App\Controller\App\Base;

use App\Common\Lib\Arr;
use App\Controller\Home\HomeBaseController;
use App\Exception\HomeException;
use App\JsonRpc\BaseServiceInterface;
use App\Model\AuthWayModel;
use App\Model\ChannelImportModel;
use App\Model\ConfigModel;
use App\Model\LineModel;
use App\Model\MemberLineModel;
use App\Model\NotifyModel;
use App\Model\NotifyReadModel;
use App\Model\OrderItemModel;
use App\Model\RecordCategoryGoodsModel;
use App\Model\WarehouseModel;
use App\Request\LibValidation;
use App\Service\AuthWayService;
use App\Service\BrandService;
use App\Service\Cache\BaseCacheService;
use App\Service\ConfigService;
use App\Service\GoodsService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: 'app/base')]
class BaseController extends HomeBaseController
{
    /**
     * @DOC 商品 获取商品列表
     */
    #[RequestMapping(path: 'goods', methods: 'post')]
    public function goods(RequestInterface $request)
    {
        $params = $request->all();
        $result = \Hyperf\Support\make(GoodsService::class)->categoryRecordLists($params);
        return $this->response->json($result);
    }

    /**
     * @DOC 商品 获取商品分类列表
     */
    #[RequestMapping(path: 'goods/category', methods: 'post')]
    public function categoryRecordLists(RequestInterface $request)
    {
        $params = $request->all();
        $where  = [];
        if (!empty($params['country_id'])) {
            $where[] = ['country_id', '=', $params['country_id']];
        }
        if (isset($params['parent_id']) && $params['parent_id'] != '-1') {
            $where[] = ['parent_id', '=', $params['parent_id']];
        }
        if (!empty($params['keyword'])) {
            $where[] = ['goods_name', 'like', '%' . $params['keyword'] . '%'];
        }
        $data           = RecordCategoryGoodsModel::where($where)
            ->select(['id', 'parent_id', 'goods_name', 'images', 'parent_ids', 'parent_string'])
            ->get()->toArray();
        $result['code'] = 200;
        $result['msg']  = '查询成功';
        $result['data'] = $data;
        return $this->response->json($result);
    }

    /**
     * @DOC 仓库 集货仓库
     */
    #[RequestMapping(path: 'warehouse', methods: 'post')]
    public function warehouse(RequestInterface $request)
    {
        $params = $request->all();
        $where  = [
            ['member_uid', '=', $request->UserInfo['parent_agent_uid']],
            ['status', '=', 1],
        ];
        if (!empty($params['ware_id'])) {
            $where[] = ['ware_id', '=', $params['ware_id']];
        }
        if (!empty($params['country_id'])) {
            $where[] = ['country_id', '=', $params['country_id']];
        }
        $data = WarehouseModel::where($where)
            ->with([
                'country' => function ($query) {
                    $query->select(['id', 'name', 'country_id']);
                }
            ])
            ->select(['ware_id', 'ware_code', 'ware_name', 'contacts', 'phone_before', 'contact_phone'
                      , 'contact_address', 'country_id', 'confine'
                      , 'province_id', 'province', 'city_id', 'city', 'district_id', 'district', 'street_id'
                      , 'street', 'address'])
            ->get()->toArray();
        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => $data
        ]);
    }

    /**
     * @DOC 消息 未读消息通知条数
     */
    #[RequestMapping(path: 'notify/unread/count', methods: 'post')]
    public function unreadCount(RequestInterface $request): ResponseInterface
    {
        $member       = $request->UserInfo;
        $where        = [
            ['status', '=', 1],
            ['member_uid', '!=', $member['uid']],
            ['parent_agent_uid', '=', $member['parent_agent_uid']]
        ];
        $readWhere    = [
            ['member_uid', '=', $member['uid']],
            ['status', '=', 0],
        ];
        $superior_uid = match ($member['role_id']) {
            3 => [$member['parent_agent_uid']],
            4, 5 => [$member['parent_join_uid'], $member['parent_agent_uid']],
            default => [],
        };
        # 查询 用户未读记录
        $unread = NotifyModel::query()
            ->where($where)
            ->whereIn('member_uid', $superior_uid)
            ->where(function ($query) use ($readWhere) {
                $query->where('receive_status', 1)
                    ->whereDoesntHave('read')
                    ->orWhere(function ($query) use ($readWhere) {
                        $query->where('receive_status', 0)
                            ->whereHas('read', function ($read) use ($readWhere) {
                                $read->where($readWhere);
                            });
                    });
            })->count();
        return $this->response->json([
            'code' => 200,
            'msg'  => '查询成功',
            'data' => ['unread' => $unread]
        ]);
    }

    /**
     * @DOC 消息 通知列表
     */
    #[RequestMapping(path: 'notify/lists', methods: 'post')]
    public function notifyList(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        if (Arr::hasArr($param, 'notify_id')) {
            $where[] = ['notify_id', '=', $param['notify_id']];
        }
        if (Arr::hasArr($param, 'add_time')) {
            $where[] = ['add_time', '>=', $param['add_time']];
        }
        if (Arr::hasArr($param, 'end_time')) {
            $where[] = ['add_time', '<=', $param['end_time']];
        }
        # 条件：已发送，不是本人信息，同一平台下
        $where[] = ['status', '=', 1];
        $where[] = ['member_uid', '!=', $member['uid']];
        $where[] = ['parent_agent_uid', '=', $member['parent_agent_uid']];
        # 根据角色获取上级信息
        $superior_uid = match ($member['role_id']) {
            3 => [$member['parent_agent_uid']],
            4, 5 => [$member['parent_join_uid'], $member['parent_agent_uid']],
            default => [],
        };
        # 查询 用户阅读表 记录信息 倒序排序
        $data = NotifyModel::where($where)
            ->whereIn('member_uid', $superior_uid)
            ->with(['member' => function ($member) {
                $member->select(['uid', 'user_name']);
            }, 'read'        => function ($read) use ($member) {
                $read->where('member_uid', '=', $member['uid']);
            }, 'type'        => function ($type) {
                $type->select(['cfg_id', 'name']);
            }])
            ->where('receive_status', '=', 1)
            ->orWhere(function ($query) use ($member, $where) {
                $query->where('receive_status', '=', 0)->where($where)
                    ->whereHas('read', function ($read) use ($member) {
                        $read->where('member_uid', '=', $member['uid']);
                    });
            })
            ->select(['*', 'type as sort'])
            ->orderBy('sort', 'DESC')
            ->orderBy('add_time', 'DESC')
            ->paginate($param['limit'] ?? 20);
        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => [
                'total' => $data->total(),
                'list'  => $data->items(),
            ]
        ]);
    }

    /**
     * @DOC 消息 更新消息状态
     */
    #[RequestMapping(path: 'notify/read', methods: 'post')]
    public function read(RequestInterface $request)
    {
        $params        = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'notify_id' => ['required', 'integer',],
            ], [
                'notify_id.required' => '消息通知不存在',
                'notify_id.integer'  => '消息通知不存在',
            ]);
        $member        = $request->UserInfo;
        $where         = [
            ['notify_id', '=', $param['notify_id']],
            ['member_uid', '=', $member['uid']],
            ['status', '!=', 1],
        ];
        $notify        = NotifyReadModel::where($where)->first();
        if (!$notify) {
            throw new HomeException('消息通知不存在');
        }
        NotifyReadModel::where($where)->update(['status' => 1, 'read_time' => time()]);
        $read = [
            'member_uid' => $member['uid'],
            'notify_id'  => $param['notify_id'],
            'status'     => 1,
            'read_time'  => time()
        ];
        NotifyReadModel::insert($read);
        return $this->response->json([
            'code' => 200,
            'msg'  => '阅读成功',
            'data' => []
        ]);
    }

    /**
     * @DOC 获取线路
     */
    #[RequestMapping(path: 'line', methods: 'post')]
    public function line(RequestInterface $request)
    {
        $member    = $request->UserInfo;
        $where     = [
            ['uid', '=', $member['parent_agent_uid']],
            ['status', '=', 1],
        ];
        $param     = $request->all();
        $lineWhere = [];
        if (Arr::hasArr($param, 'send_country_id')) {
            $lineWhere[] = ['send_country_id', '=', $param['send_country_id']];
        }
        if (Arr::hasArr($param, 'target_country_id')) {
            $lineWhere[] = ['target_country_id', '=', $param['target_country_id']];
        }
        $lineIds = null;
        if (!empty($lineWhere)) {
            $lineIds = LineModel::where($lineWhere)->pluck('line_id');
        }
        // 构建基本查询
        $query = MemberLineModel::query()
            ->where($where)
            ->with(['line' => function ($query) {
                $query->with([
                    'send'        => function ($query) {
                        $query->select(['country_id', 'country_name', 'country_code']);
                    },
                    'target'      => function ($query) {
                        $query->select(['country_id', 'country_name', 'country_code']);
                    },
                    'target_area' => function ($query) {
                        $query->where('parent_id', 0)->select(['id', 'country_id']);
                    }
                ])->select(['line_id', 'line_name', 'send_country_id', 'target_country_id']);
            }])
            ->select(['line_id'])
            ->orderBy('add_time', 'desc');
        // 如果有线路 ID，则添加到查询条件中
        if ($lineIds !== null) {
            $query->whereIn('line_id', $lineIds);
        }
        $data = $query->get()->toArray();
        // 返回结果
        if (empty($data)) {
            return $this->response->json([
                'code' => 201,
                'msg'  => '未查询到线路信息',
                'data' => $data
            ]);
        }
        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => $data
        ]);
    }

    /**
     * @DOC 行政区域
     */
    #[RequestMapping(path: 'area', methods: 'get,post')]
    public function area(RequestInterface $request): ResponseInterface
    {
        $param            = $request->all();
        $parent_agent_uid = $request->UserInfo['parent_agent_uid'];
        $result           = (new ConfigService())->area($param, $parent_agent_uid);
        return $this->response->json($result);
    }

    /**
     * @DOC 查找行政区域
     */
    #[RequestMapping(path: 'area/search', methods: 'get,post')]
    public function searchArea(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $result = (new ConfigService())->searchArea($param);
        return $this->response->json($result);
    }

    /**
     * @DOC 根据分词获取分类信息
     */
    #[RequestMapping(path: 'category/participle', methods: 'get,post')]
    public function GoodsCategoryParticiple(RequestInterface $request)
    {
        $text      = $request->input('text', '');
        $parent_id = $request->input('parent_id', 0);
        if (empty($text)) {
            throw new HomeException('请输入搜索词');
        }

        $baseServiceInterface = \Hyperf\Support\make(BaseServiceInterface::class);
        $result               = $baseServiceInterface->recordGoodsCategoryParticiple($text, 'ik_smart', $parent_id);
        return $this->response->json($result);

    }

    /**
     * @DOC 根据分词获取分类信息
     */
    #[RequestMapping(path: 'word/participle', methods: 'get,post')]
    public function recordGoodsWordSearch(RequestInterface $request)
    {
        $params        = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'text'      => ['required_without:parent_id', 'string',],
                'parent_id' => ['required_without:text', 'integer'],
            ], [
                'text.string'                => '关键词必须为字符串',
                'text.required_without'      => '关键词|父级ID必须存在一项',
                'parent_id.required_without' => '关键词|父级ID必须存在一项',
                'parent_id.integer'          => '父级ID必须为整数',
            ]);

        $baseServiceInterface = \Hyperf\Support\make(BaseServiceInterface::class);
        $result               = $baseServiceInterface->elasticRecordGoodsWordSearch($param);
        return $this->response->json($result);
    }

    /**
     * @DOC 认证要素
     */
    #[RequestMapping(path: 'element', methods: 'post')]
    public function element(RequestInterface $request): ResponseInterface
    {
        $field  = ['cfg_id', 'title', 'code', 'sort'];
        $cfg_id = $request->input('cfg_id', 0);
        $data   = (new BaseCacheService())->CategoryCache(pid: 1700, cfg_id: $cfg_id, field: $field);
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
    }

    /**
     * @DOC 获取品牌
     */
    #[RequestMapping(path: 'brand', methods: 'post')]
    public function brand(RequestInterface $request)
    {
        $param                = $request->all();
        $baseServiceInterface = \Hyperf\Support\make(BaseServiceInterface::class);

        $result = \Hyperf\Support\make(BrandService::class)->getBrand($param);
        return $this->response->json($result);
    }

    /**
     * @DOC 小程序获取历史商品
     */
    #[RequestMapping(path: 'goods/history', methods: 'post')]
    public function historyGoods(RequestInterface $request)
    {
        $member = $request->UserInfo;
        // 获取最近订单
        $orderItem = OrderItemModel::with([
            'category' => function ($query) {
                $query->select(['id', 'goods_name', 'images']);
            },
            'convert'  => function ($query) {
                $query->select(['item_code', 'record_sku_id']);
            }
        ])
            ->where('member_uid', $member['uid'])
            ->where('parent_join_uid', $member['parent_join_uid'])
            ->where('parent_agent_uid', $member['parent_agent_uid'])
            ->orderBy('add_time', 'DESC')
            ->select(['item_id', 'item_record_sn', 'item_code', 'category_item_id', 'brand_id', 'brand_name', 'goods_base_id', 'sku_id', 'item_sku_name', 'item_spec', 'item_spec_unit', 'item_sku', 'item_num', 'item_price', 'item_price_unit', 'item_tax'])
            ->limit(20)->get()->toArray();

        $orderItem = array_column($orderItem, null, 'category_item_id');
        $orderItem = array_values($orderItem);
        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => $orderItem
        ]);

    }

    /**
     * @DOC 获取是否隐藏配置信息
     */
    #[RequestMapping(path: 'page/hide', methods: 'post')]
    public function getHideConfig()
    {
        $hideConfig = (new ConfigModel())->where('cfg_id', 27000)->value('status');
        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => [
                'status' => $hideConfig
            ]
        ]);
    }

    /**
     * @DOC 生成条形码
     */
    #[RequestMapping(path: 'barcode', methods: 'post')]
    public function barcode(RequestInterface $request)
    {
        $number    = $request->input('number', '');
        $generator = new BarcodeGeneratorPNG();
        $png       = $generator->getBarcode($number, $generator::TYPE_CODE_128);
        $png       = base64_encode($png);
        return $this->response->json(['code' => 200, 'msg' => '生成成功', 'data' => ['png' => $png]]);
    }

    /**
     * @DOC 认证要素
     */
    protected array $pictureCode = ['passport_front', 'identity_front', 'identity_back']; // 这些字段必须上传照片

    /**
     * @DOC 根据渠道获取认证参数
     */
    #[RequestMapping(path: 'auth/element', methods: 'post')]
    public function getAuthElement(RequestInterface $request)
    {
        $params  = $request->all();
        $service = \Hyperf\Support\make(AuthWayService::class);
        $result  = $service->getAuthElementData($params);
        return $this->response->json($result);
    }

    /**
     * @DOC 优惠卷类型数据
     */
    #[RequestMapping(path: 'coupons/type', methods: 'post')]
    public function couponsType()
    {
        $baseCacheSer = \Hyperf\Support\make(BaseCacheService::class);
        $data         = $baseCacheSer->ConfigPidCache(30100);
        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => $data
        ]);
    }

}
