<?php

namespace App\Controller\Home\Member;

use App\Common\Lib\Arr;
use App\Common\Lib\TimeLib;
use App\Controller\Home\AbstractController;
use App\Exception\HomeException;
use App\Model\ApiMemberPlatformModel;
use App\Model\MemberModel;
use App\Model\MemberSevModel;
use App\Model\SevModel;
use App\Service\Cache\BaseCacheService;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;
use Psr\Http\Message\ResponseInterface;
use App\Request\LibValidation;


#[Controller(prefix: "member/servers")]
class ServersController extends AbstractController
{

    #[Inject]
    protected BaseCacheService $baseCacheService;

    /**
     * @DOC 拥有服务：包含自建、和别人授权的服务
     * @Name   have
     * @Author wangfei
     * @date   2023/10/16 2023
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: "have", methods: "post")]
    public function have(RequestInterface $request)
    {
        $member           = $this->request->UserInfo;
        $CountryCodeCache = $this->baseCacheService->CountryCodeCache();
        $CountryCodeCache = array_column($CountryCodeCache, 'country_id');
        $LibValidation    = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params           = $LibValidation->validate(params: $request->all(), rules: [
            'page'       => ['required', 'numeric'],
            'limit'      => ['required', 'numeric'],
            'status'     => ['integer', 'numeric'],
            'country_id' => ['integer', Rule::in($CountryCodeCache)],
            'port_id'    => ['integer', 'numeric'],
            'sev_cfg_id' => ['integer', 'numeric'],
            'start_time' => ['date_format:Y-m-d H:i:s'],
            'end_time'   => ['date_format:Y-m-d H:i:s'],
        ]);
        $perPage          = Arr::hasArr($params, 'limit') ? $params['limit'] : 15;
        $where[]          = ['use_uid', '=', $member['uid']];
        if (isset($params['status']) && in_array($params['status'], [0, 1])) {
            $where[] = ['status', '=', $params['status']];
        }
        if (Arr::hasArr($params, 'country_id')) {
            $where[] = ['country_id', '=', $params['country_id']];
        }
        if (Arr::hasArr($params, 'sev_cfg_id')) {
            $where[] = ['sev_cfg_id', '=', $params['sev_cfg_id']];
        }
        if (Arr::hasArr($params, ['start_time', 'end_time'])) {
            $where[] = ['start_time', '>=', strtotime($params['start_time'])];
            $where[] = ['end_time', '<=', strtotime($params['end_time'])];
        }

        $query   = MemberSevModel::query();
        $painter = $query->with(
            [
                'country' => function ($query) {
                    $query->select(['country_id', 'country_name', 'country_code', 'code']);
                },
                'supply'  => function ($query) {
                    $query->select(['uid', 'user_name', 'nick_name', 'head_url']);
                },
                'use'     => function ($query) {
                    $query->select(['uid', 'user_name', 'nick_name', 'head_url']);
                }, 'node', 'servers.port'
            ]
        )
            ->where($where)
            ->paginate($perPage)->toArray();

        $sev               = array_column($painter['data'], 'servers');
        $m_platform_id_arr = array_unique(array_column($sev, 'member_platform_id'));
        $member_platformDb = ApiMemberPlatformModel::query()->whereIn('member_platform_id', $m_platform_id_arr)->select()->get()->toArray();
        $member_platformDb = array_column($member_platformDb, null, 'member_platform_id');
        foreach ($painter['data'] as $key => $val) {
            if ($val['use_uid'] == $val['supply_uid']) {
                $painter['data'][$key]['auth_type'] = "自建服务";
            } else {
                $painter['data'][$key]['auth_type'] = "授权服务";
            }
            if (isset($val['servers'])) {
                $member_platformId                                  = $val['servers']['member_platform_id'];
                $painter['data'][$key]['servers']['memberPlatform'] = $member_platformId > 0 && isset($member_platformDb[$member_platformId]) ? $member_platformDb[$member_platformId] : [];
            }
        }

        $result['code'] = 200;
        $result['data'] = $painter;
        return $this->response->json($result);
    }


    /**
     * @DOC 自建服务
     * @Name   lists
     * @Author wangfei
     * @date   2023/10/16 2023
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: "lists", methods: "post")]
    public function lists(RequestInterface $request)
    {
        $member           = $request->UserInfo;
        $CountryCodeCache = $this->baseCacheService->CountryCodeCache();
        $CountryCodeCache = array_column($CountryCodeCache, 'country_id');

        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params        = $LibValidation->validate(params: $request->all(), rules: [
            'page'       => ['required', 'numeric'],
            'limit'      => ['required', 'numeric'],
            'status'     => ['integer', 'numeric'],
            'country_id' => ['integer', Rule::in($CountryCodeCache)],
            'port_id'    => ['integer', 'numeric'],
            'sev_cfg_id' => ['integer', 'numeric'],
            'start_time' => ['date_format:Y-m-d H:i:s'],
            'end_time'   => ['date_format:Y-m-d H:i:s'],
        ]);
        $perPage       = Arr::hasArr($params, 'limit') ? $params['limit'] : 15;
        $where[]       = ['uid', '=', $member['uid']];
        if (isset($params['status']) && in_array($params['status'], [0, 1, 2])) {
            $where[] = ['sev_status', '=', $params['status']];
        }
        if (Arr::hasArr($params, 'country_id')) {
            $where[] = ['country_id', '=', $params['country_id']];
        }
        if (Arr::hasArr($params, 'sev_cfg_id')) {
            $where[] = ['sev_cfg_id', '=', $params['sev_cfg_id']];
        }
        if (Arr::hasArr($params, ['start_time', 'end_time'])) {
            $where[] = ['add_time', '>=', strtotime($params['start_time'])];
            $where[] = ['add_time', '<=', strtotime($params['end_time'])];
        }
        $query          = SevModel::query();
        $painter        = $query->with([
            'country' => function ($query) {
                $query->select(['country_id', 'country_name', 'country_code', 'code']);
            }, 'node', 'port'])
            ->where($where)
            ->orderBy('add_time', 'desc')
            ->paginate($perPage)->toArray();
        $result['code'] = 200;
        $result['data'] = $painter;
        return $this->response->json($result);
    }

    /**
     * @DOC 创建服务
     * @Name   handleAdd
     * @Author wangfei
     * @date   2023/10/16 2023
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: "handleAdd", methods: "post")]
    public function handleAdd(RequestInterface $request)
    {
        $member                       = $request->UserInfo;
        $ChannelNodeCache             = $this->baseCacheService->ChannelNodeCache();
        $ChannelNodeCache             = array_column($ChannelNodeCache, 'cfg_id');
        $CountryCodeCache             = $this->baseCacheService->CountryCodeCache();
        $CountryCodeCache             = array_column($CountryCodeCache, 'country_id');
        $params                       = $request->all();
        $params['member_platform_id'] = Arr::hasArr($params, 'member_platform_id') ? $params['member_platform_id'] : 0;
        $params['port_id']            = Arr::hasArr($params, 'port_id') ? $params['port_id'] : 0;

        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);

        $rule                          = [
            'sev_name'           => ['required', 'string', 'min:3'],
            'sev_cfg_id'         => ['required', Rule::unique('sev')->where(function ($query) use ($params, $member) {
                $query->where('uid', '=', $member['uid'])->where('country_id', '=', $params['country_id'])
                    ->where('sev_cfg_id', '=', $params['sev_cfg_id'])->where('port_id', '=', $params['port_id'])
                    ->where('member_platform_id', '=', $params['member_platform_id']);
            }), 'integer', Rule::in($ChannelNodeCache)],
            'country_id'         => ['required', 'integer', Rule::in($CountryCodeCache)],
            'sev_out_status'     => ['integer', 'numeric'],//是否对外 0：仅自用，1：仅对外，2：内外皆用
            'sev_desc'           => ['string'],
            'port_id'            => ['integer', 'numeric'],
            'member_platform_id' => ['integer', 'numeric'],
            'area'               => ['array'],
            'area.*.country_id'  => ['integer', 'required_with:area.*.province', Rule::in($CountryCodeCache)],
            'area.*.province'    => ['integer', 'required_with:area.*.city'],
            'area.*.city'        => ['string']
        ];
        $messages['sev_cfg_id.unique'] = '当前国家地区、节点类型、口岸、物流公司已存在、禁止重复添加';
        switch ($params['sev_cfg_id']) {
            case 1620://报关
            case 1622://清关
                $rule['port_id']              = ['required', 'integer', 'numeric'];
                $messages['port_id.required'] = '报关、清关：口岸必填';
                break;
            case 1619://发出集货、
                break;
            case 1623://落地转运
                $rule['member_platform_id']              = ['required', 'integer', 'numeric'];
                $messages['member_platform_id.required'] = '落地转运：快递公司必填';
                break;
        }
        $params                    = $LibValidation->validate(params: $params, rules: $rule, messages: $messages);
        $time                      = time();
        $sev['uid']                = $request->UserInfo['uid'];
        $sev['sev_name']           = $params['sev_name'];
        $sev['sev_cfg_id']         = $params['sev_cfg_id'];
        $sev['country_id']         = $params['country_id'];
        $sev['member_platform_id'] = $params['member_platform_id'];
        $sev['port_id']            = $params['port_id'];
        $sev['sev_out_status']     = $params['sev_out_status'];
        $sev['sev_desc']           = addslashes($params['sev_desc']);
        $sev['area']               = !empty($params['area']) ? json_encode($params['area']) : '';
        $sev['add_time']           = $time;
        switch ($params['sev_out_status']) {
            case 0:
                $sev['sev_status'] = 2; //仅自用的时候，无需审核。直接通过
                break;
        }

        $memberSev['status']     = 1;
        $memberSev['sev_cfg_id'] = $params['sev_cfg_id'];
        $memberSev['country_id'] = $params['country_id'];
        $memberSev['use_uid']    = $member['uid'];
        $memberSev['supply_uid'] = $member['uid'];

        Db::beginTransaction();
        try {
            $sev_id              = Db::table("sev")->insertGetId($sev);
            $memberSev['sev_id'] = $sev_id;
            Db::table("member_sev")->insert($memberSev);
            Db::commit();
            $result['code'] = 200;
            $result['msg']  = "添加成功";
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $result['code'] = 201;
            $result['msg']  = $e->getMessage();
        }
        return $this->response->json($result);
    }

    /**
     *
     *
     * /**
     * @DOC 编辑服务
     * @Name   handleEdit
     * @Author wangfei
     * @date   2023/10/16 2023
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: "handleEdit", methods: "post")]
    public function handleEdit(RequestInterface $request)
    {
        $member                       = $request->UserInfo;
        $ChannelNodeCache             = $this->baseCacheService->ChannelNodeCache();
        $ChannelNodeCache             = array_column($ChannelNodeCache, 'cfg_id');
        $CountryCodeCache             = $this->baseCacheService->CountryCodeCache();
        $CountryCodeCache             = array_column($CountryCodeCache, 'country_id');
        $params                       = $request->all();
        $params['member_platform_id'] = Arr::hasArr($params, 'member_platform_id') ? $params['member_platform_id'] : 0;
        $params['port_id']            = Arr::hasArr($params, 'port_id') ? $params['port_id'] : 0;

        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);

        $rule                          = [
            'sev_id'             => ['required', 'integer'],
            'sev_name'           => ['required', 'string', 'min:3'],
            'sev_cfg_id'         => ['required', Rule::unique('sev')->where(function ($query) use ($params, $member) {
                $query->where('uid', '=', $member['uid'])->where('country_id', '=', $params['country_id'])
                    ->where('sev_cfg_id', '=', $params['sev_cfg_id'])->where('port_id', '=', $params['port_id'])
                    ->where('member_platform_id', '=', $params['member_platform_id'])
                    ->where('sev_id', '<>', $params['sev_id']);
            }), 'integer', Rule::in($ChannelNodeCache)],
            'country_id'         => ['required', 'integer', Rule::in($CountryCodeCache)],
            'sev_out_status'     => ['integer', 'numeric'],//是否对外 0：仅自用，1：仅对外，2：内外皆用
            'sev_desc'           => ['string'],
            'port_id'            => ['integer', 'numeric'],
            'member_platform_id' => ['integer', 'numeric'],
            'area'               => ['array'],
            'area.*.country_id'  => ['integer', 'required_with:area.*.province', Rule::in($CountryCodeCache)],
            'area.*.province'    => ['integer', 'required_with:area.*.city'],
            'area.*.city'        => ['string']
        ];
        $messages['sev_cfg_id.unique'] = '当前国家地区、节点类型、口岸、物流公司已存在、禁止重复添加';
        switch ($params['sev_cfg_id']) {
            case 1620://报关
            case 1622://清关
                $rule['port_id']              = ['required', 'integer', 'numeric'];
                $messages['port_id.required'] = '报关、清关：口岸必填';
                break;
            case 1619://发出集货、
                break;
            case 1623://落地转运
                $rule['member_platform_id']              = ['required', 'integer', 'numeric'];
                $messages['member_platform_id.required'] = '落地转运：快递公司必填';
                break;
        }
        $params = $LibValidation->validate(params: $params, rules: $rule, messages: $messages);

        $sevWhere['sev_id'] = $params['sev_id'];
        $sevWhere['uid']    = $member['uid'];

        $sevDb = Db::table('sev')->where($sevWhere)->first();
        if (empty($sevWhere)) {
            throw new HomeException('当前服务不存在、或者不属于您');
        }

        $time                      = time();
        $sev['uid']                = $request->UserInfo['uid'];
        $sev['sev_name']           = $params['sev_name'];
        $sev['sev_cfg_id']         = $params['sev_cfg_id'];
        $sev['country_id']         = $params['country_id'];
        $sev['member_platform_id'] = $params['member_platform_id'];
        $sev['port_id']            = $params['port_id'];
        $sev['sev_out_status']     = $params['sev_out_status'];
        $sev['sev_desc']           = addslashes($params['sev_desc']);
        $sev['area']               = !empty($params['area']) ? json_encode($params['area']) : '';
        $sev['update_time']        = $time;
        switch ($params['sev_out_status']) {
            case 0:
                $sev['sev_status'] = 2; //仅自用的时候，无需审核。直接通过
                break;
        }

        $memberSev['sev_cfg_id'] = $params['sev_cfg_id'];
        $memberSev['country_id'] = $params['country_id'];
        $memberSev['use_uid']    = $member['uid'];
        $memberSev['supply_uid'] = $member['uid'];

        Db::beginTransaction();
        try {
            Db::table("sev")->where('sev_id', '=', $params['sev_id'])->update($sev);
            Db::table("member_sev")->where('sev_id', '=', $params['sev_id'])->update($memberSev);
            Db::commit();
            $result['code'] = 200;
            $result['msg']  = "修改成功";
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $result['code'] = 201;
            $result['msg']  = $e->getMessage();
        }
        return $this->response->json($result);
    }


    //授权服务
    #[RequestMapping(path: "auth", methods: "post")]
    public function auth(RequestInterface $request)
    {
        $member                 = $request->UserInfo;
        $params                 = $request->all();
        $LibValidation          = \Hyperf\Support\make(LibValidation::class, [$member]);
        $rule                   = [
            'sev_id'     => ['required', 'integer'],
            'uid'        => ['required', 'integer', Rule::exists('member')->where(function ($query) use ($params, $member) {
                $query->whereIn('role_id', [1, 2])->where("uid", '<>', $member['uid']);
            })],
            'start_time' => ['date_format:Y-m-d H:i:s'],
        ];
        $messages['uid.exists'] = '被授权的用户不存在、禁止给自己或者非平台代理用户授权';
        $params                 = $LibValidation->validate(params: $params, rules: $rule, messages: $messages);
        $data                   = SevModel::query()->where('sev_id', '=', $params['sev_id'])
            ->with(['node', 'use' => function ($query) use ($params) {
                $query->where('use_uid', '=', $params['uid'])->where('sev_id', '=', $params['sev_id']);
            }])->first()->toArray();
        if (empty($data)) {
            throw new HomeException('授权错误：服务不存在');
        }
        $result['code'] = 200;
        if (Arr::hasArr($data, 'use')) {
            $membSev['start_time'] = strtotime($params['start_time']);
            $membSev['end_time']   = TimeLib::yearAfter(1, $membSev['start_time']);
            Db::table('member_sev')->where('member_sev_id', '=', $data['use']['member_sev_id'])->update($membSev);
            $result['msg'] = "更新授权成功";
        } else {
            $membSev['sev_id']     = $params['sev_id'];
            $membSev['use_uid']    = $params['uid'];//被授权客户
            $membSev['supply_uid'] = $member['uid'];//授权客户
            $membSev['country_id'] = $data['country_id'];//服务所在地
            $membSev['status']     = 1;
            $membSev['sev_cfg_id'] = $data['sev_cfg_id'];
            $membSev['start_time'] = strtotime($params['start_time']);
            $membSev['end_time']   = TimeLib::yearAfter(1, $membSev['start_time']);
            Db::table('member_sev')->insert($membSev);
            $result['msg'] = "授权成功";
        }
        return $this->response->json($result);
    }

    // 授权用户列表
    #[RequestMapping(path: "use", methods: "post")]
    public function use(RequestInterface $request)
    {
        $member         = $request->UserInfo;
        $params         = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class, [$member]);
        $rule           = [
            'page'  => ['required', 'numeric'],
            'limit' => ['required', 'numeric']
        ];
        $params         = $LibValidation->validate(params: $params, rules: $rule);
        $perPage        = Arr::hasArr($params, 'limit') ? $params['limit'] : 15;
        $paginate       = MemberModel::query()->whereIn('role_id', [1, 2])->where('status', '=', 2)
            ->where('uid', '<>', $member['uid'])->paginate($perPage)
            ->toArray();
        $result['code'] = 200;
        $result['msg']  = '查询成功';
        $result['data'] = $paginate;
        return $this->response->json($result);
    }

}
