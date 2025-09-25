<?php

namespace App\Controller\Home\Member;

use App\Common\Lib\Arr;
use App\Controller\Home\HomeBaseController;
use App\Model\AuthMenuModel;
use App\Model\MemberAuthMenuModel;
use App\Service\Cache\BaseCacheService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: "member/menu")]
class MenuController extends HomeBaseController
{

    #[Inject]
    protected BaseCacheService $baseCacheService;


    /**
     * @DOC 权限列表接口
     */
    #[RequestMapping(path: 'index', methods: 'post')]
    public function index(RequestInterface $request): ResponseInterface
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

        $list          = MemberAuthMenuModel::where($where)
            ->whereNotIn('menu_id', ['537'])
            ->with(['children'])
            ->paginate($param['limit'] ?? 20);
        $data['total'] = $list->total();
        $dataArr       = $list->items();
        foreach ($dataArr as $key => $val) {
            $dataArr[$key]['hasChildren'] = (isset($val['children']) && count($val['children']) > 0) ? true : false;
            $parent                       = $this->parent($val['menu_id'], []);
            $dataArr[$key]['parent']      = $parent;
            unset($dataArr[$key]['children']);
        }
        $data['data'] = Arr::reorder($dataArr, 'sort');

        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => $data,
        ]);

    }

    /**
     * @DOC 权限列表接口
     */
    #[RequestMapping(path: 'out/index', methods: 'post')]
    public function out(RequestInterface $request): ResponseInterface
    {
        $UserInfo = $request->UserInfo;
        if (isset($UserInfo['child_role_id']) && $UserInfo['child_role_id'] > 0) {
            $data = $this->baseCacheService->MemberChildRoleMenuCache($UserInfo['child_role_id']);
        } else {
            $data = $this->baseCacheService->MemberRoleMenuCache($UserInfo['role_id'], true);
        }

        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = $data;
        return $this->response->json($result);
    }

    /**
     * @DOC 获取上一级
     */
    protected function parent($menu_id, $result): array
    {
        $data = AuthMenuModel::where('menu_id', '=', $menu_id)->first();
        array_unshift($result, $data);
        if (isset($data['menu_pid']) && $data['menu_pid'] > 0) {
            return $this->parent($data['menu_pid'], $result);
        }
        $param['menu_id']   = array_column($result, 'menu_id');
        $param['menu_name'] = array_column($result, 'menu_name');
        return $param;
    }


}
