<?php

namespace App\Controller\Home\Base;

use App\Common\Lib\Arr;
use App\Controller\Home\AbstractController;
use App\JsonRpc\BaseServiceInterface;
use App\JsonRpc\Service\BaseService;
use App\Model\CountryCodeModel;
use App\Request\LibValidation;
use App\Service\Cache\BaseCacheService;
use App\Service\ConfigService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: "base/country")]
class CountryController extends AbstractController
{
    /**
     * @DOC 国家数据
     */
    #[RequestMapping(path: 'index', methods: 'get,post')]
    public function index(RequestInterface $request): ResponseInterface
    {
        $param   = $request->all();
        $where[] = ['status', '=', 1];
        if (Arr::hasArr($param, 'country_id')) $where[] = ['country_id', 'in', $param['country_id']];
        $data = CountryCodeModel::with(
            [
                'area' => function ($query) {
                    $query->where('parent_id', 0)->select(['id', 'country_id']);
                }
            ]
        )->where($where)->paginate($param['limit'] ?? 20);
        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => [
                'total' => $data->total(),
                'data'  => $data->items(),
            ]]);
    }

    /**
     * @DOC 行政区域
     */
    #[RequestMapping(path: 'area', methods: 'get,post')]
    public function area(RequestInterface $request): ResponseInterface
    {
        $param            = $request->all();
        $parent_agent_uid = $request->UserInfo['parent_agent_uid'];
        $result           = (new ConfigService())->area($param, $parent_agent_uid);
        return $this->response->json($result);
    }

    /**
     * @DOC 行政区域
     */
    #[RequestMapping(path: 'area/all', methods: 'get,post')]
    public function areaAll(RequestInterface $request): ResponseInterface
    {
        $param          = $request->all();
        $country_id     = 1;
        $where[]        = ['id', '<>', 1];
        $baseCache      = \Hyperf\Support\make(BaseCacheService::class);
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = $baseCache->CountryAreaAllCache($country_id, $where);
        return $this->response->json($result);
    }

    /**
     * @DOC   : 区域标签
     * @Name  : areaLabel
     * @Author: wangfei
     * @date  : 2025-04 21:05
     * @param RequestInterface $request
     * @return ResponseInterface
     *
     */
    #[RequestMapping(path: 'area/label', methods: 'get,post')]
    public function areaLabel(RequestInterface $request): ResponseInterface
    {
        $params = $request->all();
        $params = make(LibValidation::class)->validate($params, [
            'country_id' => ['required', 'integer']
        ]);
        $result = make(BaseServiceInterface::class)->countryAreaLabel($params['country_id']);
        return $this->response->json($result);
    }

    /**
     * @DOC   :通过标签获取行政区域
     * @Name  : areaByLabel
     * @Author: wangfei
     * @date  : 2025-04 21:17
     * @param RequestInterface $request
     * @return ResponseInterface
     *
     */
    #[RequestMapping(path: 'area/by/label', methods: 'get,post')]
    public function areaByLabel(RequestInterface $request): ResponseInterface
    {
        $params = $request->all();
        $result = make(BaseServiceInterface::class)->countryAreaByLabel($params);
        return $this->response->json($result);
    }

}
