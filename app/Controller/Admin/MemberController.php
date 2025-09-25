<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Common\Lib\Arr;
use App\Common\Lib\Crypt;
use App\Common\Lib\Str;
use App\Exception\HomeException;
use App\Model\AdminUserModel;
use App\Model\MemberModel;
use App\Request\LibValidation;
use App\Service\LoginService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: '/', server: 'httpAdmin')]
class MemberController extends AdminBaseController
{

    protected $adminUID = [1]; //此管理员不能被查询到，也不能对此 管理员做任何操作

    /**
     * @DOC 用户信息
     */
    #[RequestMapping(path: 'member/info', methods: 'post')]
    public function info(RequestInterface $request): ResponseInterface
    {
        $data = [
            'member'       => $request->UserInfo,
            'name'         => $request->UserInfo['user_name'],
            'avatar'       => '',
            'introduction' => '',
            'roles'        => [1]
        ];
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
    }


    /**
     * @DOC 列表，已经查询接口
     */
    #[RequestMapping(path: 'member/lists', methods: 'post')]
    public function lists(RequestInterface $request): ResponseInterface
    {
        $result['code'] = 201;
        $result['msg']  = '获取失败';

        $param = $request->all();
        $data  = AdminUserModel::query()->whereNotIn('uid', $this->adminUID);
        if (Arr::hasArr($param, 'keyword')) {
            $data = $data->where(function ($query) use ($param) {
                $query->orWhere('user_name', 'like', '%' . $param['keyword'] . '%')
                    ->orWhere('nick_name', 'like', '%' . $param['keyword'] . '%')
                    ->orWhere('real_name', 'like', '%' . $param['keyword'] . '%');
            });
        }
        if (Arr::hasArr($param, 'status') && $param['status'] >= 0) {
            $data = $data->where('status', $param['status']);
        }
        if (Arr::hasArr($param, 'role_id')) {
            $data = $data->where('role_id', $param['role_id']);

        }
        $data  = $data->paginate($param['limit'] ?? 20);
        $items = $data->items();
        foreach ($items as $key => $val) {
            try {
                $mobile = (new Crypt)->decrypt((string)$val['mobile']);
                if (strpos('解密失败', $mobile)) {
                    $items[$key]['mobile'] = $mobile;
                } else {
                    $items[$key]['mobile'] = Str::centerStar($mobile);
                }

            } catch (\Exception $e) {
                $items[$key]['mobile'] = $val['mobile'];
            }
        }
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = [
            'total' => $data->total(),
            'data'  => $items,
        ];
        return $this->response->json($result);
    }

    /**
     * @DOC 管理员添加
     */
    #[RequestMapping(path: 'member/add', methods: 'post')]
    public function memberAdd(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $param          = $request->all();

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param,
            [
                'username' => ['required', 'min:3', 'max:25'],
                'password' => ['required', 'min:8', 'max:25'],
                'role_id'  => ['required'],
                'nickname' => ['required'],
                //                'status'   => ['required', Rule::in([0, 1])],
            ], [
                'username.required' => '账号必填',
                'username.min'      => '账号不少于3位',
                'username.max'      => '账号不大于25位',
                'password.required' => '密码必填',
                'password.min'      => '密码不少于8位',
                'password.max'      => '密码不大于25位',
                'role_id.required'  => '角色权限必填',
                'nickname.required' => '用户昵称必填',
                'status.required'   => '状态错误',
                'status.in'         => '状态错误',
            ]);
        $adminEx = AdminUserModel::where('user_name', $param['username'])->exists();
        if ($adminEx) {
            throw new HomeException('账号已存在');
        }
        $adminEx = AdminUserModel::where('nick_name', $param['nickname'])->exists();
        if ($adminEx) {
            throw new HomeException('昵称已存在');
        }
        unset($adminEx);

        if (isset($param['mobile']) && !empty($param['mobile'])) {
            $data['mobile'] = (new Crypt)->encrypt($param['mobile']);
        }


        $data['user_name']     = $param['username'];
        $data['nick_name']     = $param['nickname'];
        $data['real_name']     = $param['realname'] ?? '';
        $data['user_password'] = $param['password'];
        $data['email']         = $param['email'] ?? '';
        $data['role_id']       = $param['role_id'];
        $data['desc']          = $param['desc'] ?? '';
        $data['status']        = $param['status'] ?? 1;
        $data['reg_time']      = date("Y-m-d H:i:s");
        $data['hash']          = LoginService::random(8, null, '@#$%^&*()');
        $data['user_password'] = (new LoginService())->mkPw($param['username'], $param['password'], $data['hash'], 'LoginAdminHASH');

