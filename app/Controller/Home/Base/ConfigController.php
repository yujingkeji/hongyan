<?php

namespace App\Controller\Home\Base;

use App\Common\Lib\Arr;
use App\Controller\Home\HomeBaseController;
use App\Exception\HomeException;
use App\JsonRpc\BaseServiceInterface;
use App\Model\BrandModel;
use App\Model\CategoryModel;
use App\Model\ConfigModel;
use App\Model\CountryCodeModel;
use App\Model\GoodsCategoryItemModel;
use App\Model\GoodsCategoryModel;
use App\Model\LogisticsTemplateModel;
use App\Model\PortsModel;
use App\Request\LibValidation;
use App\Service\AuthWayService;
use App\Service\Cache\BaseCacheService;
use App\Service\ConfigService;
use App\Service\GoodsService;
use App\Service\RechargeService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: "base/config")]
class ConfigController extends HomeBaseController
{
    #[Inject]
    protected BaseServiceInterface $baseService;

    /**
     * @DOC 接口类型
     */
    #[RequestMapping(path: 'interface', methods: 'post')]
    public function interface(RequestInterface $request): ResponseInterface
    {
        $cfg_id = $request->input('cfg_id', 0);
        $data   = (new BaseCacheService())->CategoryCache(pid: 1680, cfg_id: $cfg_id);
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => ['data' => $data]]);
    }

    /**
     * @DOC 平台类型
     */
    #[RequestMapping(path: 'platform', methods: 'post')]
    public function platform(RequestInterface $request): ResponseInterface
    {
        $cfg_id = $request->input('cfg_id', 0);
        $data   = (new BaseCacheService())->CategoryCache(pid: 1625, cfg_id: $cfg_id);
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
    }

    /**
     * @DOC 价格模板类型
     */
    #[RequestMapping(path: 'price/temp/type', methods: 'post')]
    public function priceTempType(RequestInterface $request): ResponseInterface
    {
        $field  = ['cfg_id', 'title', 'code', 'sort'];
        $cfg_id = $request->input('cfg_id', 0);
        $data   = (new BaseCacheService())->CategoryCache(pid: 1775, cfg_id: $cfg_id, field: $field);
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
    }

    /**
     * @DOC 监管条件
     */
    #[RequestMapping(path: 'condition', methods: 'post')]
    public function condition(RequestInterface $request): ResponseInterface
    {
        $field  = ['cfg_id', 'title', 'code', 'sort'];
        $cfg_id = $request->input('cfg_id', 0);
        $data   = (new BaseCacheService())->CategoryCache(pid: 1720, cfg_id: $cfg_id, field: $field);
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
    }

    /**
     * @DOC 认证要素
     */
    #[RequestMapping(path: 'element', methods: 'post')]
    public function element(RequestInterface $request): ResponseInterface
    {
        $field  = ['cfg_id', 'title', 'code', 'sort'];
        $cfg_id = $request->input('cfg_id', 0);
        $data   = (new BaseCacheService())->CategoryCache(pid: 1700, cfg_id: $cfg_id, field: $field);
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
    }

    /**
     * @DOC 常用单位
     */
    #[RequestMapping(path: 'unit', methods: 'post')]
    public function unit(RequestInterface $request): ResponseInterface
    {
        $field  = ['cfg_id', 'title', 'code', 'sort'];
        $cfg_id = $request->input('cfg_id', 0);
        $data   = (new BaseCacheService())->CategoryCache(pid: 89, cfg_id: $cfg_id, field: $field);
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
    }

    /**
     * @DOC 检验检疫类别
     */
    #[RequestMapping(path: 'ciq', methods: 'post')]
    public function ciq(RequestInterface $request): ResponseInterface
    {
        $field  = ['cfg_id', 'title', 'code', 'sort'];
        $cfg_id = $request->input('cfg_id', 0);
        $data   = (new BaseCacheService())->CategoryCache(pid: 1760, cfg_id: $cfg_id, field: $field);
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
    }

    /**
     * @DOC 进出口方式
     */
    #[RequestMapping(path: 'export', methods: 'post')]
    public function export(RequestInterface $request): ResponseInterface
    {
        $cfg_id = $request->input('cfg_id', 0);
        $data   = (new BaseCacheService())->CategoryCache(pid: 1660, cfg_id: $cfg_id);
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
    }

    /**
     * @DOC 报关方式
     */
    #[RequestMapping(path: 'declare', methods: 'get,post')]
    public function declare(RequestInterface $request): ResponseInterface
    {
        $cfg_id = $request->input('cfg_id', 0);
        $data   = (new BaseCacheService())->CategoryCache(pid: 1615, cfg_id: $cfg_id);
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
    }

    /** 废弃
     * @DOC 表 goods_category 的内容。
     */
    #[RequestMapping(path: 'goods/cate', methods: 'get,post')]
    public function goodsCate(RequestInterface $request): ResponseInterface
    {
        $param = $request->all();
        $where = [];
        if (Arr::hasArr($param, 'is_suit')) {
            $where[] = ['is_suit', '=', $param['is_suit']];
        }
        $list = GoodsCategoryModel::where($where)->get();
        $data = [];
        if (!empty($list)) {
            $list = $list->toArray();
            $data = Arr::tree($list, 'cate_id', 'pid');
        }
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
    }

    /** 启用新分类接口与base统一
     * @DOC 商品类别
     */
    #[RequestMapping(path: 'record/category', methods: 'post')]
    public function recordCategory(RequestInterface $request)
    {
        $param = $request->all();
        if (empty($param['country_id'])) {
            $param['country_id'] = 1;
        }
        $data = $this->baseService->recordGoodsCategoryByCountry($param['country_id'] ?? 1);
        return $this->response->json($data);
    }


    /**
     * @DOC  新的备案分类信息
     * @Name   recordCategoryInfo
     * @Author wangfei
     * @date   2024/2/24 2024
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: 'record/category/info', methods: 'post')]
    public function recordCategoryInfo(RequestInterface $request)
    {
        $param         = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($param,
            [
                'id' => ['required', 'array']
            ],
            [

                'id.required' => '缺少父级ID',
                'id.array'    => '格式错误，必须为数组',
            ]
        );
        $result        = $this->baseService->recordGoodsCategoryInfo($param['id']);
        return $this->response->json($result);
    }

    /**
     * @DOC 表 goods_category 的内容。
     */
    #[RequestMapping(path: 'cate/goods', methods: 'get,post')]
    public function cateGoods(RequestInterface $request): ResponseInterface
    {
        $param = $request->all();
        $where = [];
        if (Arr::hasArr($param, 'cate1')) {
            $where[] = ['cate1', '=', $param['cate1']];
        }
        if (Arr::hasArr($param, 'cate2')) {
            $where[] = ['cate2', '=', $param['cate2']];
        }
        if (Arr::hasArr($param, 'keyword')) {
            $where[] = ['name', 'like', '%' . $param['keyword'] . '%'];
        }
        if (Arr::hasArr($param, 'is_suit')) {
            $cate_idArr = GoodsCategoryModel::where('is_suit', $param['is_suit'])->pluck('cate_id');
            $where[]    = ['cate1', 'in', $cate_idArr];
        }

        $list = GoodsCategoryItemModel::with(['cate1', 'cate2'])
            ->where($where)->paginate($param['limit'] ?? 20);
        return $this->response->json([
            'code' => 200,
            'msg'  => '查询成功',
            'data' => [
                'total' => $list->total(),
                'data'  => $list->items()
            ]]);
    }

    /**
     * @DOC 物流模板
     */
    #[RequestMapping(path: 'logistics/template', methods: 'post')]
    public function logisticsTemplate(RequestInterface $request): ResponseInterface
    {
        $param = $request->all();
        $data  = LogisticsTemplateModel::with(['platform'])->where('status', '=', 1);
        if (Arr::hasArr($param, 'platform_id')) {
            $data = $data->where('platform_id', $param['platform_id']);
        }
        if (Arr::hasArr($param, 'keyword')) {
            $keyword = $param['keyword'];
            $data    = $data->where(function ($query) use ($keyword) {
                if (!empty($keyword)) {
                    foreach (['template_name', 'template_url'] as $field) {
                        $query->orWhere($field, 'like', '%' . $keyword . '%');
                    }
                }
            });
        }
        $data = $data->paginate($param['limit'] ?? 50);
        return $this->response->json([
            'code' => 200,
            'msg'  => '查询成功',
            'data' => [
                'total' => $data->total(),
                'data'  => $data->items()
            ]]);
    }

    /**
     * @DOC 港口机场
     */
    #[RequestMapping(path: 'ports', methods: 'post')]
    public function ports(RequestInterface $request): ResponseInterface
    {
        $param = $request->all();
        $data  = PortsModel::with(['country', 'port'])->where('status', '=', 1);
        if (Arr::hasArr($param, 'country_id')) {
            $data = $data->where('country_id', $param['country_id']);
        }
        if (Arr::hasArr($param, 'port_air')) {
            $data = $data->where('port_air', $param['port_air']);
        }
        if (Arr::hasArr($param, 'port_id')) {
            $data = $data->where('port_id', $param['port_id']);
        }
        if (Arr::hasArr($param, 'keyword')) {
            $keyword = $param['keyword'];
            $data    = $data->where(function ($query) use ($keyword) {
                if (!empty($keyword)) {
                    foreach (['ports_zh', 'ports_en', 'ports_source'] as $field) {
                        $query->orWhere($field, 'like', '%' . $keyword . '%');
                    }
                }
            });
        }
        $data = $data->select(['*', 'port_air as port_air_str'])->paginate($param['limit'] ?? 50);
        return $this->response->json([
            'code' => 200,
            'msg'  => '查询成功',
            'data' => [
                'total' => $data->total(),
                'data'  => $data->items()
            ]]);
    }

    /**
     * @DOC 仓库类型
     */
    #[RequestMapping(path: 'ware/type', methods: 'post')]
    public function wareType(RequestInterface $request): ResponseInterface
    {
        $cfg_id = $request->input('cfg_id', 0);
        $data   = (new BaseCacheService())->CategoryCache(pid: 1785, cfg_id: $cfg_id);
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
    }

    /**
     * @DOC 证件类型
     */
    #[RequestMapping(path: 'cert/type', methods: 'post')]
    public function certType(RequestInterface $request): ResponseInterface
    {
        $param   = $request->all();
        $where[] = ['status', '=', 1];
        $where[] = ['pid', '=', 345];
        $data    = CategoryModel::where($where);
        if (Arr::hasArr($param, 'cfg_id')) {
            $data = CategoryModel::where('cfg_id', $param['cfg_id']);
        }
        if (Arr::hasArr($param, 'remove_share') && $param['remove_share'] == 1) {
            $data = $data->where('country_id', $param['country_id']);
        }
        if (Arr::hasArr($param, 'remove_share') && $param['remove_share'] == 0) {
            if (Arr::hasArr($param, 'country_id')) {
                $data = $data->whereIn('country_id', [0, $param['country_id']]);
            }
        }
        $data = $data->get()->toArray();
        $data = Arr::reorder($data, 'sort', 'SORT_ASC');

        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
    }

    /**
     * @DOC 订单来源
     */
    #[RequestMapping(path: 'order/from', methods: 'post')]
    public function orderFrom(RequestInterface $request): ResponseInterface
    {
        $where[]   = ['pid', '=', 1789];
        $where[]   = ['status', '=', 1];
        $FromDb    = CategoryModel::where($where)->get()->toArray();
        $countryDb = [];
        if (!empty($FromDb)) {
            $country_id_Arr = array_column($FromDb, 'country_id');
            $country_id_Arr = array_unique($country_id_Arr);
            $countryDb      = CountryCodeModel::whereIn('country_id', $country_id_Arr)->get()->toArray();
            $countryDb      = array_column($countryDb, null, 'country_id');
            foreach ($FromDb as $key => $val) {
                if ($val['country_id'] > 0) {
                    $countryDb[$val['country_id']]['child'][] = $val;
                }
            }
            $countryDb = Arr::reorder($countryDb, 'country_id', 'SORT_ASC');
        }

        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $countryDb]);
    }

    /**
     * @DOC 送件方式，也是交付方式
     */
    #[RequestMapping(path: 'deliver', methods: 'post')]
    public function deliver(RequestInterface $request): ResponseInterface
    {
        $where[] = ['pid', '=', 14];
        $data    = ConfigModel::where($where)->get()->toArray();
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
    }

    /**
     * @DOC 订单类型、以及订单、包裹相关状态
     */
    #[RequestMapping(path: 'order/state', methods: 'post')]
    public function orderState(RequestInterface $request): ResponseInterface
    {
        $where[] = ['status', '=', 1];
        $data    = ConfigModel::where($where)->whereIn('model', [18, 25, 40])->get()->toArray();
        $data    = Arr::tree($data, 'cfg_id', 'pid');
        $data    = array_column($data, null, 'code');
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
    }

    /**
     * @DOC 地址智能解析
     */
    #[RequestMapping(path: 'analysis', methods: 'post')]
    public function analysis(RequestInterface $request): ResponseInterface
    {
        $q      = $request->input('q', '');
        $result = (new ConfigService())->analysis($q);
        return $this->response->json($result);
    }

    /**
     * @DOC 等级图标
     */
    #[RequestMapping(path: 'grade', methods: 'post')]
    public function grade(RequestInterface $request): ResponseInterface
    {
        $data = [];
        for ($i = 1; $i <= 14; $i++) {
            $grade['number'] = $i;
            $grade['src']    = 'https://open-hjd.oss-cn-hangzhou.aliyuncs.com/yfd/user/icon/rank/rank' . $i . '.png';
            $grade['back']   = 'https://open-hjd.oss-cn-hangzhou.aliyuncs.com/yfd/user/icon/rank/rank_bg_' . $i . '.png';
            $data[]          = $grade;
        }
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
    }


    /**
     * @DOC 头像图标
     */
    #[RequestMapping(path: 'head', methods: 'post')]
    public function head(RequestInterface $request): ResponseInterface
    {
        $data = [];
        for ($i = 1; $i <= 36; $i++) {
            $grade['number'] = $i;
            $grade['src']    = 'https://open-hjd.oss-cn-hangzhou.aliyuncs.com/yfd/user/icon/head/head_' . $i . '.png';
            $data[]          = $grade;
        }
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
    }


    /**
     * @DOC 品牌查询
     */
    #[RequestMapping(path: 'brand', methods: 'post')]
    public function brand(RequestInterface $request): ResponseInterface
    {
        $param = $request->all();

        $data = BrandModel::query();

        if (Arr::hasArr($param, 'keyword')) {
            $keyword = $param['keyword'];
            $data    = $data->where(function ($query) use ($keyword) {
                if (!empty($keyword)) {
                    foreach (['source_name', 'brand_name', 'brand_en_name'] as $field) {
                        $query->orWhere($field, 'like', $keyword . '%');
                    }
                }
            });
        }
        $data = $data->paginate($param['limit'] ?? 20);
        return $this->response->json([
            'code' => 200,
            'msg'  => '查询成功',
            'data' => [
                'total' => $data->total(),
                'data'  => $data->items()
            ]]);
    }

    /**
     * @DOC 根据分词获取分类信息
     */
    #[RequestMapping(path: 'category/participle', methods: 'get,post')]
    public function GoodsCategoryParticiple(RequestInterface $request)
    {
        $text      = $request->input('text', '');
        $parent_id = $request->input('parent_id', 0);
        if (empty($text)) {
            throw new HomeException('请输入搜索词');
        }

        $baseServiceInterface = \Hyperf\Support\make(BaseServiceInterface::class);
        $result               = $baseServiceInterface->recordGoodsCategoryParticiple($text, 'ik_smart', (int)$parent_id);
        return $this->response->json($result);
    }


    /**
     * @DOC 根据分词获取分类信息
     */
    #[RequestMapping(path: 'word/participle', methods: 'get,post')]
    public function recordGoodsWordSearch(RequestInterface $request)
    {
        $params        = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'text'      => ['required_without:parent_id', 'string',],
                'parent_id' => ['required_without:text', 'integer'],
            ], [
                'text.string'                => '关键词必须为字符串',
                'text.required_without'      => '关键词|父级ID必须存在一项',
                'parent_id.required_without' => '关键词|父级ID必须存在一项',
                'parent_id.integer'          => '父级ID必须为整数',
            ]);

        $baseServiceInterface = \Hyperf\Support\make(BaseServiceInterface::class);
        $result               = $baseServiceInterface->elasticRecordGoodsWordSearch($param);
        return $this->response->json($result);
    }


    /**
     * @DOC 根据渠道获取认证参数
     */
    #[RequestMapping(path: 'auth/element', methods: 'post')]
    public function getAuthElement(RequestInterface $request)
    {
        $params  = $request->all();
        $service = \Hyperf\Support\make(AuthWayService::class);
        $result  = $service->getAuthElementData($params);
        return $this->response->json($result);
    }

    /**
     * @DOC 生成二维码
     */
    #[RequestMapping(path: 'qrcode', methods: 'post')]
    public function qrcode(RequestInterface $request)
    {
        $string = $request->input('string', '');
        if (empty($string)) {
            throw new HomeException('请输入生成二维码的内容');
        }
        $png = \Hyperf\Support\make(RechargeService::class)->getUrlPng($string);
        return $this->response->json(['code' => 200, 'msg' => '生成成功', 'data' => ['png' => $png]]);
    }

    /**
     * @DOC  分类ID/行邮税号 返回信息
     */
    #[RequestMapping(path: 'category/cfg', methods: 'post')]
    public function getCategoryCfg(RequestInterface $request)
    {
        $param   = $request->all();
        $servers = \Hyperf\Support\make(ConfigService::class);
        $data    = $servers->getCategoryCfg($param);
        return $this->response->json($data);
    }

    /**
     * @DOC 助手下载组件
     */
    #[RequestMapping(path: 'helper/download', methods: 'post')]
    public function helperDownload()
    {
        $data = make(BaseCacheService::class)->ConfigPidCache(30000);
        $data = array_values($data);
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
    }


}
