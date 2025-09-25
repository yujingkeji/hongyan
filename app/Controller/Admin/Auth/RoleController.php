<?php

declare(strict_types=1);

namespace App\Controller\Admin\Auth;

use App\Common\Lib\Arr;
use App\Controller\Admin\AdminBaseController;
use App\Exception\HomeException;
use App\Model\AdminUserModel;
use App\Model\AgentMemberModel;
use App\Model\AuthMenuModel;
use App\Model\AuthRoleMenuModel;
use App\Model\AuthRoleModel;
use App\Model\MemberAuthMenuModel;
use App\Model\MemberAuthRoleMenuModel;
use App\Model\MemberAuthRoleModel;
use App\Request\LibValidation;
use App\Service\Cache\BaseCacheService;
use App\Service\Cache\BaseEditUpdateCacheService;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;
use Mockery\Exception;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: '/', server: 'httpAdmin')]
class RoleController extends AdminBaseController
{

    /**
     * @DOC 菜单
     */
    #[RequestMapping(path: 'auth/role/menu', methods: 'post')]
    public function roleMenu(RequestInterface $request): ResponseInterface
    {
        $baseCache = new BaseCacheService();
        if ($request->UserInfo['uid'] == 1) {
            $data = $baseCache->AdminAllRoleCache();
        } else {
            $data = $baseCache->AdminRoleCache($request->UserInfo['role_id']);
            $data = $data['menu'];
        }
        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => $data
        ]);
    }

    /**
     * @DOC 列表，已经查询接口
     */
    #[RequestMapping(path: 'auth/role/lists', methods: 'post')]
    public function roleLists(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '获取失败';
        $param          = $request->all();
        $where          = [];
        if (Arr::has($param, 'keyword')) {
            $where[] = ['name', 'like', '%' . $param['keyword'] . '%'];
        }
        if (isset($param['status']) && !empty($param['status']) && in_array($param['status'], [0, 1])) {
            $where[] = ['status', '=', $param['status']];
        }
        $data = AuthRoleModel::where($where)->paginate($param['limit'] ?? 20);
        $item = $data->items();
        foreach ($item as $key => $val) {
            $menu_id            = AuthRoleMenuModel::where('role_id', $val['role_id'])->pluck('menu_id');
            $item[$key]['menu'] = $menu_id;
        }
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = [
            'total' => $data->total(),
            'data'  => $item,
        ];

        return $this->response->json($result);
    }

    /**
     * @DOC 新增角色
     */
    #[RequestMapping(path: 'auth/role/add', methods: 'post')]
    public function roleAdd(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $param          = $LibValidation->validate($params,
            [
                'status' => ['required', Rule::in([0, 1])],
                'name'   => ['required'],
            ], [
                'status.required' => '状态错误',
                'status.in'       => '状态错误',
                'name.required'   => '角色名称必填',
            ]);

        $role = AuthRoleModel::where('name', $param['name'])->exists();
        if (!empty($role)) {
            throw new HomeException('当前名称已经存在');
        }
        $data['name']   = $param['name'];
        $data['status'] = $param['status'];
        if (AuthRoleModel::insert($data)) {
            $result['code'] = 200;
            $result['msg']  = '添加成功';
        }
        return $this->response->json($result);

    }

    /**
     * @DOC 编辑角色
     */
    #[RequestMapping(path: 'auth/role/edit', methods: 'post')]
    public function roleEdit(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $param          = $LibValidation->validate($params,
            [
                'role_id' => ['required'],
                'status'  => ['required', Rule::in([0, 1])],
                'name'    => ['required'],
            ], [
                'status.required'  => '状态错误',
                'status.in'        => '状态错误',
                'name.required'    => '角色名称必填',
                'role_id.required' => '角色不存在',
            ]);
        $where          = [
            ['role_id', '<>', $param['role_id']],
            ['name', '=', $param['name']],
        ];
        $role           = AuthRoleModel::where($where)->exists();
        if ($role) {
            throw new HomeException('当前名称已经存在');
        }
        $data['name']   = $param['name'];
        $data['status'] = $param['status'];
        if (AuthRoleModel::where('role_id', $param['role_id'])->update($data)) {
            $result['code'] = 200;
            $result['msg']  = '更新成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 删除角色
     */
    #[RequestMapping(path: 'auth/role/del', methods: 'post')]
    public function roleDel(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $param          = $LibValidation->validate($params,
            [
                'role_id' => ['required'],
                'status'  => ['required', Rule::in([0, 1])],
                'name'    => ['required'],
            ], [
                'status.required'  => '状态错误',
                'status.in'        => '状态错误',
                'name.required'    => '角色名称必填',
                'role_id.required' => '角色不存在',
            ]);
        $where          = [
            ['role_id', '=', $param['role_id']],
            ['name', '=', $param['name']],
        ];
        $roleExists     = AuthRoleModel::where($where)->exists();
        if (!$roleExists) {
            throw new HomeException('角色不存在');
        }
        // 获取角色信息
        $roleData = AuthRoleModel::where('role_id', $param['role_id'])->first()->toArray();
        if ($roleData == 0 || $roleData['status'] != $param['status']) {
            throw new HomeException('非禁止状态、禁止删除');
        }
        // 角色下的用户
        $roleUserData = AdminUserModel::where('role_id', $param['role_id'])->exists();
        if ($roleUserData) {
            throw new HomeException('当前角色下存在用户、禁止删除');
        }
        // 角色下的菜单
        $roleMenuData = AuthRoleMenuModel::where('role_id', $param['role_id'])->exists();
        if ($roleMenuData) {
            throw new HomeException('当前角色下存在权限、禁止删除');
        }

        if (AuthRoleModel::where('role_id', $param['role_id'])->delete()) {
            $result['code'] = 200;
            $result['msg']  = '删除成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 编辑角色
     */
    #[RequestMapping(path: 'auth/role/status', methods: 'post')]
    public function roleStatus(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $param          = $LibValidation->validate($params,
            [
                'role_id' => ['required'],
                'status'  => ['required', Rule::in([0, 1])],
            ], [
                'status.required'  => '状态错误',
                'status.in'        => '状态错误',
                'role_id.required' => '角色不存在',
            ]);
        $where          = [
            ['role_id', '=', $param['role_id']],
        ];
        $role           = AuthRoleModel::where($where)->exists();
        if (!$role) {
            throw new HomeException('当前角色不存在');
        }
        $data['status'] = $param['status'];
        if (AuthRoleModel::where('role_id', $param['role_id'])->update($data)) {
            $result['code'] = 200;
            $result['msg']  = '更新成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 分配角色
     */
    #[RequestMapping(path: 'auth/role/allocation', methods: 'post')]
    public function roleAllocation(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $params         = $LibValidation->validate($params,
            [
                'role_id' => ['required', Rule::exists('auth_role', 'role_id')],
                'name'    => ['required'],
                'menu'    => ['array'],
            ], [
                'name.required'    => '状态错误',
                'role_id.required' => '角色必填',
                'role_id.exists'   => '角色不存在',
                'menu.array'       => '分配菜单错误',
            ]);
        $menu           = AuthMenuModel::whereIn('menu_id', $params['menu'])->get()->toArray();
        $role_menu      = [];
        foreach ($menu as $key => $val) {
            $role_menu[$key]['role_id'] = $params['role_id'];
            $role_menu[$key]['menu_id'] = $val['menu_id'];
            $role_menu[$key]['name']    = $val['menu_name'];
        }
        // 事务处理
        Db::beginTransaction();
        try {
            Db::table('auth_role_menu')->where('role_id', $params['role_id'])->delete();
            Db::table('auth_role_menu')->insert($role_menu);
            // 提交事务
            $result['code'] = 200;
            $result['msg']  = '修改成功';
            Db::commit();
        } catch (Exception $e) {
            // 回滚事务
            $result['msg'] = '修改失败';
            Db::rollback();
        }
        $BaseEditUpdateCacheService = make(BaseEditUpdateCacheService::class);
        $BaseEditUpdateCacheService->AdminRoleCache($params['role_id']);
        $BaseEditUpdateCacheService->AdminLoginRoleCache($params['role_id']);
        return $this->response->json($result);
    }

    /**
     * @DOC 用户角色列表接口
     */
    #[RequestMapping(path: 'auth/role/member/lists', methods: 'post')]
    public function roleMemberList(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '获取失败';
        $param          = $request->all();
        $where          = [];
        if (isset($param['keyword']) && !empty($param['keyword'])) {
            $where[] = ['name', 'like', '%' . $param['keyword'] . '%'];
        }
        if (isset($param['status']) && !empty($param['status']) && in_array($param['status'], [0, 1])) {
            $where[] = ['status', '=', $param['status']];
        }
        $data  = MemberAuthRoleModel::where($where)->paginate($param['limit'] ?? 20);
        $items = $data->items();
        foreach ($items as $key => $val) {
            $menu_id             = MemberAuthRoleMenuModel::where('role_id', $val['role_id'])->pluck('menu_id');
            $items[$key]['menu'] = $menu_id;
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
     * @DOC 新增用户角色
     */
    #[RequestMapping(path: 'auth/role/member/add', methods: 'post')]
    public function roleMemberAdd(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $param          = $LibValidation->validate($params,
            [
                'status' => ['required', Rule::in([0, 1])],
                'name'   => ['required'],
            ], [
                'status.required' => '状态错误',
                'status.in'       => '状态错误',
                'name.required'   => '角色名称必填',
            ]);

        $role = MemberAuthRoleModel::where('name', $param['name'])->exists();
        if (!empty($role)) {
            throw new HomeException('当前名称已经存在');
        }
        $data['name']   = $param['name'];
        $data['status'] = $param['status'];
        $data['info']   = $param['info'] ?? '';
        if (MemberAuthRoleModel::insert($data)) {
            $result['code'] = 200;
            $result['msg']  = '添加成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 编辑用户角色
     */
    #[RequestMapping(path: 'auth/role/member/edit', methods: 'post')]
    public function roleMemberEdit(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $param          = $LibValidation->validate($params,
            [
                'role_id' => ['required'],
                'status'  => ['required', Rule::in([0, 1])],
                'name'    => ['required'],
            ], [
                'status.required'  => '状态错误',
                'status.in'        => '状态错误',
                'name.required'    => '角色名称必填',
                'role_id.required' => '角色不存在',
            ]);
        $where          = [
            ['role_id', '<>', $param['role_id']],
            ['name', '=', $param['name']],
        ];
        $role           = MemberAuthRoleModel::where($where)->exists();
        if ($role) {
            throw new HomeException('当前名称已经存在');
        }
        $data['name']   = $param['name'];
        $data['status'] = $param['status'];
        $data['info']   = $params['info'] ?? '';
        if (MemberAuthRoleModel::where('role_id', $param['role_id'])->update($data)) {
            $result['code'] = 200;
            $result['msg']  = '更新成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 删除用户角色
     */
    #[RequestMapping(path: 'auth/role/member/del', methods: 'post')]
    public function roleMemberDel(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $param          = $LibValidation->validate($params,
            [
                'role_id' => ['required'],
                'status'  => ['required', Rule::in([0, 1])],
                'name'    => ['required'],
            ], [
                'status.required'  => '状态错误',
                'status.in'        => '状态错误',
                'name.required'    => '角色名称必填',
                'role_id.required' => '角色不存在',
            ]);
        $where          = [
            ['role_id', '=', $param['role_id']],
            ['name', '=', $param['name']],
        ];
        $roleExists     = MemberAuthRoleModel::where($where)->exists();
        if (!$roleExists) {
            throw new HomeException('角色不存在');
        }
        // 获取角色信息
        $roleData = MemberAuthRoleModel::where('role_id', $param['role_id'])->first()->toArray();
        if ($roleData == 0 || $roleData['status'] != $param['status']) {
            throw new HomeException('非禁止状态、禁止删除');
        }
        // 角色下的用户
        $roleUserData = AgentMemberModel::where('role_id', $param['role_id'])->exists();
        if ($roleUserData) {
            throw new HomeException('当前角色下存在用户、禁止删除');
        }
        // 角色下的菜单
        $roleMenuData = MemberAuthRoleMenuModel::where('role_id', $param['role_id'])->exists();
        if ($roleMenuData) {
            throw new HomeException('当前角色下存在权限、禁止删除');
        }

        if (MemberAuthRoleModel::where('role_id', $param['role_id'])->delete()) {
            $result['code'] = 200;
            $result['msg']  = '删除成功';
        }
        return $this->response->json($result);
    }


    /**
     * @DOC 编辑角色
     */
    #[RequestMapping(path: 'auth/role/member/status', methods: 'post')]
    public function roleMemberStatus(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $param          = $LibValidation->validate($params,
            [
                'role_id' => ['required'],
                'status'  => ['required', Rule::in([0, 1])],
            ], [
                'status.required'  => '状态错误',
                'status.in'        => '状态错误',
                'role_id.required' => '角色不存在',
            ]);
        $where          = [
            ['role_id', '=', $param['role_id']],
        ];
        $role           = MemberAuthRoleModel::where($where)->exists();
        if (!$role) {
            throw new HomeException('当前角色不存在');
        }
        $data['status'] = $param['status'];
        if (MemberAuthRoleModel::where('role_id', $param['role_id'])->update($data)) {
            $result['code'] = 200;
            $result['msg']  = '更新成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 分配用户角色
     */
    #[RequestMapping(path: 'auth/role/member/allocation', methods: 'post')]
    public function roleMemberAllocation(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $param          = $LibValidation->validate($params,
            [
                'role_id' => ['required'],
                'name'    => ['required'],
                'menu'    => ['array'],
            ], [
                'name.required'    => '状态错误',
                'role_id.required' => '角色不存在',
                'menu.array'       => '分配菜单错误',
            ]);
        $menu           = MemberAuthMenuModel::whereIn('menu_id', $param['menu'])->get()->toArray();
        $role_menu      = [];
        foreach ($menu as $key => $val) {
            $role_menu[$key]['role_id'] = $param['role_id'];
            $role_menu[$key]['menu_id'] = $val['menu_id'];
            $role_menu[$key]['name']    = $val['menu_name'];
        }
        // 事务处理
        Db::beginTransaction();
        try {
            Db::table('member_auth_role_menu')->where('role_id', $param['role_id'])->delete();
            Db::table('member_auth_role_menu')->insert($role_menu);
            // 提交事务
            $result['code'] = 200;
            $result['msg']  = '修改成功';
            Db::commit();
        } catch (Exception $e) {
            // 回滚事务
            $result['msg'] = '修改失败';
            Db::rollback();
        }
        $BaseEditUpdateCacheService = make(BaseEditUpdateCacheService::class);
        $BaseEditUpdateCacheService->MemberRoleMenuCache($param['role_id']);
        $BaseEditUpdateCacheService->MemberRoleCache($param['role_id']);
        return $this->response->json($result);
    }

    /**
     * @DOC 分配角色工作台权限
     * @Name   roleWorkAllocation
     * @Author wangfei
     * @date   2024/6/13 2024
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: 'auth/role/work/allocation', methods: 'post')]
    public function roleWorkAllocation(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $params         = $LibValidation->validate($params,
            [
                'role_id' => ['required', Rule::exists('member_auth_role', 'role_id')],
                'name'    => ['required'],
                'menu'    => ['array'],
            ], [
                'role_id.required' => '角色不存在',
                'role_id.exists'   => '角色不存在',
                'name.required'    => '状态错误',
                'menu.array'       => '分配菜单错误',
            ]);
        if (Db::table('member_auth_role')->where('role_id', $params['role_id'])->update(['work_menus' => json_encode($params['menu'])])) {
            $result['code'] = 200;
            $result['msg']  = '分配成功';
        }
        $BaseEditUpdateCacheService = make(BaseEditUpdateCacheService::class);
        $BaseEditUpdateCacheService->MemberWorkRoleCache($params['role_id'], 0);
        return $this->response->json($result);
    }
}