        if (AdminUserModel::insert($data)) {
            $result['code'] = 200;
            $result['msg']  = '添加成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 管理员编辑
     */
    #[RequestMapping(path: 'member/edit', methods: 'post')]
    public function memberEdit(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $param          = $request->all();

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param,
            [
                'uid'      => ['required'],
                'username' => ['required', 'min:3', 'max:25'],
                'role_id'  => ['required'],
                'nickname' => ['required'],
                //                'status'   => ['required', Rule::in([0, 1])],
            ], [
                'uid.required'      => '账号不存在',
                'username.required' => '账号必填',
                'username.min'      => '账号不少于3位',
                'username.max'      => '账号不大于25位',
                'role_id.required'  => '角色权限必填',
                'nickname.required' => '用户昵称必填',
                'status.required'   => '状态错误',
                'status.in'         => '状态错误',
            ]);

        if (in_array($param['uid'], $this->adminUID)) {
            throw new HomeException('账号不可操作');
        }

        $adminEx = AdminUserModel::where('uid', '<>', $param['uid'])->where('user_name', $param['username'])->exists();
        if ($adminEx) {
            throw new HomeException('账号已存在');
        }
        $adminEx = AdminUserModel::where('uid', '<>', $param['uid'])->where('nick_name', $param['nickname'])->exists();
        if ($adminEx) {
            throw new HomeException('昵称已存在');
        }
        unset($adminEx);
        if (isset($param['mobile']) && !empty($param['mobile'])) {
            $data['mobile'] = (new Crypt)->encrypt($param['mobile']);
        }

        $data['user_name']     = $param['username'];
        $data['nick_name']     = $param['nickname'];
        $data['real_name']     = $param['realname'] ?? '';
        $data['user_password'] = $param['password'];
        $data['email']         = $param['email'] ?? '';
        $data['role_id']       = $param['role_id'];
        $data['desc']          = $param['desc'] ?? '';
        $data['status']        = $param['status'] ?? 1;

        if (isset($param['password']) && !empty($param['password'])) {
            $data['hash']          = LoginService::random(8, null, '@#$%^&*()');
            $data['user_password'] = (new LoginService())->mkPw($param['username'], $param['password'], $data['hash'], 'LoginAdminHASH');
        }

        if (AdminUserModel::whereNotIn('uid', $this->adminUID)->where('uid', $param['uid'])->update($data)) {
            $result['code'] = 200;
            $result['msg']  = '编辑成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 管理员修改状态
     */
    #[RequestMapping(path: 'member/status', methods: 'post')]
    public function memberStatus(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';

        $param = $request->all();

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param,
            [
                'uid'      => ['required'],
                'username' => ['required', 'min:3', 'max:25'],
                'status'   => ['required', Rule::in([0, 1])],
            ], [
                'uid.required'      => '账号不存在',
                'username.required' => '账号必填',
                'username.min'      => '账号不少于3位',
                'username.max'      => '账号不大于25位',
                'status.required'   => '状态错误',
                'status.in'         => '状态错误',
            ]);

        $adminEx = AdminUserModel::whereNotIn('uid', $this->adminUID)->where('uid', $param['uid'])->first();
        if (empty($adminEx)) {
            throw new HomeException('当前账号不存在');
        }
        $adminEx = $adminEx->toArray();
        if ($adminEx['user_name'] != $param['username']) {
            throw new HomeException('当前需要修改名称不一致');
        }
        if (AdminUserModel::where('uid', $param['uid'])->update(['status' => $param['status']])) {
            $result['code'] = 200;
            $result['msg']  = '修改成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 前端用户列表
     */
    #[RequestMapping(path: 'member/user/lists', methods: 'post')]
    public function userLists(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '获取失败';
        $param          = $request->all();

        $data = MemberModel::query();
        if (Arr::hasArr($param, ['keyword'])) {
            $data = $data->where(function ($query) use ($param) {
                $query->orWhere('user_name', 'like', '%' . $param['keyword'] . '%')
                    ->orWhere('nick_name', 'like', '%' . $param['keyword'] . '%');
            });
        }
        if (Arr::hasArr($param, 'status') && $param['status'] >= 0) {
            $data = $data->where('status', '=', $param['status']);
        }
        $data = $data->select(['uid', 'user_name', 'nick_name', 'status', 'email', 'amount', 'tel', 'role_id', 'desc'])
            ->paginate($param['list_rows'] ?? 20);

        $items = $data->items();
        foreach ($items as $k => $v) {
            if (!empty($v['tel'])) {
                $tel              = base64_decode($v['tel']);
                $items[$k]['tel'] = (new Crypt())->decrypt($tel);
            }
        }

        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = [
            'total' => $data->total(),
            'data'  => $items
        ];
        return $this->response->json($result);
    }

    /**
     * @DOC 添加用户
     */
    #[RequestMapping(path: 'member/user/add', methods: 'post')]
    public function userAdd(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $param          = $request->all();

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param,
            [
                'username' => ['required', 'min:4', 'max:25'],
                'password' => ['required', 'min:8', 'max:25'],
                'nickname' => ['required'],
                'email'    => ['nullable'],
                'mobile'   => ['nullable'],
                'role_id'  => ['nullable'],
                'status'   => ['required', Rule::in([0, 1, 2, 3])],
            ], [
                'username.required' => '账号必填',
                'username.min'      => '账号不少于4位',
                'username.max'      => '账号不大于25位',
                'password.required' => '密码必填',
                'password.min'      => '密码不小于8位',
                'password.max'      => '密码不大于25位',
                'nickname.required' => '昵称必填',
                'status.required'   => '状态错误',
                'status.in'         => '状态错误',
            ]);

        if (MemberModel::where('user_name', '=', $param['username'])->exists()) {
            throw new HomeException('当前账号已存在');
        }
        if (MemberModel::where('nick_name', '=', $param['nickname'])->exists()) {
            throw new HomeException('当前昵称已存在');
        }


        if (isset($param['mobile']) && !empty($param['mobile'])) {
            $data['tel'] = base64_encode((new Crypt())->encrypt($param['mobile']));
        }
        $data['user_name']     = $param['username'];
        $data['nick_name']     = $param['nickname'];
        $data['user_password'] = $param['password'];
        $data['email']         = $param['email'] ?? '';
        $data['role_id']       = $param['role_id'] ?? 0;
        $data['desc']          = Arr::hasArr($param, 'desc') ? $param['desc'] : '';
        $data['status']        = $param['status'] ?? 1;
        $data['hash']          = LoginService::random(8, null, '@#$%^&*()');
        $data['user_password'] = (new LoginService())->mkPw($param['username'], $param['password'], $data['hash']);
        if (MemberModel::insert($data)) {
            $result['code'] = 200;
            $result['msg']  = '添加成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 编辑用户
     */
    #[RequestMapping(path: 'member/user/edit', methods: 'post')]
    public function userEdit(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $param          = $request->all();

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param,
            [
                'uid'      => ['required', Rule::exists('member', 'uid')],
                'username' => ['required', 'min:4', 'max:25'],
                'nickname' => ['required'],
                'email'    => ['nullable'],
                'mobile'   => ['nullable'],
                'role_id'  => ['nullable'],
                'status'   => ['required', Rule::in([0, 1, 2, 3])],
            ], [
                'uid.required'      => '账号不存在',
                'uid.exists'        => '账号不存在',
                'username.required' => '账号必填',
                'username.exists'   => '账号已存在',
                'username.min'      => '账号不少于4位',
                'username.max'      => '账号不大于25位',
                'password.required' => '密码必填',
                'password.min'      => '密码不小于8位',
                'password.max'      => '密码不大于25位',
                'nickname.required' => '昵称必填',
                'nickname.exists'   => '昵称已存在',
                'status.required'   => '状态错误',
                'status.in'         => '状态错误',
            ]);

        $data = MemberModel::where('uid', $param['uid'])->first()->toArray();
        if ($data['user_name'] != strtolower($param['username'])) {
            throw new HomeException('用户名不一致：禁止修改', 201);
        }
        $uid = $data['uid'];
        //当nick不相等的时候，需要查询是否有重复
        if ($param['nickname'] != $data['nick_name']) {
            $nickEx = MemberModel::where('uid', '<>', $uid)
                ->where('nick_name', $param['nickname'])->exists();
            if ($nickEx) {
                throw new HomeException('nick被占用、禁止修改');
            }
        }
        if (isset($param['mobile']) && !empty($param['mobile'])) {
            $data['tel'] = base64_encode((new Crypt())->encrypt($param['mobile']));
        }
        if (isset($param['password']) && !empty($param['password'])) {
            $data['hash']          = LoginService::random(8, null, '@#$%^&*()');
            $data['user_password'] = (new LoginService())->mkPw($param['username'], $param['password'], $data['hash']);
        }
        $data['nick_name'] = $param['nickname'];
        $data['email']     = $param['email'] ?? '';
        $data['desc']      = $param['desc'] ?? '';
        $data['role_id']   = $param['role_id'] ?? 0;
        $data['status']    = $param['status'] ?? 1;
        if (MemberModel::where('uid', $uid)->update($data)) {
            $result['code'] = 200;
            $result['msg']  = '修改成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 修改状态
     */
    #[RequestMapping(path: 'member/user/status', methods: 'post')]
    public function userStatus(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $param          = $request->all();

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param,
            [
                'uid'      => ['required', Rule::exists('member', 'uid')],
                'username' => ['required'],
                'status'   => ['required', Rule::in([0, 1, 2, 3])],
            ], [
                'uid.required'      => '账号不存在',
                'uid.exists'        => '账号不存在',
                'username.required' => '账号必填',
                'status.required'   => '状态错误',
                'status.in'         => '状态错误',
            ]);
        $Member = MemberModel::where('uid', $param['uid'])->first()->toArray();

        if ($Member['user_name'] != $param['username']) {
            throw new HomeException('当前需要修改名称不一致');
        }
        if (MemberModel::where('uid', $param['uid'])->update(['status' => $param['status']])) {
            $result['code'] = 200;
            $result['msg']  = '修改成功';
        }
        return $this->response->json($result);
    }


}
