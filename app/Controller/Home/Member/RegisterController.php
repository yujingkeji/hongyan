<?php

namespace App\Controller\Home\Member;

use App\Common\Lib\Crypt;
use App\Common\Lib\Str;
use App\Controller\Home\AbstractController;
use App\Exception\HomeException;
use App\JsonRpc\BaseServiceInterface;
use App\Model\AgentMemberModel;
use App\Model\MemberModel;
use App\Request\LibValidation;
use App\Service\Cache\BaseCacheService;
use App\Service\LoginService;
use App\Service\SmsService;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: "member/register")]
class RegisterController extends AbstractController
{

    /**
     * @DOC 注册 -- 注册平台下用户
     */
    #[RequestMapping(path: "member", methods: "post")]
    public function member(RequestInterface $request): ResponseInterface
    {
        $params        = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $params        = $LibValidation->validate($params,
            [
                'username'              => ['required', 'string', 'min:4', Rule::unique('member', 'user_name')],
                'password'              => ['required', 'confirmed', 'string', 'min:8'],
                'password_confirmation' => ['required', 'string'],
                'register_code'         => ['string', 'min:4'],
                #'mobile'                => ['required', 'min:11', 'max:11'],
                #'area_code'             => ['required'],
                #'code'                  => ['required', 'min:4'],
                'token'                 => 'required|string',
                'point'                 => 'required|string',
            ],
            [
                'username.required'              => '缺少用户名',
                'username.string'                => '用户名必须是字符',
                'username.min'                   => '用户名最少4位字符',
                'username.unique'                => '用户名已存在',
                'password.required'              => '缺少密码',
                'password.confirmed'             => '密码不一致',
                'password.string'                => '密码必须是字符',
                'password.min'                   => '密码最少8位',
                'password_confirmation.required' => '缺少确认密码',
                'register_code.string'           => '加盟商编码必须是字符',
                'register_code.min'              => '加盟商编码最少4位',
                # 'mobile.required'                => '缺少手机号',
                # 'mobile.min'                     => '手机号格式错误',
                # 'mobile.max'                     => '手机号格式错误',
                # 'area_code.required'             => '缺少手机区号',
                # 'code.required'                  => '缺少验证码',
                # 'code.min'                       => '验证码最少4位',
            ]
        );

        $resultCaptcha = make(BaseServiceInterface::class)->captchaCheck($params);
        if ($resultCaptcha['code'] != 200) {
            throw new HomeException($resultCaptcha['repMsg']);
        }

        # 校验编码
        /*  $agentMember = AgentMemberModel::where('code', $params['register_code'])
              ->select(['member_uid', 'parent_agent_uid', 'code'])->first();
          if (!$agentMember) {
              throw new HomeException('注册编码错误，请重新填写');
          }*/


        $params['referer'] = $request->getHeaderLine('origin');
        $LoginService      = \Hyperf\Support\make(LoginService::class);
        $agentMember       = $LoginService->checkAgentPlatform($params);

        // 判断当前代理无默认加盟商
        if ($agentMember['default_join_uid'] == 0) {
            throw new HomeException('注册失败，未检测到默认加盟商');
        }

        // 用户信息
        $hash                        = Str::random(6);
        $memberData                  = $this->checkUsername(params: $params, role: 5, hash: $hash);
        $memberData['user_password'] = $LoginService->mkPw($params['username'], $params['password'], $hash);

        $agentMemberData = [
            'parent_join_uid'  => $agentMember['default_join_uid'],
            'parent_agent_uid' => $agentMember['agent_platform_uid'],
            'role_id'          => 5,
            'agent_status'     => 2,
            'add_time'         => time(),
        ];

        Db::beginTransaction();
        try {
            $member_uid                    = Db::table('member')->insertGetId($memberData);
            $agentMemberData['member_uid'] = $member_uid;
            $agentMemberData['code']       = 'M' . str_pad($member_uid, 4, '0', STR_PAD_LEFT);
            Db::table('agent_member')->insert($agentMemberData);
            Db::commit();
            $result['code'] = 200;
            $result['msg']  = '注册成功';
        } catch (\Throwable $e) {
            Db::rollBack();
            $result['code'] = 201;
            $result['msg']  = '注册失败：' . $e->getMessage();
        }

        return $this->response->json($result);
    }


    /**
     * @DOC
     * @Name   checkUsername
     * @Author wangfei
     * @date   2023/10/27 2023
     * @param array $params
     * @param int $role
     * @param string $hash
     * @return array
     */
    # 验证账号信息
    public function checkUsername(array $params, int $role, string $hash)
    {
        return [
            'head_url'  => 'https://open-hjd.oss-cn-hangzhou.aliyuncs.com/yfd/user/icon/head/head_' . rand(1, 36) . '.png',
            'user_name' => $params['username'],
            'nick_name' => $params['username'],
            'hash'      => $hash,
            'reg_time'  => time(),
            'status'    => 2, // 等待审核状态
            'role_id'   => $role, // 角色关系
        ];

    }

