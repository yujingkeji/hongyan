<?php

namespace App\Controller\Admin\Base;

use App\Common\Lib\Arr;
use App\Controller\Admin\AdminBaseController;
use App\Model\TariffModel;
use App\Model\TariffWordModel;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

#[Controller(prefix: '/', server: 'httpAdmin')]
class TariffController extends AdminBaseController
{

    /**
     * @DOC 行邮税号-列表
     */
    #[RequestMapping(path: 'base/tariff/lists', methods: 'post')]
    public function lists(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '获取失败';
        $data           = TariffModel::get()->toArray();
        $data           = Arr::tree($data, 'id', 'parent_id', 'children', 0);
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = $data;
        return $this->response->json($result);
    }

    /**
     * @DOC 税则词库列表
     */
    #[RequestMapping(path: 'base/tariff/word/lists', methods: 'post')]
    public function wordLists(RequestInterface $request)
    {
        $param = $request->all();

        $where = [];
        if (isset($param['keyword']) && !empty($param['keyword'])) {
            $where[] = ['word', 'like', '%' . $param['keyword'] . '%'];
        }

        $data           = TariffWordModel::with(
            ['tax' => function ($query) {
                $query->select(['id', 'name', 'tax_code', 'dutiable_value', 'tax_rate', 'tax_rate_number']);
            }]
        )->where($where)->paginate($param['limit'] ?? 20);
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = [
            'total' => $data->total(),
            'data'  => $data->items()
        ];
        return $this->response->json($result);

    }


}
