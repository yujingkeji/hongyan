<?php

namespace App\Controller\Home\Member;

use App\Common\Lib\Arr;
use App\Common\Lib\Crypt;
use App\Common\Lib\Str;
use App\Controller\Home\HomeBaseController;
use App\Exception\HomeException;
use App\Model\MemberAuthRoleModel;
use App\Model\MemberChildAuthRoleModel;
use App\Model\MemberChildModel;
use App\Model\WorkAuthMenuModel;
use App\Request\LibValidation;
use App\Service\Cache\BaseEditUpdateCacheService;
use App\Service\LoginService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;
use Psr\Http\Message\ResponseInterface;


#[Controller(prefix: "member/child")]
class ChildController extends HomeBaseController
{

    /**
     * @DOC 添加子账号
     */
    #[RequestMapping(path: "add", methods: "post")]
    public function add(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param, [
            'name'     => ['required', 'string'],
            'head_url' => ['required'],
            'role_id'  => ['required', 'integer'],
            'password' => ['required', 'min:8'],
            'status'   => ['required', Rule::in([0, 1])],
        ], [
            'head_url.required' => '请选择头像',
            'name.required'     => '名称必填',
            'name.string'       => '名称错误',
            'password.required' => '密码错误',
            'password.min'      => '密码至少8位数',
            'status.required'   => '状态错误',
            'status.in'         => '状态错误',
            'role_id.required'  => '角色必选',
            'role_id.integer'   => '角色错误',
        ]);

        $child_name = $member['user_name'] . ':' . $param['name'];

        $where['uid']        = $member['uid'];
        $where['child_name'] = $child_name;

        $role = MemberChildModel::where($where)->first();
        if (!empty($role)) {
            throw new HomeException('当前名称已经存在');
        }

        $data['uid']            = $member['uid'];
        $data['child_name']     = $child_name;
        $data['name']           = $param['name'];
        $data['status']         = $param['status'];
        $data['tel']            = (isset($param['mobile']) && !empty($param['mobile'])) ? base64_encode((new Crypt())->encrypt($param['mobile'])) : '';
        $data['desc']           = $param['desc'] ?? '';
        $data['head_url']       = $param['head_url'] ?? '';
        $data['realname']       = $param['realname'] ?? '';
        $data['child_role_id']  = $param['role_id'] ?? 0;
        $data['reg_time']       = time();
        $data['hash']           = Str::random(5, null, '@#$%^&*()');
        $data['child_password'] = (new LoginService())->mkPw($data['child_name'], $param['password'], $data['hash']);

