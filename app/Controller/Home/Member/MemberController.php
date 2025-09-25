<?php

namespace App\Controller\Home\Member;

use App\Common\Lib\Arr;
use App\Common\Lib\Crypt;
use App\Common\Lib\SendTemplate;
use App\Common\Lib\Str;
use App\Controller\Home\HomeBaseController;
use App\Exception\HomeException;
use App\Model\AgentMemberModel;
use App\Model\AgentPlatformModel;
use App\Model\AgentRateModel;
use App\Model\DeliveryStationModel;
use App\Model\MemberChildModel;
use App\Model\MemberInfoModel;
use App\Model\MemberJoinAppModel;
use App\Model\MemberModel;
use App\Model\OrderModel;
use App\Model\ParcelModel;
use App\Model\ParcelSendModel;
use App\Model\ParcelTransportModel;
use App\Request\AuthenticationRequest;
use App\Request\LibValidation;
use App\Request\MemberRequest;
use App\Service\Cache\BaseEditUpdateCacheService;
use App\Service\LoginService;
use App\Service\MembersService;
use App\Service\SmsService;
use Hyperf\Cache\Cache;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Redis\Redis;
use Psr\Http\Message\ResponseInterface;


#[Controller(prefix: "member/member")]
class MemberController extends HomeBaseController
{

    #[Inject]
    protected MembersService $membersService;

