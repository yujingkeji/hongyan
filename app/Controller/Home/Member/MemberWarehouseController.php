<?php

namespace App\Controller\Home\Member;

use App\Common\Lib\Crypt;
use App\Controller\Home\HomeBaseController;
use App\Model\AgentMemberModel;
use App\Model\MemberModel;
use App\Model\WarehouseModel;
use App\Request\LibValidation;
use App\Service\LoginService;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: "member/member/warehouse")]
class MemberWarehouseController extends HomeBaseController
{
    /**
     * @DOC 代理下的仓库管理员列表
     */
    #[RequestMapping(path: "", methods: "post")]
    public function index(RequestInterface $request): ResponseInterface
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $query  = AgentMemberModel::query()
            ->with([
                'member'    => function ($query) {
                    $query->select(['uid', 'user_name', 'role_id', 'nick_name', 'email', 'tel', 'reg_time', 'head_url', 'area_code', 'desc']);
                },
                'role'      => function ($query) {
                    $query->select(['name', 'role_id']);
                },
                'joins'     => function ($query) {
                    $query->select(['uid', 'user_name', 'role_id', 'nick_name', 'email', 'tel', 'reg_time']);
                },
                'platform'  => function ($query) {
                    $query->select(['agent_platform_uid', 'currency']);
                },
                'warehouse' => function ($query) {
                    $query->select(['ware_id', 'ware_name']);
                }
            ])
            ->where('parent_agent_uid', $member['parent_agent_uid'])
            ->where('role_id', 10);

        // 名称
        if (!empty($params['name'])) {
            $query->whereHas('member', function ($query) use ($params) {
                $query->where(function ($query) use ($params) {
                    $query->orWhere('user_name', 'like', '%' . $params['name'] . '%');
                    $query->orWhere('nick_name', 'like', '%' . $params['name'] . '%');
                });
            });
        }
        // 注册编码
        if (!empty($params['code'])) {
            $query->where('code', $params['code']);
        }

        $count = $query->count('agent_member_uid');
        $data  = $query->select(['agent_member_uid', 'member_uid', 'parent_join_uid', 'parent_agent_uid', 'role_id', 'code', 'add_time', 'warehouse_id'])
            ->forPage($param['page'] ?? 1, $param['limit'] ?? 20)->get()->toArray();

        foreach ($data as &$v) {
            if (!empty($v['member']['tel'])) {
                $tel['tel']         = $v['member']['tel'];
                $v['member']['tel'] = $this->memberDecrypt($tel, true)['tel'];
            }
        }

        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => [
                'total' => $count,
                'data'  => $data,
            ]
        ]);
    }

    /**
     * @DOC 代理下的仓库管理员 新增
     */
    #[RequestMapping(path: "add", methods: "post")]
    public function add(RequestInterface $request)
    {
        $params        = $request->all();
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'user_name'    => 'required',
                'nick_name'    => 'required',
                'password'     => 'required|min:8',
                'desc'         => 'nullable',
                'area_code'    => 'required',
                'tel'          => 'required|min:11|max:11',
                'warehouse_id' => 'required',
            ], [
                'user_name.required'    => '用户名不能为空',
                'nick_name.required'    => '昵称不能为空',
                'password.required'     => '密码不能为空',
                'password.min'          => '密码长度至少8位',
                'desc.required'         => '描述不能为空',
                'area_code.required'    => '地区编码不能为空',
                'tel.required'          => '手机号不能为空',
                'tel.min'               => '手机号长度不正确',
                'warehouse_id.required' => '请选择授权的仓库',
            ]
        );

        // 验证用户名与昵称是否已经存在
        $member_name = MemberModel::query()->where('user_name', $param['user_name'])->value('uid');
        if ($member_name) {
            return $this->response->json(['code' => 201, 'msg' => '用户名已存在']);
        }
        $nick_name = MemberModel::query()->where('nick_name', $param['nick_name'])->value('uid');
        if ($nick_name) {
            return $this->response->json(['code' => 201, 'msg' => '昵称已存在']);
        }
        // 手机号验证是否正确
        $pattern = '/^1[3-9]\d{9}$/';
        if (!preg_match($pattern, $param['tel'])) {
            return $this->response->json(['code' => 201, 'msg' => '手机号不合法']);
        }
        $tel      = base64_encode((new Crypt())->encrypt($param['tel']));
        $hash     = LoginService::random(8, null, '@#$%^&*()');
        $password = (new LoginService())->mkPw($param['user_name'], $param['password'], $hash);
        // 用户信息 member
        $memberData = [
            'user_name'     => $param['user_name'],
            'nick_name'     => $param['nick_name'],
            'user_password' => $password,
            'head_url'      => 'https://open-hjd.oss-cn-hangzhou.aliyuncs.com/yfd/user/icon/head/head_' . rand(1, 36) . '.png',
            'desc'          => $param['desc'] ?? '',
            'area_code'     => $param['area_code'],
            'tel'           => $tel,
            'hash'          => $hash,
            'role_id'       => 10,
            'status'        => 1,
            'reg_time'      => time(),
        ];
        // 检查仓库
        $warehouse = WarehouseModel::query()
            ->where('ware_id', $param['warehouse_id'])
            ->where('member_uid', $member['uid'])
            ->where('status', 1)
            ->first();
        if (empty($warehouse)) {
            return $this->response->json(['code' => 201, 'msg' => '所授权仓库不存在或未开启']);
        }
        // agent_member
        $agentMemberData = [
            'parent_join_uid'  => 0,
            'parent_agent_uid' => $member['parent_agent_uid'],
            'role_id'          => 10,
            'agent_status'     => 2,
            'add_time'         => time(),
            'warehouse_id'     => $param['warehouse_id'],
        ];

        Db::beginTransaction();
        try {
            $member_uid                    = Db::table('member')->insertGetId($memberData);
            $agentMemberData['member_uid'] = $member_uid;
            $agentMemberData['code']       = 'W' . str_pad($member_uid, 4, '0', STR_PAD_LEFT);
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
     * @DOC 代理下的仓库管理员 编辑
     */
    #[RequestMapping(path: "edit", methods: "post")]
    public function edit(RequestInterface $request)
    {
        $params        = $request->all();
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'member_uid'   => 'required',
                'warehouse_id' => 'required',
            ], [
                'member_uid.required'   => '用户ID不能为空',
                'warehouse_id.required' => '请选择授权的仓库',
            ]
        );
        // 检查用户
        $agentMember = AgentMemberModel::query()
            ->where('member_uid', $param['member_uid'])
            ->where('parent_agent_uid', $member['parent_agent_uid'])
            ->where('role_id', 10)
            ->first();
        if (empty($agentMember)) {
            return $this->response->json(['code' => 201, 'msg' => '用户不存在或未授权']);
        }

        // 检查仓库
        $warehouse = WarehouseModel::query()
            ->where('ware_id', $param['warehouse_id'])
            ->where('member_uid', $member['uid'])
            ->where('status', 1)
            ->first();
        if (empty($warehouse)) {
            return $this->response->json(['code' => 201, 'msg' => '所授权仓库不存在或未开启']);
        }

        $isUpdate = AgentMemberModel::query()
            ->where('member_uid', $param['member_uid'])
            ->where('parent_agent_uid', $member['parent_agent_uid'])
            ->where('role_id', 10)->update(['warehouse_id' => $param['warehouse_id']]);
        if ($isUpdate) {
            return $this->response->json(['code' => 200, 'msg' => '编辑成功']);
        }
        return $this->response->json(['code' => 201, 'msg' => '编辑失败']);
    }

}
