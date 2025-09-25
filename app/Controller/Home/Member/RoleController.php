<?php

namespace App\Controller\Home\Member;

use App\Common\Lib\Arr;
use App\Controller\Home\HomeBaseController;
use App\Exception\HomeException;
use App\Model\MemberAuthRoleMenuModel;
use App\Model\MemberChildAuthRoleModel;
use App\Request\LibValidation;
use App\Service\Cache\BaseEditUpdateCacheService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;
use Psr\Http\Message\ResponseInterface;


#[Controller(prefix: "member/role")]
class RoleController extends HomeBaseController
{
    /**
     * @DOC 列表接口、查询接口
     */
    #[RequestMapping(path: "index", methods: "post")]
    public function index(RequestInterface $request): ResponseInterface
    {
        $param   = $request->all();
        $member  = $request->UserInfo;
        $where[] = ['uid', '=', $member['uid']];
        if (Arr::hasArr($param, 'keyword')) {
            $where[] = ['name', 'like', '%' . $param['keyword'] . '%'];
        }
        if (isset($param['status']) && !empty($param['status']) && in_array($param['status'], [0, 1])) {
            $where[] = ['status', '=', $param['status']];
        }

        $list = MemberChildAuthRoleModel::where($where)
            ->paginate($param['limit'] ?? 20);

        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => [
                'total' => $list->total(),
                'data'  => $list->items(),
            ]
        ]);
    }


    /**
     * @DOC 新增
     */
    #[RequestMapping(path: "add", methods: "post")]
    public function add(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param, [
            'name'   => ['required'],
            'status' => ['required', Rule::in([0, 1])]
        ], [
            'name.required'   => '缺少角色名称',
            'status.required' => '缺少状态',
            'status.in'       => '状态错误',
        ]);

        $where['uid']  = $member['uid'];
        $where['name'] = $param['name'];
        $role          = MemberChildAuthRoleModel::where($where)->first();
        if ($role) {
            throw new HomeException('当前名称已经存在');
        }
        $data = [
            'uid'    => $member['uid'],
            'name'   => $param['name'],
            'status' => $param['status'],
            'info'   => $param['info'] ?? '',
        ];

        if (MemberChildAuthRoleModel::insert($data)) {
            return $this->response->json(['code' => 200, 'msg' => '添加成功', 'data' => []]);
        }
        return $this->response->json(['code' => 201, 'msg' => '添加失败', 'data' => []]);
    }

    /**
     * @DOC 编辑
     */
    #[RequestMapping(path: "edit", methods: "post")]
    public function edit(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param, [
            'role_id' => ['required', 'integer'],
            'name'    => ['required'],
            'status'  => ['required', Rule::in([0, 1])]
        ], [
            'role_id.required' => '角色错误',
            'role_id.integer'  => '角色错误',
            'name.required'    => '缺少角色名称',
            'status.required'  => '缺少状态',
            'status.in'        => '状态错误',
        ]);

        $where['uid']  = $member['uid'];
        $where['name'] = $param['name'];
        $role          = MemberChildAuthRoleModel::where($where)->whereNotIn('role_id', [$param['role_id']])->first();
        if ($role) {
            throw new HomeException('当前名称已经存在');
        }
        $data['name']   = $param['name'];
        $data['status'] = $param['status'];
        $data['info']   = $param['info'];
        unset($where['name']);
        $where['role_id'] = $param['role_id'];

        if (MemberChildAuthRoleModel::where($where)->update($data)) {
            return $this->response->json(['code' => 200, 'msg' => '更新成功', 'data' => []]);
        }
        return $this->response->json(['code' => 201, 'msg' => '更新失败', 'data' => []]);

    }

    /**
     * @DOC 修改状态
     */
    #[RequestMapping(path: "status", methods: "post")]
    public function status(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param, [
            'role_id' => ['required', 'integer'],
            'name'    => ['required'],
            'status'  => ['required', Rule::in([0, 1])]
        ], [
            'role_id.required' => '角色错误',
            'role_id.integer'  => '角色错误',
            'name.required'    => '缺少角色名称',
            'status.required'  => '缺少状态',
            'status.in'        => '状态错误',
        ]);
        $where['uid']     = $request->UserInfo['uid'];
        $where['role_id'] = $param['role_id'];
        $Role             = MemberChildAuthRoleModel::where($where)->first();
        if (!$Role) {
            throw new HomeException('未查询到角色');
        }
        $Role = $Role->toArray();
        if ($Role['name'] != $param['name']) {
            throw new HomeException('角色名不正确');
        }
        if (MemberChildAuthRoleModel::where($where)->update(['status' => $param['status']])) {
            return $this->response->json(['code' => 200, 'msg' => '修改成功', 'data' => []]);
        }
        return $this->response->json(['code' => 201, 'msg' => '修改失败', 'data' => []]);
    }

    /**
     * @DOC 删除
     */
    #[RequestMapping(path: "del", methods: "post")]
    public function del(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param, [
            'role_id' => ['required', 'integer'],
            'name'    => ['required'],
            'status'  => ['required', Rule::in([0, 1])]
        ], [
            'role_id.required' => '角色错误',
            'role_id.integer'  => '角色错误',
            'name.required'    => '缺少角色名称',
            'status.required'  => '缺少状态',
            'status.in'        => '状态错误',
        ]);
        $where['uid']     = $member['uid'];
        $where['role_id'] = $param['role_id'];
        $Role             = MemberChildAuthRoleModel::where($where)
            ->with(['child' => function ($query) use ($member, $param) {
                $query->where('uid', $member['uid'])
                    ->where('child_role_id', $param['role_id'])
                    ->select(['child_uid', 'uid']);
            }])->first();
        if (empty($Role)) {
            throw new HomeException('角色不存在、禁止删除');
        }
        $Role = $Role->toArray();
        if ($Role['name'] != $param['name']) {
            throw new HomeException('角色名称不正确、禁止删除');
        }
        if ($Role['status'] != 0) {
            throw new HomeException('非禁止状态、禁止删除');
        }
        if (count($Role['child']) >= 1) {
            throw new HomeException('当前角色下存在用户、禁止删除');
        }
        if (!empty($Role['menu']) >= 1) {
            throw new HomeException('当前角色下存在权限、禁止删除');
        }
        if (MemberChildAuthRoleModel::where($where)->delete()) {
            return $this->response->json(['code' => 200, 'msg' => '删除成功', 'data' => []]);
        }
        return $this->response->json(['code' => 201, 'msg' => '删除失败', 'data' => []]);
    }

    /**
     * @DOC 分配菜单
     */
    #[RequestMapping(path: "allocation", methods: "post")]
    public function allocation(RequestInterface $request): ResponseInterface
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $param          = $request->all();
        $member         = $request->UserInfo;
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param, [
            'role_id' => ['required', 'integer'],
            'name'    => ['required'],
            'menu'    => ['required', 'array']
        ], [
            'role_id.required' => '角色错误',
            'role_id.integer'  => '角色错误',
            'name.required'    => '缺少角色名称',
            'menu.required'    => '缺少权限',
            'menu.array'       => '权限错误',
        ]);

        $where['uid']     = $member['uid'];
        $where['role_id'] = $param['role_id'];

        $Role = MemberChildAuthRoleModel::where($where)->first();
        if (!$Role) {
            throw new HomeException('未查询到角色信息');
        }
        $Role = $Role->toArray();
        if ($Role['name'] != $param['name']) {
            throw new HomeException('角色名不正确、禁止设置权限');
        }

        $menu = MemberAuthRoleMenuModel::where('role_id', $member['role_id'])->get();
        if (!$menu) {
            throw new HomeException('未查询到权限菜单');
        }
        $menu      = $menu->toArray();
        $menuIdArr = array_column($menu, 'menu_id');
        $role_menu = [];

        foreach ($param['menu'] as $key => $val) {
            if (in_array($val, $menuIdArr)) {
                array_push($role_menu, $val);
            }
        }
        unset($menuIdArr, $menu);
        if (!empty($role_menu)) {
            if (MemberChildAuthRoleModel::where($where)->update(['role_menu' => implode(',', $role_menu)])) {
                $result['code'] = 200;
                $result['msg']  = '设置成功';
                $result['data'] = [];
            } else {
                $result['code'] = 201;
                $result['msg']  = '没有需要更新的内容';
                $result['data'] = [];
            }
        }
        $BaseCacheService = (new BaseEditUpdateCacheService());
        $BaseCacheService->MemberChildRoleMenuCache($param['role_id']);

        return $this->response->json($result);

    }

}