    /**
     * @DOC 用户信息
     */
    #[RequestMapping(path: "info", methods: "get,post")]
    public function info(RequestInterface $request): ResponseInterface
    {
        $userInfo = $request->UserInfo;

        $member = MemberModel::where('uid', $userInfo['uid'])
            ->with(['member'])
            ->select(['uid', 'user_name', 'email', 'head_url', 'tel', 'nick_name'])
            ->first()->toArray();
        if (isset($member['member']['agent_status']) && in_array($member['member']['agent_status'], [0, 1]) && $member['member']['role_id'] == 3) {
            return $this->response->json(['code' => 200, 'msg' => '账号未审核', 'data' => []]);
        }

        # 解密用户手机号
        $member          = $this->memberDecrypt($member, true);
        $member['child'] = [];
        if ($userInfo['child_uid']) {
            $member['child'] = MemberChildModel::select(['uid', 'child_name', 'head_url'])
                ->where('child_uid', $userInfo['child_uid'])->first();
        }
        $member['platform'] = AgentPlatformModel::where('agent_platform_uid', $userInfo['parent_agent_uid'])
            ->select(['agent_platform_uid', 'currency_id', 'currency', 'web_domain', 'web_domain_md5', 'web_code', 'web_keywords', 'web_logo', 'web_name'])
            ->first();
        # 汇率
        $member['platform']['rate'] = AgentRateModel::where('agent_platform_uid', $userInfo['parent_agent_uid'])
            ->with(['source', 'target'])
            ->first();
        $member['join']             = MemberModel::where('uid', $userInfo['parent_join_uid'])
            ->select(['uid', 'user_name', 'nick_name', 'email', 'tel', 'head_url'])->first();
        switch ($userInfo['role_id']) {
            case 1:
                $member['member_role'] = '平台代理';
                $member['member']      = $userInfo;
                break;
            case 2:
                $member['member_role'] = '货运代理';
                $member['member']      = $userInfo;
                break;
            case 3:
                $member['member_role'] = '加盟商家';

                $userInfo         = $this->getUserInfo($userInfo, $member);
                $member['code']   = $member['member']['code'];
                $member['member'] = $userInfo;
                break;
            case 4:
                $member['member_role'] = '企业商家';

                $userInfo         = $this->getUserInfo($userInfo, $member);
                $userInfo['code'] = $member['member']['code'];
                $member['member'] = $userInfo;
                break;
            case 5:
                $member['member_role'] = '个人商家';

                $userInfo         = $this->getUserInfo($userInfo, $member);
                $userInfo['code'] = $member['member']['code'];
                $member['member'] = $userInfo;
                break;
            case 8:
                $member['member_role'] = '体验组';

                $userInfo         = $this->getUserInfo($userInfo, $member);
                $member['member'] = $userInfo;
                break;
            case 9:
                $member['member_role'] = '所有权限组';

                $userInfo         = $this->getUserInfo($userInfo, $member);
                $member['member'] = $userInfo;
                break;
            case 10:
                $member['member_role'] = '仓库管理';
                $member['member']      = $userInfo;
                break;
            default:
                break;
        }

        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $member]);
    }

    /**
     * @DOC info 用户信息获取
     */
    protected function getUserInfo($userInfo, &$member)
    {
        $userInfo['level']        = $member['member']['level'];
        $member['amount']         = $userInfo['amount'] = $member['member']['amount'];
        $member['warning_amount'] = $member['member']['warning_amount'];
        # 授信额度
        $member['residue'] = number_format($member['member']['warning_amount'], 2);
        if ($member['member']['amount'] < 0) {
            $member['residue'] = number_format($member['member']['warning_amount'] + $member['member']['amount'], 2);
            if ($member['residue'] < 0) $member['residue'] = 0.00;
        }
        $member['residue'] = str_replace(',', '', $member['residue']);
        return $userInfo;
    }


    /**
     * @DOC 查看当前用户下级信息
     */
    #[RequestMapping(path: "subordinate", methods: "post")]
    public function subordinate(RequestInterface $request): ResponseInterface
    {
        $limit  = $this->request->input('limit', 20);
        $member = $request->UserInfo;

        $where = match ($member['role_id']) {
            1 => [['parent_agent_uid', '=', $member['uid']]],
            3 => [['parent_join_uid', '=', $member['uid']], ['parent_agent_uid', '=', $member['parent_agent_uid']]],
            default => [],
        };
        if (empty($where)) {
            return $this->response->json(['code' => 200, 'msg' => 'success', 'data' => ['count' => 0, 'list' => []]]);
        }
        $list = AgentMemberModel::where($where)
            ->with(['member' => function ($member) {
                $member->select('uid', 'user_name');
            }])
            ->select(['member_uid', 'parent_join_uid', 'parent_agent_uid'])
            ->paginate($limit);

        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => [
                'count' => $list->total(),
                'list'  => $list->items(),
            ]
        ]);
    }

    /**
     * @DOC 获取用户实名信息
     */
    #[RequestMapping(path: "information", methods: "post")]
    public function information(RequestInterface $request): array|ResponseInterface
    {
        $member = $request->UserInfo;
        $param  = $request->all();

        $info = MemberInfoModel::where('member_uid', ($param['uid'] ?? $member['uid']))
            ->where('parent_agent_uid', $member['parent_agent_uid'])
            ->with([
                'category' => function ($category) {
                    $category->select(['cfg_id', 'title']);
                },
                'member'   => function ($query) {
                    $query->with([
                        'member' => function ($query) {
                            $query->select(['member_uid', 'amount', 'warning_amount', 'agent_status', 'code']);
                        }
                    ])->select(['uid', 'user_name', 'nick_name']);
                },
                'joins'    => function ($query) {
                    $query->select(['uid', 'user_name', 'nick_name']);
                }
            ])
            ->first();
        if ($info) {
            $info                   = $info->toArray();
            $info['card_number']    = Str::centerStar($info['card_number']); // 身份证脱敏
            $info['co_card_number'] = Str::centerStar($info['co_card_number']); // 身份证脱敏
            if (!empty($info['member']['member'])) {
                $info['member']['member']['residue'] = number_format($info['member']['member']['warning_amount'], 2);
                if ($info['member']['member']['amount'] < 0) {
                    $v['residue'] = number_format($info['member']['member']['warning_amount'] + $info['member']['member']['amount'], 2);
                    if ($info['member']['member']['residue'] < 0) $info['member']['member']['residue'] = 0.00;
                }
                $info['member']['member']['residue'] = str_replace(',', '', $info['member']['member']['residue']);
            }
        } else {
            $info = [];
        }
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $info]);
    }

    /**
     * @DOC 修改头像 / 会员名称
     */
    #[RequestMapping(path: "updateBasic", methods: "post")]
    public function updateBasic(RequestInterface $request): ResponseInterface
    {
        $member = $request->UserInfo;
        $param  = $request->all();
        $data   = [];
        if (Arr::hasArr($param, 'nickname')) {
            $true = MemberModel::where('nick_name', $param['nickname'])->exists();
            if ($true) {
                throw new HomeException('昵称已存在');
            }
            $data['nick_name'] = $param['nickname'];
        }
        $flay = true;
        if (Arr::hasArr($param, 'head_url')) {
            $data['head_url'] = $param['head_url'];
        }
        if (Arr::hasArr($param, 'currency') && Arr::hasArr($param, 'currency_id')) {
            $flay = false;
            AgentPlatformModel::where('agent_platform_uid', $member['parent_agent_uid'])
                ->update(['currency' => $param['currency'], 'currency_id' => $param['currency_id']]);
            AgentRateModel::where('agent_platform_uid', $member['parent_agent_uid'])->delete();
            $baseCacheService = new BaseEditUpdateCacheService();
            $baseCacheService->AgentPlatformCache();
        }
        if (Arr::hasArr($param, 'logo')) {
            $flay = false;
            AgentPlatformModel::where('agent_platform_uid', $member['parent_agent_uid'])->update(['web_logo' => $param['logo']]);
        }
        if (Arr::hasArr($param, 'web_name')) {
            $flay = false;
            AgentPlatformModel::where('agent_platform_uid', $member['parent_agent_uid'])->update(['web_name' => $param['web_name']]);
        }
        if (empty($data) && $flay) {
            throw new HomeException('未发现要更新的内容');
        }
        if (!empty($data)) {
            MemberModel::where('uid', $member['uid'])->update($data);
        }
        return $this->response->json(['code' => 200, 'msg' => '修改成功', 'data' => []]);
    }

    /**
     * @DOC 新增用户实名信息
     */
    #[RequestMapping(path: "authentication", methods: "post")]
    public function Authentication(RequestInterface $request): ResponseInterface
    {
        $member = $request->UserInfo;
        $role   = $request->input('role_id', 0);
        # 处理 个人 与 企业的信息
        $data                     = $this->authenticationData($role);
        $data['member_uid']       = $member['uid'];
        $data['parent_join_uid']  = $member['parent_join_uid'];
        $data['parent_agent_uid'] = $member['parent_agent_uid'];

        $agentMemberWhere = [
            ['member_uid', '=', $member['uid']],
            ['parent_join_uid', '=', $member['parent_join_uid']],
            ['parent_agent_uid', '=', $member['parent_agent_uid']],
        ];

        $info = MemberInfoModel::where($agentMemberWhere)->exists();
        if ($info) {
            $data['update_time'] = time();
            $data['status']      = 1;
            MemberInfoModel::where('member_uid', $member['uid'])->update($data);
        } else {
            $data['add_time']    = time();
            $data['update_time'] = time();
            MemberInfoModel::insert($data);
        }

        $AgentMember = AgentMemberModel::where($agentMemberWhere)->first();
        # 重新审核，提交实名后修改加盟商的审核状态
        $update['role_id'] = $role;
        if ($AgentMember['status'] == 0) {
            $AgentMember['agent_status'] = 1;
            $AgentMember['error']        = '';
            $update['agent_status']      = 1;
            $update['error']             = '';
        }
        AgentMemberModel::where($agentMemberWhere)->update($update);
        return $this->response->json(['code' => 200, 'msg' => '实名成功', 'data' => ['member' => $AgentMember]]);
    }

    # 验证 实名信息
    public function authenticationData($role): array
    {
        $cardRequest = $this->container->get(AuthenticationRequest::class);
        $param       = match ($role) {
            5 => $cardRequest->scene('personal')->validated(),
            1, 2, 3, 4 => $cardRequest->scene('enterprise')->validated(),
            default => throw new HomeException('实名类型错误')
        };

        if ($param['card_type'] == 346) {
            $id_card_pattern = '/^\d{17}[0-9xX]$/';
            if (!preg_match($id_card_pattern, $param['card_number'])) {
                throw new HomeException('身份证号码格式不正确');
            }
        }

        $data = [
            'country'      => $param['country'] ?? '',
            'country_id'   => $param['country_id'] ?? 0,
            'province'     => $param['province'] ?? '',
            'province_id'  => $param['province_id'] ?? 0,
            'city'         => $param['city'] ?? '',
            'city_id'      => $param['city_id'] ?? 0,
            'district'     => $param['district'] ?? '',
            'district_id'  => $param['district_id'] ?? 0,
            'street'       => $param['street'] ?? '',
            'street_id'    => $param['street_id'] ?? 0,
            'address'      => $param['address'] ?? '',
            'card_type'    => $param['card_type'] ?? 0,
            'issuing_date' => $param['issuing_date'],
            'expiry_date'  => $param['expiry_date'],
            'photo_path'   => implode(',', $param['photo_path']),
            'card_name'    => (new Crypt())->encrypt($param['card_name']),
        ];

        if (!str_contains($param['card_number'], '*')) {
            if ($param['card_type'] == 346) {
                $id_card_pattern = '/^\d{17}[0-9xX]$/';
                if (!preg_match($id_card_pattern, $param['card_number'])) {
                    throw new HomeException('身份证号码格式不正确');
                }
            }
            $data['card_number'] = (new Crypt())->encrypt($param['card_number']);
        }

        if ($role == 5) {
            $data['co_name']        = '';
            $data['co_card_number'] = '';
            $data['co_photo_path']  = '';
        }
        if (in_array($role, [1, 2, 3, 4])) {
            $data['co_name']       = $param['co_name'];
            $data['co_photo_path'] = implode(',', $param['co_photo_path']);
            if (!str_contains($param['co_card_number'], '*')) {
                $data['co_card_number'] = (new Crypt())->encrypt($param['co_card_number']);
            }
        }
        return $data;
    }

    /**
     * @DOC 修改密码
     */
    #[RequestMapping(path: "changePassword", methods: "post")]
    public function ChangePassword(RequestInterface $request): ResponseInterface
    {
        $member        = $request->UserInfo;
        $memberRequest = $this->container->get(MemberRequest::class);
        $param         = $memberRequest->scene('changePassword')->validated();
        if ($param['new_password'] != $param['confirm_password']) {
            throw new HomeException('密码与确认密码不一致');
        }
        $member = MemberModel::where('uid', $member['uid'])->first();
        # 加密密码
        $password = (new LoginService())->mkPw($member['user_name'], $param['password'], $member['hash']);
        if ($member['user_password'] != $password) {
            throw new HomeException('密码错误,请检查密码');
        }
        $data['hash']          = LoginService::random(5, null, '@#$%^&*()');
        $data['user_password'] = (new LoginService())->mkPw($member['user_name'], $param['new_password'], $data['hash']);
        MemberModel::where('uid', $member['uid'])->update($data);
        return $this->response->json(['code' => 200, 'msg' => '修改完成，请重新登录', 'data' => []]);
    }

    /**
     * @DOC 绑定手机号
     */
    #[RequestMapping(path: "bindPhone", methods: "post")]
    public function bindPhone(RequestInterface $request): ResponseInterface
    {
        $member        = $request->UserInfo;
        $memberRequest = $this->container->get(MemberRequest::class);
        $param         = $memberRequest->scene('bindPhone')->validated();
        # 校验验证码
        (new SmsService())->checkVerifyCode($param['code'], $param['area_code'], $param['mobile']);
        # 加密手机号
        $tel = base64_encode((new Crypt())->encrypt($param['mobile']));
        # 验证手机号唯一
        $only = MemberModel::where('tel', $tel)->exists();
        if ($only) {
            throw new HomeException('手机号已绑定账号，请更换手机号');
        }
        MemberModel::where('uid', $member['uid'])->update(['area_code' => $param['area_code'], 'tel' => $tel]);
        return $this->response->json(['code' => 200, 'msg' => '绑定成功', 'data' => []]);
    }

    /**
     * @DOC 发送绑定的手机号验证码
     */
    #[RequestMapping(path: "sendBinding", methods: "post")]
    public function sendBinding(RequestInterface $request): ResponseInterface
    {
        $member = $request->UserInfo;
        # 获取手机号
        $member = MemberModel::where('uid', $member['uid'])->first()->toArray();
        $member = $this->memberDecrypt($member);
        # 发送验证码
        (new SmsService())->send($member['area_code'], $member['tel'], 2);
        return $this->response->json(['code' => 200, 'msg' => '发送成功', 'data' => []]);
    }

    /**
     * @DOC 解绑手机号 -- 第一步
     */
    #[RequestMapping(path: "bindingVerify", methods: "post")]
    public function bindingVerify(RequestInterface $request): ResponseInterface
    {
        $member        = $request->UserInfo;
        $memberRequest = $this->container->get(MemberRequest::class);
        $param         = $memberRequest->scene('code')->validated();

        # 获取手机号
        $member = MemberModel::where('uid', $member['uid'])->first()->toArray();
        $member = $this->memberDecrypt($member);
        (new SmsService())->checkVerifyCode($param['code'], $member['area_code'], $member['tel'], 2);

        # 验证标签
        $redis = $this->container->get(Redis::class);
        $mark  = LoginService::random(5, null, '@#$%^&*()');
        $redis->set('binding_phone_' . $member['tel'], $mark, 600);

        return $this->response->json(['code' => 200, 'msg' => '验证成功', 'data' => ['mark' => $mark]]);

    }

    /**
     * @DOC 解绑手机号 -- 第二步
     */
    #[RequestMapping(path: "bindingChange", methods: "post")]
    public function bindingChange(RequestInterface $request): ResponseInterface
    {
        $memberRequest = $this->container->get(MemberRequest::class);
        $param         = $memberRequest->scene('bindPhone')->validated();
        $mark          = $request->input('mark', '');
        $member        = $request->UserInfo;
        $member        = MemberModel::where('uid', $member['uid'])->first()->toArray();
        # 解析手机号
        $member    = $this->memberDecrypt($member);
        $redis     = $this->container->get(Redis::class);
        $markRedis = $redis->get('binding_phone_' . $member['tel']);
        if (!$markRedis || $markRedis != $mark) {
            throw new HomeException('换绑手机：请完成前一步');
        }
        if ($param['mobile'] == $member['tel']) {
            throw new HomeException('换绑手机：请更换不同的手机号');
        }
        (new SmsService())->checkVerifyCode($param['code'], $param['area_code'], $param['mobile'], 3);

        # 加密手机号
        $tel = base64_encode((new Crypt())->encrypt($param['mobile']));
        # 验证手机号唯一
        $only = MemberModel::where('tel', $tel)->exists();
        if ($only) {
            throw new HomeException('手机号已绑定账号，请更换手机号');
        }

        MemberModel::where('uid', $member['uid'])->update(['area_code' => '', 'tel' => $tel]);
        return $this->response->json(['code' => 200, 'msg' => '换绑成功', 'data' => []]);
    }

    /**
     * @DOC 获取证件信息
     */
    #[RequestMapping(path: "document", methods: "post")]
    public function document(RequestInterface $request): ResponseInterface
    {
        $userInfo = $request->UserInfo;
        # 实名信息
        $info = MemberInfoModel::where('member_uid', $userInfo['uid'])
            ->where('parent_agent_uid', $userInfo['parent_agent_uid'])
            ->first();

        # 用户审核状态
        $AgentMember = AgentMemberModel::where('member_uid', $userInfo['uid'])
            ->where('parent_agent_uid', $userInfo['parent_agent_uid'])
            ->first();

        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => [
                'member' => $AgentMember ? $AgentMember->toArray() : [],
                'info'   => $info ? $info->toArray() : [],
            ]
        ]);
    }

    /**
     * @DOC 加盟商审核列表
     */
    #[RequestMapping(path: "auditList", methods: "post")]
    public function auditList(RequestInterface $request): ResponseInterface
    {
        # 代理 判断
        $member = $request->UserInfo;
        $param  = $request->all();
        # 查询当前代理下的审核加盟商

        $where[] = ['parent_agent_uid', '=', $member['parent_agent_uid']];

        $where[] = match ($member['role_id']) {
            1, 2 => ['parent_join_uid', '=', 0],
            3 => ['parent_join_uid', '=', $member['uid']],
            default => throw new HomeException('权限不足，无法审核信息'),
        };

        if (Arr::hasArr($param, 'status', true)) {
            $where[] = ['agent_status', '=', $param['status']];
        }
        $list = AgentMemberModel::where($where)
            ->with(['member' => function ($memberData) {
                $memberData->select('uid', 'user_name', 'status', 'tel', 'head_url');
            }, 'member.info' => function ($info) use ($member) {
                $info->where('parent_agent_uid', $member['parent_agent_uid']);
            }])
            ->whereHas('member.info');
        if (Arr::hasArr($param, 'username')) {
            $list = $list->whereHas('member', function ($member) use ($param) {
                $member->where('user_name', 'like', '%' . $param['username'] . '%');
            });
        }
        $list = $list->paginate($param['limit'] ?? 20);

        $data  = $list->items();
        $crypt = new Crypt();
        foreach ($data as &$valur) {
            if (!empty($valur['member']['tel'])) {
                $tel                    = base64_decode($valur['member']['tel']);
                $valur['member']['tel'] = $crypt->decrypt($tel);
                // 手机号替换
                $valur['member']['tel'] = substr_replace($valur['member']['tel'], '****', 3, 4);
            }
        }

        return $this->response->json([
            'code' => 200,
            'msg'  => '查询成功',
            'data' => [
                'count' => $list->total(),
                'list'  => $data,
            ]
        ]);
    }

    /**
     * @DOC 加盟商审核
     */
    #[RequestMapping(path: "auditCheck", methods: "post")]
    public function auditCheck(RequestInterface $request): ResponseInterface
    {
        $memberRequest = $this->container->get(MemberRequest::class);
        $param         = $memberRequest->scene('auditCheck')->validated();
        $member        = $request->UserInfo;

        if (!in_array($param['status'], [0, 1, 2])) {
            throw new HomeException('状态错误');
        }

        $where                     = [
            ['member_uid', '=', $param['uid']],
            ['parent_agent_uid', '=', $member['uid']],
        ];
        $AgentData['agent_status'] = $InfoData['status'] = $param['status'];
        $AgentData['error']        = $InfoData['error'] = $param['error'];

        Db::transaction(function () use ($member, $AgentData, $InfoData, $where) {
            AgentMemberModel::where($where)->update($AgentData);
            MemberInfoModel::where($where)->update($InfoData);
        });
        return $this->response->json([
            'code' => 200,
            'msg'  => '审核成功',
            'data' => []
        ]);

    }

    /**
     * @DOC 证件审核列表
     */
    #[RequestMapping(path: "auditDocumentList", methods: "post")]
    public function auditDocumentList(RequestInterface $request): ResponseInterface
    {
        $member = $request->UserInfo;
        $param  = $request->all();

        switch ($member['role_id']) {
            case 1:
            case 2:
                $where[] = ['parent_agent_uid', '=', $member['parent_agent_uid']];
                break;
            case 3:
                $where[] = ['parent_agent_uid', '=', $member['parent_agent_uid']];
                $where[] = ['parent_join_uid', '=', $member['uid']];
                break;
            default:
                throw new HomeException('权限不足，不可查看信息');
        }

        if (Arr::hasArr($param, 'status', true)) {
            $where[] = ['agent_status', '=', $param['status']];
        }

        $list = AgentMemberModel::where($where)
            ->with(['member' => function ($memberData) {
                $memberData->select('uid', 'user_name', 'status', 'tel', 'head_url');
            }])
            ->whereHas('member.info', function ($info) {
                $info->where('status', '=', 1);
            })->select(['member_uid', 'amount', 'warning_amount', 'add_time']);
        if (Arr::hasArr($param, 'username')) {
            $list = $list->whereHas('member', function ($member) use ($param) {
                $member->where('user_name', 'like', '%' . $param['username'] . '%');
            });
        }
        $list = $list->paginate($param['limit'] ?? 20);

        $data  = $list->items();
        $crypt = new Crypt();
        foreach ($data as &$valur) {
            if (!empty($valur['member']['tel'])) {
                $tel                    = base64_decode($valur['member']['tel']);
                $valur['member']['tel'] = $crypt->decrypt($tel);
                // 手机号替换
                $valur['member']['tel'] = substr_replace($valur['member']['tel'], '****', 3, 4);
            }
        }

        return $this->response->json([
            'code' => 200,
            'msg'  => '查询成功',
            'data' => [
                'count' => $list->total(),
                'list'  => $list->items(),
            ]
        ]);
    }


    /**
     * @DOC 证件审核
     */
    #[RequestMapping(path: "auditDocumentCheck", methods: "post")]
    public function auditDocumentCheck(RequestInterface $request): ResponseInterface
    {
        $memberRequest = $this->container->get(MemberRequest::class);
        $param         = $memberRequest->scene('auditCheck')->validated();
        $member        = $request->UserInfo;

        if (!in_array($param['status'], [0, 1, 2])) {
            throw new HomeException('状态错误');
        }
        MemberInfoModel::where('member_uid', ($param['uid'] ?? 0))
            ->where('parent_join_uid', $member['uid'])
            ->where('parent_agent_uid', $member['parent_agent_uid'])
            ->update(['status' => $param['status'], 'error' => $param['error']]);
        return $this->response->json([
            'code' => 200,
            'msg'  => '审核成功',
            'data' => []
        ]);

    }

    /**
     * @DOC 首页展示信息
     */
    #[RequestMapping(path: "homeStatistics", methods: "post")]
    public function homeStatistics(RequestInterface $request): ResponseInterface
    {
        $member = $request->UserInfo;

        switch ($member['role_id']) {
            case 1:
            case 2:
                $where[] = ['parent_agent_uid', '=', $member['parent_agent_uid']];
                $data    = $this->agentStatistics($where, $member);
                return $this->response->json(['code' => 200, 'msg' => '统计完成', 'data' => $data]);
            case 3:
            case 10:
                $where['direct']  = [
                    ['parent_agent_uid', '=', $member['parent_agent_uid']],
                    ['parent_join_uid', '=', $member['uid']]
                ];
                $where['collect'] = [
                    ['send_agent_uid', '=', $member['parent_agent_uid']],
                    ['send_join_uid', '=', $member['uid']]
                ];
                $data             = $this->statistics($where, $member);
                return $this->response->json(['code' => 200, 'msg' => '统计完成', 'data' => $data]);
            case 4:
            case 5:
                $where['direct']  = [
                    ['parent_agent_uid', '=', $member['parent_agent_uid']],
                    ['parent_join_uid', '=', $member['parent_join_uid']],
                    ['member_uid', '=', $member['uid']]
                ];
                $where['collect'] = [
                    ['send_agent_uid', '=', $member['parent_agent_uid']],
                    ['send_join_uid', '=', $member['parent_join_uid']],
                    ['send_member_uid', '=', $member['uid']],
                ];
                $data             = $this->statistics($where, $member);
                return $this->response->json(['code' => 200, 'msg' => '统计完成', 'data' => $data]);
        }
        return $this->response->json(['code' => 200, 'msg' => '统计完成', 'data' => []]);
    }

    /**
     * @DOC 代理统计
     */
    public function agentStatistics($where, $member): array
    {
        # 待入库
        $data['warehousing'] = DeliveryStationModel::where('send_agent_uid', $member['parent_agent_uid'])->where('delivery_status', DeliveryStationModel::STATUS_WAIT_IN)->count('send_station_sn');
        # 待出库
        $data['out'] = DeliveryStationModel::where('send_agent_uid', $member['parent_agent_uid'])->where('delivery_status', DeliveryStationModel::STATUS_OUT)->count('send_station_sn');
        # 待报关
        $data['export'] = ParcelModel::where($where)->where('parcel_status', 80)->count();
        # 待运输
        $data['trunk'] = ParcelModel::where($where)->where('parcel_status', 110)->count();
        # 待清关
        $data['import'] = ParcelModel::where($where)->where('parcel_status', 130)->count();
        # 待配送
        $data['transport'] = ParcelTransportModel::where($where)->count();
        # 直邮订单
        $data['direct'] = OrderModel::where($where)
            ->where(function ($query) {
                $query->orWhereDoesntHave('prediction')
                    ->orWhereHas('prediction', function ($query) {
                        $query->where('parcel_type', DeliveryStationModel::TYPE_DIRECT);
                    });
            })->count('order_sys_sn');
        # 集运订单
        $data['collect'] = DeliveryStationModel::where('send_agent_uid', $member['parent_agent_uid'])->where('parcel_type', DeliveryStationModel::TYPE_COLLECT)->count('send_station_sn');
        $where[]         = ['agent_status', '=', 2];
        # 加盟商
        $data['join_count'] = AgentMemberModel::where($where)
            ->where('role_id', '=', 3)
            ->count();
        # 个人客户
        $data['that_person'] = AgentMemberModel::where($where)
            ->where('role_id', '=', 5)
            ->count();
        # 企业客户
        $data['enterprise'] = AgentMemberModel::where($where)
            ->where('role_id', '=', 4)
            ->count();
        # 仓储用户
        $data['storage'] = AgentMemberModel::where($where)
            ->where('role_id', '=', 10)
            ->count();

        $startDate = strtotime(date('Y-m-d', strtotime('-1 month')));
        $endDate   = time();

        # 新增加盟商
        $data['join_time_count'] = AgentMemberModel::where($where)
            ->where('role_id', '=', 3)
            ->whereBetween('add_time', [$startDate, $endDate])
            ->count();
        # 新增企业客户
        $data['enterprise_time'] = AgentMemberModel::where($where)
            ->where('role_id', '=', 4)
            ->whereBetween('add_time', [$startDate, $endDate])
            ->count();
        # 新增个人客户
        $data['that_time_person'] = AgentMemberModel::where($where)
            ->where('role_id', '=', 5)
            ->whereBetween('add_time', [$startDate, $endDate])
            ->count();
        # 新增仓储用户
        $data['that_time_storage'] = AgentMemberModel::where($where)
            ->where('role_id', '=', 10)
            ->whereBetween('add_time', [$startDate, $endDate])
            ->count();
        return [
            '待入库'       => $data['warehousing'],
            '待出库'       => $data['out'],
            '待报关'       => $data['export'],
            '待运输'       => $data['trunk'],
            '待清关'       => $data['import'],
            '待配送'       => $data['transport'],
            '直邮订单'     => $data['direct'],
            '集运订单'     => $data['collect'],
            '加盟商'       => $data['join_count'],
            '新增加盟商'   => $data['join_time_count'],
            '个人客户'     => $data['that_person'],
            '新增个人客户' => $data['that_time_person'],
            '企业客户'     => $data['enterprise'],
            '新增企业客户' => $data['enterprise_time'],
            '仓储用户'     => $data['storage'],
            '新增仓储用户' => $data['that_time_storage'],
        ];
    }

    /**
     * @DOC 加盟商统计
     */
    public function joinStatistics($where): array
    {
        $where[]   = ['agent_status', '=', 2];
        $startDate = strtotime(date('Y-m-d', strtotime('-1 month')));
        $endDate   = time();

        # 个人客户
        $data['that_person'] = AgentMemberModel::where($where)
            ->where('role_id', '=', 5)
            ->count();
        # 企业客户
        $data['enterprise'] = AgentMemberModel::where($where)
            ->where('role_id', '=', 4)
            ->count();

        # 新增企业客户
        $data['that_time_person'] = AgentMemberModel::where($where)
            ->where('role_id', '=', 4)
            ->whereBetween('add_time', [$startDate, $endDate])
            ->count();
        # 新增个人客户
        $data['enterprise_time'] = AgentMemberModel::where($where)
            ->where('role_id', '=', 5)
            ->whereBetween('add_time', [$startDate, $endDate])
            ->count();
        return $data;
    }

    /**
     * @DOC 客户统计
     */
    public function memberStatistics($where): array
    {
        # 异常订单
        $data['exceptions'] = OrderModel::where($where)->whereIn('order_status', [27])->count();
        # 订单转包
        $data['subcontract'] = OrderModel::where($where)->whereIn('order_status', [26, 28, 29])->count();
        # 取号异常
        $data['retrieval'] = OrderModel::where($where)->whereIn('order_status', [26, 28, 29])->count();
        # 待打印
        $data['print'] = ParcelModel::where($where)->where('parcel_status', '=', 42)->count();
        # 待发往集运仓库
        $data['send_warehousing'] = ParcelModel::where($where)->where('parcel_status', '=', 43)->count();
        # 补交运费
        $data['supplemented'] = ParcelModel::where($where)->whereHas('cost_member_item', function ($parcel) {
            $parcel->where('payment_status', '=', 0);
        })->count();
        # 异常风控截留
        $data['interception'] = ParcelModel::where($where)->whereHas('exception', function ($parcel) {
            $parcel->where('status', '<>', 3);
        })->count();
        return $data;
    }

    /**
     * @DOC 统计
     */
    public function statistics($where, $member)
    {
        // 判断 缓存中是否存在信息
//        $cache     = \Hyperf\Support\make(Cache::class);
//        $cache_key = 'home:statistics:' . date('Y/m/d') . ':' . $member['uid'];
//        if ($cache->has($cache_key)) {
//            return $cache->get($cache_key);
//        }
        $directLabel = [
            0 => '全部订单',
            1 => '补全订单',
            2 => '打单寄送',
            3 => '待入库',
            4 => '补交费用',
            5 => '待出库',
            6 => '已出库',
            7 => '问题订单',
        ];
        $data        = [];
        for ($i = 0; $i <= 7; $i++) {
            $order = OrderModel::query()
                ->where(function ($query) {
                    $query->orWhereDoesntHave('prediction')
                        ->orWhereHas('prediction', function ($query) {
                            $query->where('parcel_type', DeliveryStationModel::TYPE_DIRECT);
                        });
                })->where($where['direct']);
            switch ($i) {
                case 1:
                    $order = $order->whereIn('order_status', [27]);
                    $order->whereDoesntHave('parcelException');
                    break;
                case 2:
                    $order = $order
                        ->where(function ($query) {
                            $query->orWhereIn('order_status', [28, 29])
                                ->orWhere(function ($query) {
                                    $query->where('order_status', 30)->whereHas('parcel', function ($parcel) {
                                        $parcel->whereIn('parcel_status', [42, 43]);
                                    });
                                });
                        });
                    $order->whereDoesntHave('parcelException');
                    break;
                case 3:
                    $parcel_status = [50];
                    $order->whereDoesntHave('parcelException');
                    break;
                case 4:
                    $order->where('order_status', '!=', 28)
                        ->whereHas('cost_member_item', function ($parcel) {
                            $parcel->where('payment_status', '=', 0)->select(['order_sys_sn']);
                        })->whereDoesntHave('parcelException');
                    break;
                case 5:
                    $parcel_status = [55, 65];
                    $order->whereDoesntHave('cost_member_item', function ($parcel) {
                        $parcel->where('payment_status', '=', 0)->select(['order_sys_sn']);
                    })->whereDoesntHave('parcelException');
                    break;
                case 6:
                    $parcel_status = [80, 110, 130, 170, 210];
                    $order->whereDoesntHave('parcelException');
                    break;
                case 7:
                    $order->whereHas('parcelException', function ($parcel) {
                        $parcel->whereIn('status', [0, 2])->select(['order_sys_sn']);
                    });
                    break;
                default:
                    break;
            }
            // 包裹信息查询
            if (!empty($parcel_status)) {
                $order = $order->whereHas('parcel', function ($parcel) use ($parcel_status) {
                    $parcel->whereIn('parcel_status', $parcel_status);
                });
            }
            $data['direct'][$directLabel[$i]] = $order->count();
            unset($order, $parcel_status);
        }

        $collectLabel = [
            0 => '全部订单',
            1 => '待入库',
            2 => '已入库',
            3 => '待出库',
            4 => '已出库',
            5 => '认领包裹',
        ];

        for ($i = 0; $i <= 5; $i++) {
            $delivery_station = DeliveryStationModel::query()
                ->where($where['collect'])
                ->where('parcel_type', DeliveryStationModel::TYPE_COLLECT);
            switch ($i) {
                case 0:
                    $data['collect'][$collectLabel[$i]] = $delivery_station->count();
                    break;
                case 1:
                    $data['collect'][$collectLabel[$i]] = $delivery_station->where('delivery_status', DeliveryStationModel::STATUS_WAIT_IN)->count();
                    break;
                case 2:
                    $data['collect'][$collectLabel[$i]] = $delivery_station->where('delivery_status', DeliveryStationModel::STATUS_IN)->count();
                    break;
                case 3:
                    $data['collect'][$collectLabel[$i]] = OrderModel::query()->whereHas('predictionParcel', function ($parcel) use ($where) {
                        $parcel->where('delivery_status', DeliveryStationModel::STATUS_OUT)->where($where['collect']);
                    })->count();
                    break;
                case 4:
                    $data['collect'][$collectLabel[$i]] = $delivery_station->where('delivery_status', DeliveryStationModel::STATUS_SEND)->count();
                    break;
                case 5:
                    $where                              = [
                        ['send_member_uid', '=', 0],
                        ['send_agent_uid', '=', $member['parent_agent_uid']]
                    ];
                    $data['collect'][$collectLabel[$i]] = DeliveryStationModel::where($where)->count();
                    break;
                default:
                    break;
            }
        }

//        $cache->set($cache_key, $data, 86400);
        return $data;
    }

    /**
     * @DOC 生成微信关注扫码二维码
     */
    #[RequestMapping(path: "wxQrcode", methods: "post")]
    public function wxQrcode(RequestInterface $request): ResponseInterface
    {
        $redis  = \Hyperf\Support\make(Redis::class);
        $member = $request->UserInfo;

        $memberOpenid = AgentMemberModel::where('member_uid', $member['uid'])
            ->where('parent_join_uid', $member['parent_join_uid'])
            ->where('parent_agent_uid', $member['parent_agent_uid'])
            ->value('openid');

        if ($memberOpenid) {
            return $this->response->json(['code' => 200, 'status' => 0, 'msg' => '当前账号已绑定，请先进行解绑', 'data' => []]);
        }

        # 获取用户的 appid 和 secret
        list($access_token, $msg) = SendTemplate::getAccessToken($member);
        if (!$access_token) {
            throw new HomeException($msg);
        }
        # 将数据进行缓存
        $key = $member['uid'] . $member['parent_join_uid'] . $member['parent_agent_uid'];
        $val = $member['uid'] . ',' . $member['parent_join_uid'] . ',' . $member['parent_agent_uid'];
        # 存储用户：fd
        $redis->set('wx_qrcode:' . $key, $val, 600);

        # 获取公众号二维码
        $qrcode  = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=' . $access_token;
        $data    = [
            'expire_seconds' => 3600,
            'action_name'    => 'QR_SCENE',
            'action_info'    => [
                'scene' => [
                    'scene_id' => $key
                ]
            ]
        ];
        $options = [
            'http' => [
                'method'        => 'POST',
                'header'        => 'Content-Type: application/json',
                'content'       => json_encode($data),
                'ignore_errors' => true // 忽略错误响应
            ]
        ];

        $context  = stream_context_create($options);
        $response = file_get_contents($qrcode, false, $context);

        if ($response !== false) {
            # 处理响应数据
            $result = json_decode($response, true);
            if ($result && isset($result['ticket'])) {
                $ticket = $result['ticket'];
                # 返回公众号二维码
                $qrcodeUrl = 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=' . urlencode($ticket);
                return $this->response->json(['code' => 200, 'status' => 1, 'msg' => '获取成功', 'data' => ['url' => $qrcodeUrl]]);
            }
        }
        return $this->response->json(['code' => 201, 'msg' => '获取失败', 'data' => []]);
    }

    /**
     * @DOC 设置汇率
     */
    #[RequestMapping(path: 'rate', methods: 'post')]
    public function rate(RequestInterface $request): ResponseInterface
    {
        $param    = $request->all();
        $userInfo = $request->UserInfo;

        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$userInfo]);
        $LibValidation->validate($param,
            [
                'source_currency_id' => ['required', 'integer'],
                'target_currency_id' => ['required', 'integer'],
                'rate'               => ['required', 'numeric'],
            ], [
                'source_currency_id.required' => '支付币种必传',
                'source_currency_id.integer'  => '支付币种错误',
                'target_currency_id.required' => '入账币种必传',
                'target_currency_id.integer'  => '入账币种错误',
                'rate.required'               => '汇率必传',
                'rate.numeric'                => '汇率错误',
            ]);

        if ($userInfo['role_id'] != 1) throw new HomeException('仅限平台代理设置');

        $time = time();

        $rateData = [
            'agent_platform_uid' => $userInfo['uid'],
            'source_currency_id' => $param['source_currency_id'],
            'target_currency_id' => $param['target_currency_id'],
            'rate'               => $param['rate'],
            'update_time'        => $time,
        ];
        $where[]  = ['agent_platform_uid', '=', $userInfo['uid']];
        $rate     = AgentRateModel::where($where)->exists();
        if ($rate) {
            # 更新
            AgentRateModel::where($where)->update($rateData);
        } else {
            # 新增
            $rateData['add_time'] = $time;
            AgentRateModel::insert($rateData);
        }
        return $this->response->json(['code' => 200, 'msg' => '设置完成', 'data' => []]);
    }

    /**
     * @DOC 加盟商列表
     */
    #[RequestMapping(path: 'agent', methods: 'post')]
    public function agent(RequestInterface $request): ResponseInterface
    {
        $param    = $request->all();
        $useWhere = $this->useWhere();
        $where    = $useWhere['where'];
        $where[]  = ['role_id', '=', 3];
        $where[]  = ['agent_status', '=', 2];
        if (Arr::hasArr($param, 'parent_join_uid')) {
            $where[] = ['parent_join_uid', '=', $param['parent_join_uid']];
        }

        $list = AgentMemberModel::where($where);

        if (Arr::hasArr($param, 'nick_name')) {
            $list = $list->whereHas('member', function ($query) use ($param) {
                $query->where('nick_name', '=', $param['nick_name']);
            });
        }
        $list = $list->with(['member' => function ($query) {
            $query->select(['uid', 'user_name', 'role_id', 'nick_name', 'email', 'tel', 'reg_time', 'head_url']);
        }, 'role', 'platform'         => function ($query) {
            $query->select(['agent_platform_uid', 'currency', 'default_join_uid']);
        }])->orderBy('add_time', 'desc')->paginate($param['limit'] ?? 20);

        $data = $list->items();
        foreach ($data as &$v) {
            if ($v['member']['tel']) {
                $tel['tel']         = $v['member']['tel'];
                $v['member']['tel'] = $this->memberDecrypt($tel, true)['tel'];
            }
            # 授信额度
            $v['residue'] = number_format($v['warning_amount'], 2);
            if ($v['amount'] < 0) {
                $v['residue'] = number_format($v['warning_amount'] + $v['amount'], 2);
                if ($v['residue'] < 0) $v['residue'] = 0.00;
            }
            $v['residue']         = str_replace(',', '', $v['residue']);
            $v['is_default_join'] = 0;
            if ($v['platform']['default_join_uid'] == $v['member_uid']) {
                $v['is_default_join'] = 1;
            }

        }

        return $this->response->json([
                'code' => 200,
                'msg'  => '查询成功',
                'data' => [
                    'total' => $list->total(),
                    'data'  => $data,
                ]
            ]
        );
    }

    /**
     * @DOC 用户列表
     */
    #[RequestMapping(path: 'user', methods: 'post')]
    public function user(RequestInterface $request): ResponseInterface
    {
        $param = $request->all();
        $data  = $this->membersService->memberLists($param);
        return $this->response->json($data);
    }

    /**
     * @DOC 加盟商————小程序配置信息列表
     */
    #[RequestMapping(path: 'app/lists', methods: 'post')]
    public function appLists(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($param,
            [
                'page'  => ['required', 'integer'],
                'limit' => ['required', 'integer'],
            ], [
                'page.required'  => '页码必传',
                'page.integer'   => '页码错误',
                'limit.required' => '条数必传',
                'limit.integer'  => '条数错误',
            ]);
        $member        = $request->UserInfo;
        $data          = MemberJoinAppModel::with([
            'jsons' => function ($query) {
                $query->select(['uid', 'user_name', 'nick_name']);
            }
        ])
            ->where('member_agent_uid', $member['parent_agent_uid'])
            ->select(['id', 'mch_id', 'app_id', 'member_join_uid', 'add_time'])
            ->paginate((int)$param['limit']);
        return $this->response->json([
            'code' => 200,
            'msg'  => '查询成功',
            'data' => [
                'total' => $data->total(),
                'data'  => $data->items()
            ],
        ]);
    }

    /**
     * @DOC 加盟商————小程序配置信息
     */
    #[RequestMapping(path: 'app/info', methods: 'post')]
    public function appInfo(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;

        if (!empty($param['id'])) {
            $where[] = ['id', '=', $param['id']];
        } else {
            $where[] = ['member_join_uid', '=', $member['uid']];
        }
        $where[] = ['member_agent_uid', '=', $member['parent_agent_uid']];
        $data    = MemberJoinAppModel::where($where)->first();
        return $this->response->json([
            'code' => 200,
            'msg'  => '查询成功',
            'data' => $data,
        ]);
    }

    /**
     * @DOC 加盟商————小程序配置
     */
    #[RequestMapping(path: "app/save", methods: "post")]
    public function saveApp(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        // 验证加盟商
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($param,
            [
                'mch_id'          => ['nullable'],
                'app_id'          => ['required'],
                'app_key'         => ['nullable'],
                'app_secret'      => ['nullable'],
                'wechat_appid'    => ['nullable'],
                'wechat_secret'   => ['nullable'],
                'cert_content'    => ['nullable'],
                'key_content'     => ['nullable'],
                'member_join_uid' => ['required'],
            ], [
                'app_id.required'          => '小程序APPID不能为空',
                'member_join_uid.required' => '未选择加盟商',
            ]);

        // 判断用户是否属于当前代理下的加盟商
        $join = AgentMemberModel::where('parent_agent_uid', $member['parent_agent_uid'])
            ->where('parent_join_uid', '=', 0)
            ->where('member_uid', $param['member_join_uid'])
            ->first();
        if (empty($join)) {
            throw new HomeException('当前代理下不存在该加盟商');
        }

        $time = time();
        $data = [
            'member_join_uid'  => $param['member_join_uid'],
            'member_agent_uid' => $member['uid'],
            'mch_id'           => trim($param['mch_id']),
            'app_id'           => trim($param['app_id']),
            'app_key'          => trim($param['app_key']),
            'app_secret'       => trim($param['app_secret']),
            'wechat_appid'     => trim($param['wechat_appid']),
            'wechat_secret'    => trim($param['wechat_secret']),
            'update_time'      => $time
        ];
        if (!empty($param['cert_content'])) {
            $certContent = trim($param['cert_content']);
            if (empty($certContent) ||
                !preg_match('/-----BEGIN CERTIFICATE-----.*-----END CERTIFICATE-----/s', $certContent)) {
                throw new HomeException('证书内容格式无效');
            }
            $path      = env('CERT_PATH');
            $cert_path = $path . $param['member_join_uid'] . '/cert_content.pem';
            if (!file_exists($path . $param['member_join_uid'])) {
                mkdir($path . $param['member_join_uid'], 0777, true);
            }
            $file = fopen($cert_path, 'w');
            fwrite($file, $param['cert_content']);
            fclose($file);

            // 写入文件并验证
            if (file_put_contents($cert_path, $certContent) === false) {
                throw new HomeException("证书文件保存失败: {$cert_path}");
            }
            $data['cert_content'] = $param['cert_content'];
            $data['cert_path']    = $cert_path;


        }
        if (!empty($param['key_content'])) {
            $path     = env('CERT_PATH');
            $key_path = $path . $param['member_join_uid'] . '/key_content.pem';
            if (!file_exists($path . $param['member_join_uid'])) {
                mkdir($path . $param['member_join_uid'], 0777, true);
            }
            $file = fopen($key_path, 'w');
            fwrite($file, $param['key_content']);
            fclose($file);
            $data['key_content'] = $param['key_content'];
            $data['key_path']    = $key_path;
        }

        $ret = MemberJoinAppModel::where('member_join_uid', $param['member_join_uid'])
            ->where('member_agent_uid', $member['parent_agent_uid'])
            ->first();
        if ($ret) {
            if (MemberJoinAppModel::where('id', '!=', $ret->id)
                ->where('app_id', $data['app_id'])
                ->exists()) {
                throw new HomeException('小程序已被使用，请更换APPID');
            }
            MemberJoinAppModel::where('id', $ret->id)->update($data);
        } else {
            if (MemberJoinAppModel::where('app_id', $param['app_id'])->exists()) {
                throw new HomeException('小程序已被使用，请更换APPID');
            }
            $data['add_time'] = $time;
            MemberJoinAppModel::insert($data);
        }
        return $this->response->json(['code' => 200, 'msg' => '配置成功', 'data' => []]);
    }

    /**
     * @DOC 获取代理币种参考费率
     */
    #[RequestMapping(path: 'refer', methods: 'get,post')]
    public function refer(RequestInterface $request)
    {
        $member = $this->request->UserInfo;
        $result = (new MembersService())->rate($member);
        return $this->response->json($result);
    }

    /**
     * @DOC 更新代理下的默认加盟商
     */
    #[RequestMapping(path: 'join/default', methods: 'get,post')]
    public function joinDefault(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        // 验证加盟商
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($param,
            [
                'member_uid' => ['required'],
            ], [
                'member_uid.required' => '请选择加盟商ID',
            ]);

        // 校验加盟商
        $joinData = AgentMemberModel::where('member_uid', $param['member_uid'])
            ->where('parent_join_uid', '=', 0)
            ->where('parent_agent_uid', $member['parent_agent_uid'])
            ->select(['member_uid'])
            ->first();
        if (empty($joinData)) {
            throw new HomeException('当前代理下不存在该加盟商');
        }

        if (AgentPlatformModel::where('agent_platform_uid', $member['parent_agent_uid'])->update(['default_join_uid' => $param['member_uid']])) {
            //更新缓存
            make(BaseEditUpdateCacheService::class)->AgentPlatformCache();
            return $this->response->json(['code' => 200, 'msg' => '设置成功', 'data' => []]);
        }
        return $this->response->json(['code' => 201, 'msg' => '设置失败', 'data' => []]);
    }

}
