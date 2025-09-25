<?php

declare(strict_types=1);

namespace App\Controller\Admin\Auth;

use App\Common\Lib\Arr;
use App\Controller\Admin\AdminBaseController;
use App\Exception\HomeException;
use App\Model\AuthMenuModel;
use App\Model\MemberAuthMenuModel;
use App\Model\WorkAuthMenuModel;
use App\Request\LibValidation;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;

#[Controller(prefix: '/', server: 'httpAdmin')]
class MenuController extends AdminBaseController
{
    /**
     * @DOC 菜单列表
     */
    #[RequestMapping(path: 'auth/menu/lists', methods: 'post')]
    public function menuLists(RequestInterface $request)
    {
        $param = $request->all();
        $where = [];
        if (isset($param['menu_pid']) && $param['menu_pid'] >= 0) {
            $where[] = ['menu_pid', '=', $param['menu_pid']];
        } else {
            $where[] = ['menu_pid', '=', 0];
        }
        if (!empty($param['keyword'])) {
            $where[] = ['menu_name', 'like', '%' . $param['keyword'] . '%'];
        }
        if (!empty($param['status'])) {
            $where[] = ['status', '=', $param['status']];
        }
        if (!empty($param['route_path'])) {
            $where[] = ['route_path', 'like', '%' . $param['route_path'] . '%'];
        }
        if (!empty($param['route_api'])) {
            $where[] = ['route_api', 'like', '%' . $param['route_api'] . '%'];
        }

        $data = AuthMenuModel::with(
            [
                'children' => function ($query) {
                    $query->select(['menu_id', 'menu_pid']);
                }
            ]
        )->where($where)->paginate($param['limit'] ?? 20);

        $dataArr = $data->items();
        foreach ($dataArr as $key => $val) {
            $dataArr[$key]['hasChildren'] = isset($val['children'][0]);
            $parent                       = $this->parent($val['menu_id'], []);
            $dataArr[$key]['parent']      = $parent;
            unset($dataArr[$key]['children']);
        }
        $dataArr = Arr::reorder($dataArr, 'sort');

        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = [
            'total' => $data->total(),
            'data'  => $dataArr
        ];
        return $this->response->json($result);
    }

    /**
     * @DOC 上级
     */
    protected function parent($menu_id, array $result)
    {
        $data                      = AuthMenuModel::where('menu_id', '=', $menu_id)->first()->toArray();
        $result[$data['menu_pid']] = $data;
        if (isset($data['menu_pid']) && $data['menu_pid'] > 0) {
            return $this->parent($data['menu_pid'], $result);
        }
        ksort($result);
        $param['menu_id']   = array_column($result, 'menu_id');
        $param['menu_name'] = array_column($result, 'menu_name');
        return $param;
    }

    /**
     * @DOC 新增菜单
     */
    #[RequestMapping(path: 'auth/menu/add', methods: 'post')]
    public function menuAdd(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $param          = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param,
            [
                'status'    => ['required'],
                'menu_name' => ['required'],
            ], [
                'status.required'    => '状态错误',
                'menu_name.required' => '名称错误',
            ]);