        if (MemberChildModel::insert($data)) {
            return $this->response->json(['code' => 200, 'msg' => '注册成功', 'data' => []]);
        }
        return $this->response->json(['code' => 201, 'msg' => '注册失败', 'data' => []]);

    }

    /**
     * @DOC 子账号列表
     */
    #[RequestMapping(path: 'index', methods: "post")]
    public function index(RequestInterface $request): ResponseInterface
    {
        $param   = $request->all();
        $member  = $request->UserInfo;
        $where[] = ['uid', '=', $member['uid']];
        if (isset($param['status']) && in_array($param['status'], [0, 1])) {
            $where[] = ['status', '=', $param['status']];
        }
        if (Arr::hasArr($param, 'keyword')) {
            $where[] = ['child_name', 'like', '%' . $param['keyword'] . '%'];
        }
        if (Arr::hasArr($param, 'role_id')) {
            $where[] = ['child_role_id', '=', $param['role_id']];
        }
        $list = MemberChildModel::where($where)
            ->select(['child_name', 'name', 'amount', 'child_role_id', 'child_uid', 'desc', 'login_num', 'tel', 'status', 'head_url', 'realname'])
            ->paginate($param['limit'] ?? 20);

        $data = $list->items();
        foreach ($data as &$v) {
            if ($v['tel']) {
                $tel['tel'] = $v['tel'];
                $v['tel']   = $this->memberDecrypt($tel, false)['tel'];
            }
        }

        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => [
                'total' => $list->total(),
                'data'  => $data,
            ]
        ]);
    }


    /**
     * @DOC 编辑-子账号
     */
    #[RequestMapping(path: "edit", methods: "post")]
    public function edit(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param, [
            'child_uid' => ['required', 'integer'],
            'name'      => ['required', 'string'],
            'head_url'  => ['required'],
            'role_id'   => ['required', 'integer'],
            'password'  => ['min:8'],
            'status'    => ['required', Rule::in([0, 1])],
        ], [
            'child_uid.required' => '子账号错误',
            'child_uid.integer'  => '子账号错误',
            'head_url.required'  => '请选择头像',
            'name.required'      => '名称必填',
            'name.string'        => '名称错误',
            'password.min'       => '密码至少8位数',
            'status.required'    => '状态错误',
            'status.in'          => '状态错误',
            'role_id.required'   => '角色必选',
            'role_id.integer'    => '角色错误',
        ]);

        $where['child_uid'] = $param['child_uid'];
        $where['uid']       = $member['uid'];
        $child_name         = $member['user_name'] . ':' . $param['name'];

        $child = MemberChildModel::where($where)->first();
        if (empty($child)) {
            throw new HomeException('子账号不存在');
        }
        $data['child_name']    = $child_name;
        $data['name']          = $param['name'];
        $data['status']        = $param['status'];
        $data['tel']           = (isset($param['mobile']) && !empty($param['mobile'])) ? base64_encode((new Crypt())->encrypt($param['mobile'])) : '';
        $data['desc']          = $param['desc'];
        $data['realname']      = $param['realname'];
        $data['head_url']      = $param['head_url'];
        $data['child_role_id'] = $param['role_id'];
        if (isset($param['password']) && !empty($param['password'])) {
            $data['hash']           = Str::random(5, null, '@#$%^&*()');
            $data['child_password'] = (new LoginService())->mkPw($data['child_name'], $param['password'], $data['hash']);
        }

        if (MemberChildModel::where($where)->update($data)) {
            return $this->response->json(['code' => 200, 'msg' => '修改成功', 'data' => []]);
        }
        return $this->response->json(['code' => 201, 'msg' => '修改失败', 'data' => []]);
    }

    /**
     * @DOC 状态修改
     */
    #[RequestMapping(path: "status", methods: "post")]
    public function status(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param, [
            'child_uid' => ['required', 'integer'],
            'name'      => ['required', 'string'],
            'role_id'   => ['required', 'integer'],
            'status'    => ['required', Rule::in([0, 1])],
        ], [
            'child_uid.required' => '子账号错误',
            'child_uid.integer'  => '子账号错误',
            'name.required'      => '缺少名称',
            'name.string'        => '名称错误',
            'status.required'    => '状态错误',
            'status.in'          => '状态错误',
            'role_id.required'   => '角色必选',
            'role_id.integer'    => '角色错误',
        ]);

        $where['child_uid'] = $param['child_uid'];
        $where['uid']       = $member['uid'];
        $child_name         = $member['user_name'] . ':' . $param['name'];

        $child = MemberChildModel::where($where)->first();
        if (empty($child)) {
            throw new HomeException('子账号不存在');
        }
        if ($child['child_name'] != $child_name) {
            throw new HomeException('名称不匹配、禁止修改');
        }
        if (MemberChildModel::where($where)->update(['status' => $param['status']])) {
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
            'child_uid' => ['required', 'integer'],
        ], [
            'child_uid.required' => '子账号错误',
            'child_uid.integer'  => '子账号错误',
        ]);

        $where['child_uid'] = $param['child_uid'];
        $where['uid']       = $member['uid'];

        $child = MemberChildModel::where($where)->first();
        if (empty($child)) {
            throw new HomeException('未查询子账号信息');
        }
        if ($child['status'] == 1) {
            throw new HomeException('非禁止状态、禁止删除');
        }
        if (MemberChildModel::where($where)->delete()) {
            return $this->response->json(['code' => 200, 'msg' => '删除成功', 'data' => []]);
        }
        return $this->response->json(['code' => 201, 'msg' => '删除失败', 'data' => []]);
    }

    /**
     * @DOC 当前角色下的工作台菜单列表
     */
    #[RequestMapping(path: "word/menu", methods: "post")]
    public function wordMenu(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $where  = [];
        if (Arr::hasArr($params, 'keyword')) {
            $where[] = ['menu_name', 'like', '%' . $params['keyword'] . '%'];
        }
        if (Arr::hasArr($params, 'menu_pid')) {
            $where[] = ['menu_pid', '=', $params['menu_pid']];
        } else {
            $where[] = ['menu_pid', '=', 0];
        }

        // 查询当前父级所拥有的工作台菜单
        $parent_word = MemberAuthRoleModel::where('role_id', $member['role_id'])->value('work_menus');
        if (empty($parent_word)) {
            return $this->response->json(['code' => 201, 'msg' => '当前角色没有工作台权限', 'data' => []]);
        }

        $data    = WorkAuthMenuModel::with(['children' => function ($query) {
                $query->select(['menu_id', 'menu_pid']);
            }]
        )->where($where)
            ->whereIn('menu_id', $parent_word)
            ->select(['menu_id', 'menu_name', 'sort', 'menu_pid'])
            ->paginate($param['limit'] ?? 20);
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

    /**
     * @DOC 判断工作台菜单
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
     * @DOC 分配角色工作台权限
     */
    #[RequestMapping(path: 'word/menu/allocation', methods: 'post')]
    public function roleWorkAllocation(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $params         = $LibValidation->validate($params,
            [
                'role_id' => ['required', Rule::exists('member_child_auth_role', 'role_id')],
                'menu'    => ['array'],
            ], [
                'role_id.required' => '角色不存在',
                'role_id.exists'   => '角色不存在',
                'menu.array'       => '分配菜单错误',
            ]);
        $member = $request->UserInfo;
        if (MemberChildAuthRoleModel::where('role_id', $params['role_id'])->update(['work_menus' => json_encode($params['menu'])])) {
            $result['code'] = 200;
            $result['msg']  = '分配成功';
        }
        (new BaseEditUpdateCacheService())->MemberWorkRoleCache($member['role_id'], $params['role_id']);
        return $this->response->json($result);
    }


}
