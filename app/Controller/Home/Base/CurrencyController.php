<?php

namespace App\Controller\Home\Base;

use App\Common\Lib\Arr;
use App\Controller\Home\HomeBaseController;
use App\Model\CountryCurrencyModel;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: "base/currency")]
class CurrencyController extends HomeBaseController
{
    /**
     * @DOC 口岸数据查询
     */
    #[RequestMapping(path: 'index', methods: 'get,post')]
    public function index(RequestInterface $request): ResponseInterface
    {
        $keyword = $request->input('keyword', '');
        $list    = CountryCurrencyModel::where(function ($query) use ($keyword) {
            if (!empty($keyword)) {
                foreach (['country_area', 'currency_name', 'currency_en', 'currency_code'] as $field) {
                    $query->orWhere($field, 'like', '%' . $keyword . '%');
                }
            }
        })
            ->select(['currency_id', 'country_area', 'currency_name', 'currency_en', 'currency_code'])
            ->paginate($param['limit'] ?? 200);
        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => [
                'total' => $list->total(),
                'data'  => $list->items()
            ]
        ]);
    }

}
