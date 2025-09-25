<?php

namespace App\Controller\Home\Member;

use App\Common\Lib\Arr;
use App\Controller\Home\HomeBaseController;
use App\Exception\HomeException;
use App\Model\ApiMemberPlatformModel;
use App\Model\ApiPlatformModel;
use App\Model\AuthWayModel;
use App\Service\AuthWayService;
use App\Service\Cache\BaseCacheService;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;
use App\Request\LibValidation;
use Psr\Http\Message\ResponseInterface;
use function App\Common\batchUpdateSql;


#[Controller(prefix: "member/auth")]
class AuthWayController extends HomeBaseController
{
    protected int $platform_cfg_id = 1626;//认证平台 查看 category 表。认证平台
    #[Inject]
    protected BaseCacheService $baseCacheService;

    //认证方式
    #[RequestMapping(path: "way", methods: "post")]
    public function way(RequestInterface $request)
    {
        $member           = $this->request->UserInfo;
        $CountryCodeCache = $this->baseCacheService->CountryCodeCache();
        $CountryCodeCache = array_column($CountryCodeCache, 'country_id');
        $LibValidation    = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params           = $LibValidation->validate(params: $request->all(), rules: [
            'page'       => ['required', 'numeric'],
            'limit'      => ['required', 'numeric'],
            'country_id' => ['integer', Rule::in($CountryCodeCache)],
            'keyword'    => ['string', 'min:1'],
        ]);
        $perPage          = Arr::hasArr($params, 'limit') ? $params['limit'] : 15;
        $query            = AuthWayModel::query()
            ->where('status', '=', 1);
        if (Arr::hasArr($params, 'country_id')) {
            $query = $query->where('country_id', '=', $params['country_id']);
        }
        if (Arr::hasArr($params, 'keyword')) {
            $query = $query->where('way_name', 'like', '%' . $params['keyword'] . '%');
        }

        $painter = $query->with(
            [
                'item'            => function ($query) use ($member) {
                    $query->where('member_write', '=', 1)
                        ->with([
                            'account' => function ($query) use ($member) {
                                $query->where('member_id', '=', $member['uid']);
                            }
                        ])->select(['item_id', 'item_name', 'item_filed', 'platform_id']);
                },
                'country'         => function ($query) {
                    $query->select(['country_id', 'country_name', 'country_code', 'code']);
                },
                'platform'        => function ($query) {
                    $query->with(
                        [
                            'interface' => function ($query) {
                                $query->with(['auth'])->where("interface_cfg_id", '=', 1681);
                            }
                        ])->select("*");
                },
                'member_platform' => function ($query) use ($member) {
                    $query->where("member_id", '=', $member['uid']);
                }
            ]
        )->paginate($perPage)->toArray();
        foreach ($painter['data'] as $key => $val) {
            if (!empty($val['member_platform'])) {
                $painter['data'][$key]['cfg_status'] = "已配置";
            }
        }
        $result['code'] = 200;
        $result['data'] = $painter;
        return $this->response->json($result);
    }

    // 配置账号
    #[RequestMapping(path: "account", methods: "post")]
    public function account(RequestInterface $request)
    {
        $member        = $this->request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params        = $LibValidation->validate(params: $request->all(), rules: [
            'member_platform_id'      => ['integer'],
            'platform_id'             => ['required', 'integer'],
            'status'                  => ['integer'],
            'info'                    => ['string'],
            'account'                 => ['required', 'array',],
            'account.*.account_id'    => ['integer'],
            'account.*.item_id'       => ['required', 'integer'],
            'account.*.account_value' => ['required', 'string'],
        ]);
        $apiPlatformDb = ApiPlatformModel::query()->where('platform_id', '=', $params['platform_id'])->first()->toArray();
        if (empty($apiPlatformDb)) {
            throw new HomeException('该平台不存在');
        }
        $itemIdArr = $account = $accountIdArr = [];
        if (Arr::hasArr($params, 'member_platform_id')) {
            $query           = ApiMemberPlatformModel::query();
            $where[]         = ['member_platform_id', '=', $params['member_platform_id']];
            $apiMemberPlatDb = $query->with([
                'country'  => function ($query) {
                    $query->select(['country_id', 'country_name', 'country_code', 'code']);
                },
                'platform' => function ($query) {
                    $query->select(['platform_id', 'platform_name', 'platform_code', 'platform_url']);
                },
                'item'     => function ($query) {
                    $query->select(['platform_id', 'item_id', 'item_name', 'item_filed']);
                },
                'account'  => function ($query) {
                }
            ])
                ->where($where)
                ->first()->toArray();
            if (!empty($apiMemberPlatDb)) {
                $member_platform_id = $apiMemberPlatDb['member_platform_id'];
            }
            if (Arr::hasArr($apiMemberPlatDb, ['item', 'account'])) {
                $accountDb = $apiMemberPlatDb['account'];
                $accountDb = array_column($accountDb, null, 'item_id');
                foreach ($apiMemberPlatDb['item'] as $key => $val) {
                    $apiMemberPlatDb['item'][$key]['account'] = Arr::hasArr($accountDb, $val['item_id']) ? $accountDb[$val['item_id']] : [];
                }
                $itemIdArr    = array_column($apiMemberPlatDb['item'], 'item_id');
                $account      = array_column($apiMemberPlatDb['item'], 'account');
                $accountIdArr = array_column($account, 'account_id');
            }
        }
        $insertPlat = [];
        if (empty($apiMemberPlatDb)) {
            $insertPlat['platform_id']          = $apiPlatformDb['platform_id'];
            $insertPlat['member_platform_name'] = $apiPlatformDb['platform_name'];
            $insertPlat['info']                 = $params['info'];
            $insertPlat['status']               = 1;
            $insertPlat['platform_cfg_id']      = $this->platform_cfg_id;
            $insertPlat['member_id']            = $member['uid'];
            $insertPlat['add_time']             = time();
        }
        $accountIdDel                = $accountIdArr;
        $insertMemberPlatformAccount = $updateMemberPlatformAccount = [];
        if (Arr::hasArr($params, 'account')) {

            foreach ($params['account'] as $key => $val) {
                if (!isset($val['account_id']) || (!in_array($val['account_id'], $accountIdArr))) {
                    $item['platform_id']           = $params['platform_id'];
                    $item['member_id']             = $member['uid'];
                    $item['item_id']               = $val['item_id'];
                    $item['account_value']         = $val['account_value'];
                    $insertMemberPlatformAccount[] = $item;
                }

                if (Arr::hasArr($val, 'account_id') && (in_array($val['account_id'], $accountIdArr))) {
                    $item['account_id']    = $val['account_id'];
                    $item['platform_id']   = $params['platform_id'];
                    $item['member_id']     = $member['uid'];
                    $item['item_id']       = $val['item_id'];
                    $item['account_value'] = $val['account_value'];
                    Arr::del($accountIdDel, $val['account_id']);
                    $updateMemberPlatformAccount[] = $item;
                }
            }
        }

        Db::beginTransaction();
        try {
            if (!empty($insertPlat)) {
                $member_platform_id = Db::table("api_member_platform")->insertGetId($insertPlat);
            }
            if (!empty($accountIdDel)) {
                Db::table("api_member_platform_account")->whereIn('account_id', $accountIdDel)->delete();
            }
            if (!empty($insertMemberPlatformAccount) && $member_platform_id > 0) {
                $addArr['member_platform_id'] = $member_platform_id;
                $insertMemberPlatformAccount  = Arr::pushArr($addArr, $insertMemberPlatformAccount);
                Db::table("api_member_platform_account")->insert($insertMemberPlatformAccount);
            }

            if (!empty($updateMemberPlatformAccount)) {
                $addArr['member_platform_id']   = $member_platform_id;
                $updateMemberPlatformAccount    = Arr::pushArr($addArr, $updateMemberPlatformAccount);
                $updateMemberPlatformAccountSql = batchUpdateSql('api_member_platform_account', $updateMemberPlatformAccount);
                Db::update($updateMemberPlatformAccountSql);
            }
            Db::commit();
            $result['code'] = 200;
            $result['msg']  = empty($apiMemberPlatDb) ? "添加成功" : '更新成功';
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $result['code'] = 201;
            $result['msg']  = $e->getMessage();
        }
        return $this->response->json($result);
    }

