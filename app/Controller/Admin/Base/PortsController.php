<?php

namespace App\Controller\Admin\Base;

use App\Common\Lib\Arr;
use App\Controller\Admin\AdminBaseController;
use App\Model\PortsModel;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

#[Controller(prefix: '/', server: 'httpAdmin')]
class PortsController extends AdminBaseController
{

    /**
     * @DOC 港口机场列表
     */
    #[RequestMapping(path: 'base/ports/lists', methods: 'post')]
    public function lists(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '获取失败';
        $param          = $request->all();
        $data           = PortsModel::query();
        if (Arr::hasArr($param, 'country_id')) {
            $data = $data->where('country_id', '=', $param['country_id']);
        }
        if (Arr::hasArr($param, 'keyword')) {
            $data = $data->where(function ($query) use ($param) {
                $query->orWhere('ports_zh', 'like', '%' . $param['keyword'] . '%')
                    ->orWhere('ports_source', 'like', '%' . $param['keyword'] . '%')
                    ->orWhere('ports_en', 'like', '%' . $param['keyword'] . '%');
            });
        }
        if (Arr::hasArr($param, 'port_air')) {
            $data = $data->where('port_air', '=', $param['port_air']);
        }
        $data           = $data->paginate($param['limit'] ?? 20);
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = [
            'total' => $data->total(),
            'data'  => $data->items(),
        ];
        return $this->response->json($result);
    }

}
