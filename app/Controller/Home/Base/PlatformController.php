<?php

namespace App\Controller\Home\Base;

use App\Common\Lib\Arr;
use App\Controller\Home\HomeBaseController;
use App\Model\ApiMemberPlatformModel;
use App\Model\ApiPlatformModel;
use App\Model\AuthWayModel;
use App\Model\CustomsSupervisionModel;
use App\Request\LibValidation;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: "base/platform")]
class PlatformController extends HomeBaseController
{
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
     * @DOC 监管方式
     */
    #[RequestMapping(path: 'supervision', methods: 'get,post')]
    public function supervision(RequestInterface $request): ResponseInterface
    {
        $param = $request->all();
        $where = [];
        if (Arr::hasArr($param, 'country_id')) {
            $where[] = ['country_id', '=', $param['country_id']];
        }
        $list = CustomsSupervisionModel::where($where)->paginate($param['limit'] ?? 20);
        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => [
                'total' => $list->total(),
                'data'  => $list->items()
            ]
        ]);
    }

    /**
     * @DOC 认证方式
     */
    #[RequestMapping(path: "auth/way", methods: "post")]
    public function authWay(RequestInterface $request): ResponseInterface
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

    /**
     * @DOC 物流公司平台列表
     */
    #[RequestMapping(path: "index", methods: "post")]
    public function index(RequestInterface $request): ResponseInterface
    {
        $param   = $request->all();
        $where[] = ['platform_cfg_id', '=', 1627];
        if (Arr::hasArr($param, 'platform_id')) {
            $where[] = ['platform_id', '=', $param['platform_id']];
        }
        if (Arr::hasArr($param, 'platform_cfg_id')) {
            $where[] = ['platform_cfg_id', '=', $param['platform_cfg_id']];
        }
        $data = ApiPlatformModel::where($where)
            ->with(['item' => function ($query) {
                $query->where('member_write', '=', 1)->select(['item_id', 'item_name', 'platform_id', 'item_filed']);
            }])->paginate($param['limit'] ?? 20);

        return $this->response->json([
            'code' => 200,
            'msg'  => '查询成功',
            'data' => [
                'total' => $data->total(),
                'data'  => $data->items(),
            ],
        ]);

    }


}
