<?php

namespace App\Controller\Home\Base;

use App\Common\Lib\Arr;
use App\Controller\Home\HomeBaseController;
use App\Model\PortModel;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: "base/port")]
class PortController extends HomeBaseController
{
    /**
     * @DOC 口岸数据查询
     */
    #[RequestMapping(path: 'index', methods: "post")]
    public function index(RequestInterface $request): ResponseInterface
    {
        $param   = $request->all();
        $where[] = ['status', '=', 1];
        if (Arr::hasArr($param, 'port_id', true)) {
            $where[] = ['port_id', '=', $param['port_id']];
        }

        $query    = PortModel::query()->where($where);
        $whereRaw = '(airport <> 0 or railwayport <> 0 or highwayport<> 0 or waterport<> 0)';
        $query    = $query
            ->whereRaw($whereRaw);
        $data     = $query->get();
        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => $data
        ]);
    }

}
