<?php

declare(strict_types=1);

namespace App\Controller\Admin\Auth;

use App\Common\Lib\Arr;
use App\Controller\Admin\AdminBaseController;
use App\Model\AuthWayModel;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: '/', server: 'httpAdmin')]
class WayController extends AdminBaseController
{

    /**
     * @DOC 认证方式列表查询
     */
    #[RequestMapping(path: 'auth/way/lists', methods: 'post')]
    public function lists(RequestInterface $request): ResponseInterface
    {
        $param = $request->all();
        $where = [];
        if (Arr::hasArr($param, 'keyword')) $where[] = ['way_name', 'like', '%' . $param['keyword'] . '%'];
        if (Arr::hasArr($param, 'country_id')) $where[] = ['country_id', '=', $param['country_id']];

        $data = AuthWayModel::with(
            [
                'country', 'platform', 'interface', 'interface.interface'
            ]
        )->where($where)->paginate($param['limit'] ?? 20);

        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => [
                'total' => $data->total(),
                'data'  => $data->items(),
            ]
        ]);

    }

}