        try {
            $Auth               = new AuthMenuModel();
            $where['menu_pid']  = $param['menu_pid'];
            $where['menu_name'] = $param['menu_name'];
            $role               = $Auth->where($where)->exists();
            if ($role) {
                throw new HomeException('当前名称已经存在');
            }
            if ($Auth->insert($param)) {
                $result['code'] = 200;
                $result['msg']  = '添加成功';
            }
        } catch (\Exception $e) {
            $result['code'] = 201;
            $result['msg']  = $e->getMessage();
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 编辑菜单
     */
    #[RequestMapping(path: 'auth/menu/edit', methods: 'post')]
    public function menuEdit(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $param          = $request->all();

        try {
            if (empty($param['menu_id']) || !is_numeric($param['menu_id'])) {
                throw new HomeException('menu_id 必填');
            }
            $Auth             = new AuthMenuModel();
            $where['menu_id'] = $param['menu_id'];
            $role             = $Auth->where($where)->first();
            if (empty($role)) {
                throw new HomeException('菜单不存在');
            }
            if ($Auth->where('menu_id', '=', $param['menu_id'])->update($param)) {
                $result['code'] = 200;
                $result['msg']  = '修改成功';
            }
        } catch (\Exception $e) {
            $result['code'] = 201;
            $result['msg']  = $e->getMessage();
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 菜单修改状态
     */
    #[RequestMapping(path: 'auth/menu/status', methods: 'post')]
    public function handleStatus(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $param          = $request->all();

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param,
            [
                'status'    => ['required', Rule::in([1, 0])],
                'menu_id'   => ['required'],
                'menu_name' => ['required'],
            ], [
                'menu_id.required'   => '菜单错误',
                'status.required'    => '状态错误',
                'status.in'          => '状态错误',
                'menu_name.required' => '名称错误',
            ]);

        $Auth = new AuthMenuModel();
        $Menu = $Auth->where('menu_id', $param['menu_id'])->first();
        if (empty($Menu)) {
            throw new HomeException('当前信息不存在');
        }
        $Menu = $Menu->toArray();
        if ($Menu['menu_name'] !== $param['menu_name']) {
            throw new HomeException('当前需要修改名称不一致');
        }
        if ($Auth->where('menu_id', $param['menu_id'])->update(['status' => $param['status']])) {
            $result['code'] = 200;
            $result['msg']  = '修改成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 菜单删除
     */
    #[RequestMapping(path: 'auth/menu/del', methods: 'post')]
    public function MenuDel(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '删除失败';
        $param          = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param,
            [
                'menu_id'   => ['required'],
                'menu_name' => ['required'],
            ], [
                'menu_id.required'   => '菜单不存在',
                'menu_name.required' => '菜单名称不存在',
            ]);

        $where['menu_id'] = $param['menu_id'];
        $Role             = AuthMenuModel::where($where)->first();
        if (empty($Role)) {
            throw new HomeException('菜单不存在、禁止删除');
        }
        $Role = $Role->toArray();
        if ($Role['menu_name'] != $param['menu_name']) {
            throw new HomeException('菜单不存在、禁止删除');
        }
        if ($Role['status'] != 0) {
            throw new HomeException('非禁用状态、禁止删除');
        }
        if (AuthMenuModel::where($where)->delete()) {
            $result['code'] = 200;
            $result['msg']  = '删除成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 所有菜单
     */
    #[RequestMapping(path: 'auth/menu/all', methods: 'post')]
    public function all(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '获取失败';
        $AuthMenuModel  = new AuthMenuModel();
        $dataArr        = $AuthMenuModel->select(['menu_id', 'menu_pid', 'menu_name', 'menu_type', 'active_menu'])
            ->get()->toArray();
        $dataArr        = Arr::tree($dataArr, 'menu_id', 'menu_pid', 'child');
        $data['data']   = $dataArr;
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = $data;
        return $this->response->json($result);
    }

    /**
     * @DOC 用户菜单列表
     */
    #[RequestMapping(path: 'auth/menu/member/lists', methods: 'post')]
    public function menuMemberLists(RequestInterface $request)
    {
        $param = $request->all();
        $where = [];
        if (isset($param['menu_pid']) && $param['menu_pid'] >= 0) {
            $where[] = ['menu_pid', '=', $param['menu_pid']];
        } else {
            $where[] = ['menu_pid', '=', 0];
        }
        if (!empty($param['keyword'])) {
            $where[] = ['menu_name', 'like', '%' . $param['keyword'] . '%'];
        }
        if (!empty($param['status'])) {
            $where[] = ['status', '=', $param['status']];
        }
        if (!empty($param['route_path'])) {
            $where[] = ['route_path', 'like', '%' . $param['route_path'] . '%'];
        }
        if (!empty($param['route_api'])) {
            $where[] = ['route_api', 'like', '%' . $param['route_api'] . '%'];
        }

        $data = MemberAuthMenuModel::with(
            [
                'children' => function ($query) {
                    $query->select(['menu_id', 'menu_pid']);
                }
            ]
        )->where($where)->paginate($param['limit'] ?? 20);

        $dataArr = $data->items();
        foreach ($dataArr as $key => $val) {
            $dataArr[$key]['hasChildren'] = isset($val['children'][0]);
            $parent                       = $this->memberParent($val['menu_id'], []);
            $dataArr[$key]['parent']      = $parent;
            unset($dataArr[$key]['children']);
        }
        $dataArr = Arr::reorder($dataArr, 'sort');

        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = [
            'total' => $data->total(),
            'data'  => $dataArr
        ];
        return $this->response->json($result);
    }

    /**
     * @DOC 上级
     */
    protected function memberParent($menu_id, array $result)
    {
        $data                      = MemberAuthMenuModel::where('menu_id', '=', $menu_id)->first()->toArray();
        $result[$data['menu_pid']] = $data;
        if (isset($data['menu_pid']) && $data['menu_pid'] > 0) {
            return $this->memberParent($data['menu_pid'], $result);
        }
        ksort($result);
        $param['menu_id']   = array_column($result, 'menu_id');
        $param['menu_name'] = array_column($result, 'menu_name');
        return $param;
    }

    /**
     * @DOC 判断工作台菜单
     * @Name   workParent
     * @Author wangfei
     * @date   2024/6/15 2024
     * @param $menu_id
     * @param array $result
     * @return array
     */
    protected function workParent($menu_id, array $result)
    {
        $data                      = WorkAuthMenuModel::where('menu_id', '=', $menu_id)->first()->toArray();
        $result[$data['menu_pid']] = $data;
        if (isset($data['menu_pid']) && $data['menu_pid'] > 0) {
            return $this->workParent($data['menu_pid'], $result);
        }
        ksort($result);
        $param['menu_id']   = array_column($result, 'menu_id');
        $param['menu_name'] = array_column($result, 'menu_name');
        return $param;
    }

    /**
     * @DOC 用户新增菜单
     */
    #[RequestMapping(path: 'auth/menu/member/add', methods: 'post')]
    public function menuMemberAdd(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $param          = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param,
            [
                'status'    => ['required'],
                'menu_name' => ['required'],
            ], [
                'status.required'    => '状态错误',
                'menu_name.required' => '名称错误',
            ]);

        try {
            $Auth               = new MemberAuthMenuModel();
            $where['menu_pid']  = $param['menu_pid'];
            $where['menu_name'] = $param['menu_name'];
            $role               = $Auth->where($where)->exists();
            if ($role) {
                throw new HomeException('当前名称已经存在');
            }
            if ($Auth->insert($param)) {
                $result['code'] = 200;
                $result['msg']  = '添加成功';
            }
        } catch (\Exception $e) {
            $result['code'] = 201;
            $result['msg']  = $e->getMessage();
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 用户编辑菜单
     */
    #[RequestMapping(path: 'auth/menu/member/edit', methods: 'post')]
    public function menuMemberEdit(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $param          = $request->all();

        try {
            if (empty($param['menu_id']) || !is_numeric($param['menu_id'])) {
                throw new HomeException('menu_id 必填');
            }
            $Auth             = new MemberAuthMenuModel();
            $where['menu_id'] = $param['menu_id'];
            $role             = $Auth->where($where)->first();
            if (empty($role)) {
                throw new HomeException('菜单不存在');
            }
            if ($Auth->where('menu_id', '=', $param['menu_id'])->update($param)) {
                $result['code'] = 200;
                $result['msg']  = '修改成功';
            }
        } catch (\Exception $e) {
            $result['code'] = 201;
            $result['msg']  = $e->getMessage();
        }
        return $this->response->json($result);
    }


    /**
     * @DOC 用户菜单修改状态
     */
    #[RequestMapping(path: 'auth/menu/member/status', methods: 'post')]
    public function menuMemberStatus(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $param          = $request->all();

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param,
            [
                'status'    => ['required', Rule::in([1, 0])],
                'menu_id'   => ['required'],
                'menu_name' => ['required'],
            ], [
                'menu_id.required'   => '菜单错误',
                'status.required'    => '状态错误',
                'status.in'          => '状态错误',
                'menu_name.required' => '名称错误',
            ]);

        $Auth = new MemberAuthMenuModel();
        $Menu = $Auth->where('menu_id', $param['menu_id'])->first();
        if (empty($Menu)) {
            throw new HomeException('当前信息不存在');
        }
        $Menu = $Menu->toArray();
        if ($Menu['menu_name'] !== $param['menu_name']) {
            throw new HomeException('当前需要修改名称不一致');
        }
        if ($Auth->where('menu_id', $param['menu_id'])->update(['status' => $param['status']])) {
            $result['code'] = 200;
            $result['msg']  = '修改成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 菜单删除
     */
    #[RequestMapping(path: 'auth/menu/member/del', methods: 'post')]
    public function menuMemberDel(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '删除失败';
        $param          = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param,
            [
                'menu_id'   => ['required'],
                'menu_name' => ['required'],
            ], [
                'menu_id.required'   => '菜单不存在',
                'menu_name.required' => '菜单名称不存在',
            ]);

        $where['menu_id'] = $param['menu_id'];
        $Role             = MemberAuthMenuModel::where($where)->first();
        if (empty($Role)) {
            throw new HomeException('菜单不存在、禁止删除');
        }
        $Role = $Role->toArray();
        if ($Role['menu_name'] != $param['menu_name']) {
            throw new HomeException('菜单不存在、禁止删除');
        }
        if ($Role['status'] != 0) {
            throw new HomeException('非禁用状态、禁止删除');
        }
        if (MemberAuthMenuModel::where($where)->delete()) {
            $result['code'] = 200;
            $result['msg']  = '删除成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 所有菜单
     */
    #[RequestMapping(path: 'auth/menu/member/all', methods: 'post')]
    public function menuMemberAll(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '获取失败';
        $AuthMenuModel  = new MemberAuthMenuModel();
        $dataArr        = $AuthMenuModel->select(['menu_id', 'menu_pid', 'menu_name', 'menu_type', 'active_menu'])
            ->get()->toArray();
        $dataArr        = Arr::tree($dataArr, 'menu_id', 'menu_pid', 'child');
        $data['data']   = $dataArr;
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = $data;
        return $this->response->json($result);
    }

    //TODO 工作台菜单添加

    /**
     * @DOC
     * @Name   menuWorkAdd
     * @Author wangfei
     * @date   2024/6/13 2024
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'auth/menu/work/add', methods: 'post')]
    public function menuWorkAdd(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $params         = $LibValidation->validate($params,
            [
                'menu_name'   => ['required', Rule::unique('work_auth_menu', 'menu_name')->where(function ($query) use ($params) {
                    if (!Arr::hasArr($params, 'menu_pid')) {
                        $params['menu_pid'] = 0;
                    }
                    $query->where('menu_pid', $params['menu_pid'])->where('menu_name', $params['menu_name']);
                })],
                'status'      => ['integer', 'in:0,1'],
                'menu_pid'    => ['integer'],
                'route_path'  => ['string'],
                'route_api'   => ['string'],
                'menu_type'   => ['integer', 'in:1,2,3'],
                'icon_name'   => ['string'],
                'active_menu' => ['string','nullable'],
                'sort'        => ['integer'],
            ],
            [
                'menu_name.required' => '菜单名称不能为空',
                'menu_name.unique'   => '菜单名称已存在',
                'status.integer'     => '状态必须为整数',
                'status.in'          => '状态值不正确',
                'menu_pid.integer'   => '父级菜单ID必须为整数',
                'route_path.string'  => '路由地址必须为字符串',
                'route_api.string'   => '路由API必须为字符串',
                'menu_type.integer'  => '菜单类型必须为整数',
                'menu_type.in'       => '菜单类型值不正确',
                'icon_name.string'   => '菜单图标必须为字符串',
                'active_menu.string' => '激活菜单必须为字符串',
                'sort.integer'       => '排序必须为整数',
            ]
        );

        try {
            if (Db::table('work_auth_menu')->insert($params)) {
                $result['code'] = 200;
                $result['msg']  = '添加成功';
            }
        } catch (\Exception $e) {
            $result['code'] = 201;
            $result['msg']  = $e->getMessage();
        }
        return $this->response->json($result);
    }


    /**
     * @DOC 工作台编辑菜单
     * @Name   menuWorkEdit
     * @Author wangfei
     * @date   2024/6/13 2024
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'auth/menu/work/edit', methods: 'post')]
    public function menuWorkEdit(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $params         = $LibValidation->validate($params,
            [
                'menu_id'     => ['required', 'integer', Rule::exists('work_auth_menu', 'menu_id')],
                'menu_name'   => ['required', Rule::unique('work_auth_menu', 'menu_name')->where(function ($query) use ($params) {
                    if (!Arr::hasArr($params, 'menu_pid')) {
                        $params['menu_pid'] = 0;
                    }
                    $query->where('menu_pid', $params['menu_pid'])->where('menu_name', $params['menu_name']);
                })->ignore($params['menu_id'], 'menu_id')],
                'status'      => ['integer', 'in:0,1'],
                'menu_pid'    => ['integer'],
                'route_path'  => ['string'],
                'route_api'   => ['string'],
                'menu_type'   => ['integer', 'in:1,2,3'],
                'icon_name'   => ['string'],
                'active_menu' => ['string','nullable'],
                'sort'        => ['integer'],
            ],
            [
                'menu_id.required'   => '菜单ID不能为空',
                'menu_id.integer'    => '菜单ID必须为整数',
                'menu_id.exists'     => '当前菜单不存在',
                'menu_name.required' => '菜单名称不能为空',
                'menu_name.unique'   => '菜单名称已存在',
                'status.integer'     => '状态必须为整数',
                'status.in'          => '状态值不正确',
                'menu_pid.integer'   => '父级菜单ID必须为整数',
                'route_path.string'  => '路由地址必须为字符串',
                'route_api.string'   => '路由API必须为字符串',
                'menu_type.integer'  => '菜单类型必须为整数',
                'menu_type.in'       => '菜单类型值不正确',
                'icon_name.string'   => '菜单图标必须为字符串',
                'active_menu.string' => '激活菜单必须为字符串',
                'sort.integer'       => '排序必须为整数',
            ]
        );
        try {
            if (Db::table('work_auth_menu')->where('menu_id', $params['menu_id'])->update($params)) {
                $result['code'] = 200;
                $result['msg']  = '修改成功';
            }
        } catch (\Exception $e) {
            $result['code'] = 201;
            $result['msg']  = $e->getMessage();
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 修改状态
     * @Name   menuWorkStatus
     * @Author wangfei
     * @date   2024/6/13 2024
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'auth/menu/work/status', methods: 'post')]
    public function menuWorkStatus(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($params,
            [
                'menu_id' => ['required', 'integer', Rule::exists('work_auth_menu', 'menu_id')],
                'status'  => ['required', Rule::in([1, 0])],
            ], [
                'menu_id.required' => '菜单ID不能为空',
                'menu_id.integer'  => '菜单ID必须为整数',
                'menu_id.exists'   => '当前菜单不存在',
                'status.required'  => '状态错误',
                'status.in'        => '状态错误',
            ]);

        if (Db::table('work_auth_menu')->where('menu_id', $params['menu_id'])->update(['status' => $params['status']])) {
            $result['code'] = 200;
            $result['msg']  = '修改成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 删除菜单
     * @Name   menuWorkDel
     * @Author wangfei
     * @date   2024/6/13 2024
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'auth/menu/work/del', methods: 'post')]
    public function menuWorkDel(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '删除失败';
        $params         = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($params,
            [
                'menu_id' => ['required', 'integer', Rule::exists('work_auth_menu', 'menu_id')],
                'status'  => ['required', Rule::in([0]), Rule::exists('work_auth_menu')->where(function ($query) use ($params) {
                    $query->where('menu_id', '=', $params['menu_id']);
                })],
            ], [
                'menu_id.required' => '菜单ID不能为空',
                'menu_id.integer'  => '菜单ID必须为整数',
                'menu_id.exists'   => '当前菜单不存在',
                'status.required'  => '状态错误',
                'status.in'        => '只能删除禁用状态的菜单',
                'status.exists'    => '请确认菜单状态：启用状态禁止删除菜单',
            ]);
        if (Db::table('work_auth_menu')->where('menu_id', '=', $params['menu_id'])->delete()) {
            $result['code'] = 200;
            $result['msg']  = '删除成功';
        }

        return $this->response->json($result);
    }

    /**
     * @DOC 分配工作台权限所有菜单
     * @Name   menuWorkAll
     * @Author wangfei
     * @date   2024/6/13 2024
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'auth/menu/work/all', methods: 'post')]
    public function menuWorkAll(RequestInterface $request)
    {
        $dataArr        = WorkAuthMenuModel::query()->select(['menu_id', 'menu_pid', 'menu_name', 'menu_type', 'active_menu'])
            ->get()->toArray();
        $dataArr        = Arr::tree($dataArr, 'menu_id', 'menu_pid', 'child');
        $data['data']   = $dataArr;
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = $data;
        return $this->response->json($result);
    }

    /**
     * @DOC 工作台菜单列表
     * @Name   menuWorkLists
     * @Author wangfei
     * @date   2024/6/15 2024
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'auth/menu/work/lists', methods: 'post')]
    public function menuWorkLists(RequestInterface $request)
    {
        $params = $request->all();
        $where  = [];
        if (Arr::hasArr($params, 'keyword')) {
            $where[] = ['menu_name', 'like', '%' . $params['keyword'] . '%'];
        }
        if (Arr::hasArr($params, 'menu_pid')) {
            $where[] = ['menu_pid', '=', $params['menu_pid']];
        } else {
            $where[] = ['menu_pid', '=', 0];
        }


        $data    = WorkAuthMenuModel::with(['children' => function ($query) {
                $query->select(['menu_id', 'menu_pid']);
            }]
        )->where($where)->paginate($param['limit'] ?? 100);
        $dataArr = $data->items();
        foreach ($dataArr as $key => $val) {
            $dataArr[$key]['hasChildren'] = isset($val['children'][0]);
            $parent                       = $this->workParent($val['menu_id'], []);
            $dataArr[$key]['parent']      = $parent;
            unset($dataArr[$key]['children']);
        }
        $dataArr = Arr::reorder($dataArr, 'sort');

        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = [
            'total' => $data->total(),
            'data'  => $dataArr
        ];
        return $this->response->json($result);
    }
}