    /**
     * @DOC 注册 -- 注册平台下 加盟商
     */
    #[RequestMapping(path: "join", methods: "post")]
    public function join(RequestInterface $request): ResponseInterface
    {
        $params            = $request->all();
        $LibValidation     = \Hyperf\Support\make(LibValidation::class);
        $params            = $LibValidation->validate($params,
            [
                'username'              => ['required', 'string', 'min:4', Rule::unique('member', 'user_name')],
                'password'              => ['required', 'confirmed', 'string', 'min:8'],
                'password_confirmation' => ['required', 'string'],
            ],
            [
                'username.required'              => '缺少用户名',
                'username.string'                => '用户名必须是字符',
                'username.min'                   => '用户名最少4位字符',
                'username.unique'                => '用户名已存在',
                'password.required'              => '缺少密码',
                'password.confirmed'             => '密码不一致',
                'password.string'                => '密码必须是字符',
                'password.min'                   => '密码最少8位',
                'password_confirmation.required' => '缺少确认密码',
            ]
        );
        $params['referer'] = $request->getHeaderLine('referer');
        $LoginService      = \Hyperf\Support\make(LoginService::class);
        $AgentPlatform     = $LoginService->checkAgentPlatform($params);


        # 解析域名地址
        $hash = Str::random(6);
        # 注册信息
        $memberData                  = $this->checkUsername(params: $params, role: 3, hash: $hash);
        $memberData['user_password'] = $LoginService->mkPw($params['username'], $params['password'], $hash);
        $agentMemberData             = [
            'parent_join_uid'  => 0,
            'parent_agent_uid' => $AgentPlatform['agent_platform_uid'],
            'role_id'          => 3,
            'agent_status'     => 1,
            'add_time'         => time(),
        ];

        Db::beginTransaction();
        try {
            $member_uid                    = Db::table('member')->insertGetId($memberData);
            $agentMemberData['member_uid'] = $member_uid;
            $agentMemberData['code']       = 'D' . str_pad($member_uid, 4, '0', STR_PAD_LEFT);
            Db::table('agent_member')->insert($agentMemberData);
            Db::commit();
            $result['code'] = 200;
            $result['msg']  = '注册成功';
        } catch (\Throwable $e) {
            Db::rollBack();
            $result['code'] = 201;
            $result['msg']  = '注册失败：' . $e->getMessage();
        }

        if ($result['code'] == 200) {
            $LoginResult    = $LoginService->check($params);
            $result['data'] = [
                'token' => $LoginResult['token'] ?? ''
            ];
        }
        return $this->response->json($result);
    }


    /**
     * @DOC 注册账号，发送手机验证码
     */
    #[RequestMapping(path: "send/code", methods: "post")]
    public function sendCode(): ResponseInterface
    {
        $params        = $this->request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'area_code'          => ['required', 'string', 'min:2'],
                'mobile'             => ['required', 'string', 'min:11'],
                'agent_platform_uid' => ['required', 'integer'],
            ],
            [
                'area_code.required'          => '请输入国家区号',
                'area_code.string'            => '国家区号必须为字符串',
                'area_code.min'               => '国家区号最少为2个字符',
                'mobile.required'             => '请输入手机号',
                'mobile.string'               => '手机号必须为字符串',
                'mobile.min'                  => '手机号最少为11个字符',
                'agent_platform_uid.required' => '请输入平台id',
                'agent_platform_uid.integer'  => '平台id必须为整数',
            ]
        );
        $tel           = base64_encode((new Crypt())->encrypt($param['mobile']));
        # 验证手机号唯一
        if (MemberModel::where('tel', $tel)->exists()) {
            throw new HomeException('当前手机号已存在，不可进行注册');
        }
        $service        = \Hyperf\Support\make(SmsService::class);
        $service->power = false;
        $service->send($param['area_code'], $param['mobile'], 4, $param['agent_platform_uid']);
        return $this->response->json(['code' => 200, 'msg' => 'success', 'data' => []]);
    }

    /**
     * @DOC 获取手机号的国际区号数据
     */
    #[RequestMapping(path: "area", methods: "post")]
    public function listMobileAreaCode(): ResponseInterface
    {
        $data = \Hyperf\Support\make(BaseCacheService::class)->CountryCodeCache();
        $data = array_map(function ($item) {
            if (!empty($item['zip_code'])) {
                return [
                    'area_code' => $item['zip_code'],
                    'area_name' => $item['country_name'],
                ];
            }
        }, $data);
        $data = array_filter($data, function ($value) {
            return !is_null($value);
        });
        return $this->response->json(['code' => 200, 'msg' => 'success', 'data' => $data]);
    }


}