    protected int $importAuth = 22102; // 40001;//40001=>22102 //清关需要认证
    protected $pictureCode = ['passport_front', 'identity_front', 'identity_back']; // 这些字段必须上传照片

    /**
     * @DOC 当地订单认证要素
     */
    #[RequestMapping(path: "element", methods: "post")]
    public function element(RequestInterface $request)
    {
        $param    = $request->all();
        $useWhere = $this->useWhere();
        $where    = $useWhere['where'];
        $result   = (new AuthWayService())->getElement($param, $where);
        return $this->response->json($result);
    }

    /**
     * @DOC 接口平台配置详情
     */
    #[RequestMapping(path: 'cfg', methods: 'post')]
    public function cfg(RequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $param         = $request->all();
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param, [
            'member_platform_id' => ['required', 'integer']
        ], [
            'member_platform_id.required' => '平台错误',
            'member_platform_id.integer'  => '平台错误'
        ]);

        $where[] = ['member_platform_id', '=', $param['member_platform_id']];
        $where[] = ['member_id', '=', $member['uid']];

        $data = ApiMemberPlatformModel::with([
            'country'  => function ($query) {
                $query->select(['country_id', 'country_name']);
            },
            'platform' => function ($query) {
                $query->select(['platform_id', 'platform_name', 'platform_code', 'platform_url']);
            },
            'item'     => function ($query) {
                $query->where('member_write', '=', 1)->select(['platform_id', 'item_id', 'item_name', 'item_filed']);
            },
            'account'
        ])->where($where)
            ->select(['member_platform_id', 'member_platform_name', 'add_time', 'platform_id', 'info', 'country_id', 'status'])
            ->first();
        if (!$data) {
            return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
        }
        $data = $data->toArray();
        if (Arr::hasArr($data, ['item', 'account'])) {
            $accountDb = $data['account'];
            $accountDb = array_column($accountDb, null, 'item_id');
            foreach ($data['item'] as $key => $val) {
                $data['item'][$key]['account'] = Arr::hasArr($accountDb, $val['item_id']) ? $accountDb[$val['item_id']] : [];
            }
        }
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
    }

    /**
     * @DOC 认证方式
     */
    #[RequestMapping(path: "way/platform", methods: "post")]
    public function authWayPlatform(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;

        $platform_idArr = ApiMemberPlatformModel::where('member_id', $member['uid'])->pluck('platform_id');
        $where[]        = ['platform_cfg_id', '=', 1626];
        if ($platform_idArr) {
            if (Arr::hasArr($param, 'country_id')) {
                $where[] = ['country_id', '=', $param['country_id']];
            }
            $list = AuthWayModel::where($where)
                ->whereIn('platform_id', $platform_idArr)
                ->with(['interface', 'interface.interface', 'interface.interface.auth', 'platform.account'])
                ->paginate($param['limit'] ?? 20);
            return $this->response->json([
                'code' => 200,
                'msg'  => '获取成功',
                'data' => [
                    'total' => $list->total(),
                    'data'  => $list->items()
                ]
            ]);
        }

        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => []]);
    }
}
