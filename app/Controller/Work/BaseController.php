<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Controller\Work;

use App\Exception\HomeException;
use App\JsonRpc\BaseServiceInterface;
use App\Model\MemberLineModel;
use App\Model\MemberPortModel;
use App\Model\PrintTemplateModel;
use App\Model\WarehouseModel;
use App\Request\LibValidation;
use App\Service\AddressService;
use App\Service\BrandService;
use App\Service\Cache\BaseCacheService;
use App\Service\ConfigService;
use App\Service\GoodsService;
use App\Service\MembersService;
use App\Service\PrintService;
use App\Service\WarehouseService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: "/", server: 'httpWork')]
class BaseController extends WorkBaseController
{
    #[Inject]
    protected BaseCacheService $baseCacheService;

    #[Inject]
    protected BaseServiceInterface $baseService;


    /**
     * @DOC   : 工作台商品类目检测接口
     * @Name  : categoryAnalyse
     * @Author: wangfei
     * @date  : 2025-04 13:52
     * @param RequestInterface $request
     * @return ResponseInterface
     *
     */
    #[RequestMapping(path: 'base/category/analyse', methods: 'post')]
    public function categoryAnalyse(RequestInterface $request)
    {
        $params = $request->all();
        $result = \Hyperf\Support\make(BaseServiceInterface::class)->categoryAnalyse($params);
        return $this->response->json($result);
    }


    /**
     * @DOC  商品分类
     */
    #[RequestMapping(path: 'base/category', methods: 'post,get')]
    public function baseCategory(RequestInterface $request)
    {
        $param = $request->all();
        if (empty($param['country_id'])) {
            $param['country_id'] = 1;
        }
        $data = $this->baseService->recordGoodsCategoryByCountry($param['country_id'] ?? 1);
        return $this->response->json($data);
    }


    /**
     * @DOC 商品分类下的商品
     */
    #[RequestMapping(path: 'base/category/goods/lists', methods: 'post')]
    public function categoryGoodsLists(RequestInterface $request)
    {
        $param   = $request->all();
        $member  = $this->request->UserInfo;
        $where[] = ['parent_agent_uid', '=', $member['parent_agent_uid']];
        if (!empty($param['member_id'])) {
            $where[]            = ['member_uid', '=', $param['member_id']];
            $base['member_uid'] = $param['member_id'];
        }
        $base['parent_agent_uid'] = $member['parent_agent_uid'];
        $useWhere                 = ['base' => $base, 'where' => $where];
        $data                     = \Hyperf\Support\make(GoodsService::class)->GoodsLists($param, $useWhere);
        return $this->response->json($data);
    }

    /**
     * @DOC 官方备案库分类下的商品
     */
    #[RequestMapping(path: 'base/category/record/lists', methods: 'post')]
    public function categoryRecordLists(RequestInterface $request)
    {
        $param = $request->all();
        $data  = \Hyperf\Support\make(GoodsService::class)->categoryRecordLists($param);
        return $this->response->json($data);
    }

    /**
     * @DOC 当前加盟商下的客户列表
     */
    #[RequestMapping(path: 'base/member/lists', methods: 'post')]
    public function memberLists(RequestInterface $request)
    {
        $param                    = $request->all();
        $param['parent_join_uid'] = $this->request->UserInfo['uid'];
        $data                     = \Hyperf\Support\make(MembersService::class)->memberLists($param);
        return $this->response->json($data);
    }

    /**
     * @DOC 查询客户下的地址
     */
    #[RequestMapping(path: 'base/address/lists', methods: 'post')]
    public function memberAddressLists(RequestInterface $request)
    {
        $param = $request->all();
        if (empty($param['member_id'])) {
            throw new HomeException('请选择客户');
        }
        $userInfo['uid'] = $param['member_id'];
        $data            = \Hyperf\Support\make(AddressService::class)->getAddress($param, $userInfo);
        return $this->response->json($data);
    }

    /**
     * @DOC 地址详情
     */
    #[RequestMapping(path: 'base/address/detail', methods: 'post')]
    public function memberAddressDetail(RequestInterface $request): ResponseInterface
    {
        $param = $request->all();
        if (empty($param['member_id'])) {
            throw new HomeException('请选择客户');
        }
        $userInfo['uid'] = $param['member_id'];
        $data            = \Hyperf\Support\make(AddressService::class)->getAddressDetail($param, $userInfo);
        return $this->response->json($data);
    }

    /**
     * @DOC 行政区域
     */
    #[RequestMapping(path: 'base/area', methods: 'post')]
    public function areaLists(RequestInterface $request)
    {
        $param            = $request->all();
        $parent_agent_uid = $request->UserInfo['parent_agent_uid'];
        $result           = \Hyperf\Support\make(ConfigService::class)->area($param, $parent_agent_uid);
        return $this->response->json($result);
    }

    /**
     * @DOC 国家区号列表
     */
    #[RequestMapping(path: 'base/country/code', methods: 'get,post')]
    public function countryCode(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $result = \Hyperf\Support\make(ConfigService::class)->countryCode($param);
        return $this->response->json($result);
    }

    /**
     * @DOC 地址智能解析
     */
    #[RequestMapping(path: 'base/analysis', methods: 'post')]
    public function analysis(RequestInterface $request): ResponseInterface
    {
        $q      = $request->input('q', '');
        $result = \Hyperf\Support\make(ConfigService::class)->analysis($q);
        return $this->response->json($result);
    }

    /**
     * @DOC 品牌列表
     */
    #[RequestMapping(path: 'base/brand/lists', methods: 'post')]
    public function brandLists(RequestInterface $request)
    {
        $param  = $request->all();
        $result = \Hyperf\Support\make(BrandService::class)->getBrand($param);
        return $this->response->json($result);
    }

    /**
     * @DOC 保存地址
     */
    #[RequestMapping(path: 'base/address/add', methods: 'post')]
    public function saveAddress(RequestInterface $request)
    {
        $param    = $request->all();
        $userInfo = $this->request->UserInfo;
        if (empty($param['member_uid'])) {
            throw new HomeException('请选择客户');
        }
        $userInfo['uid'] = $param['member_uid'];
        $result          = \Hyperf\Support\make(AddressService::class)->addAddress($param, $userInfo);
        return $this->response->json($result);
    }

    /**
     * @DOC 图片上传（开放）
     */
    #[RequestMapping(path: 'base/upload/open', methods: 'post')]
    public function openUpload(RequestInterface $request)
    {
        $param    = $request->all();
        $userInfo = $this->request->UserInfo;
        if (empty($param['member_uid'])) {
            throw new HomeException('请选择客户');
        }
        $userInfo['uid'] = $param['member_uid'];
        $result          = \Hyperf\Support\make(ConfigService::class)->upload($param, $userInfo);
        return $this->response->json($result);
    }

    /**
     * @DOC 获取用户详情
     */
    #[RequestMapping(path: 'base/user/info', methods: 'post')]
    public function getUserInfo(RequestInterface $request)
    {
        $param    = $request->all();
        $userInfo = $this->request->UserInfo;

        $result = \Hyperf\Support\make(MembersService::class)->getUserInfo($param, $userInfo);
        return $this->response->json($result);
    }

    /**
     * @DOC 汇率查询
     */
    #[RequestMapping(path: 'base/rate', methods: 'get,post')]
    public function rate(RequestInterface $request)
    {
        $member = $this->request->UserInfo;
        $result = \Hyperf\Support\make(MembersService::class)->rate($member);
        return $this->response->json($result);
    }

    /**
     * @DOC 打单模板列表
     */
    #[RequestMapping(path: 'base/print/lists', methods: 'post')]
    public function printLists(RequestInterface $request)
    {
        $param    = $request->all();
        $userInfo = $this->request->UserInfo;
        $result   = \Hyperf\Support\make(PrintService::class)->getTemplateList($param, $userInfo);
        return $this->response->json($result);
    }

    /**
     * @DOC 获取打单之前的数据处理
     */
    #[RequestMapping(path: 'base/print/info', methods: 'post')]
    public function parcelExport(RequestInterface $request)
    {
        $param                  = $request->all();
        $param['template_type'] = PrintTemplateModel::TEMPLATE_Parcel_Waybill;
        $data                   = \Hyperf\Support\make(PrintService::class)->getPrintData($param);
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
    }

    /**
     * @DOC 打印标签
     */
    #[RequestMapping(path: 'base/print/label/info', methods: 'post')]
    public function printLabel(RequestInterface $request)
    {
        $param                  = $request->all();
        $param['template_type'] = PrintTemplateModel::TEMPLATE_TYPE_LABEL;
        $data                   = \Hyperf\Support\make(PrintService::class)->handleprintLabelInfo($param);
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
    }

    /**
     * @DOC 打印拣货单
     */
    #[RequestMapping(path: 'base/print/pack/info', methods: 'post')]
    public function printPackLabel(RequestInterface $request)
    {
        $param  = $request->all();
        $result = \Hyperf\Support\make(PrintService::class)->handleprintPackInfo($param);
        return $this->response->json($result);
    }

    /**
     * @DOC 打印成功回调
     */
    #[RequestMapping(path: 'base/print/success', methods: 'post')]
    public function parcelSuccess(RequestInterface $request)
    {
        $param    = $request->all();
        $userInfo = $this->request->UserInfo;
        $result   = \Hyperf\Support\make(PrintService::class)->printSuccess($param, $userInfo);
        return $this->response->json($result);
    }


    /**
     * @DOC 打印模板详情
     */
    #[RequestMapping(path: 'base/print/detail', methods: 'post')]
    public function detail(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PrintService::class)->getPintTemplateDetail($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 根据订单号查询渠道运单模版打印模版详情
     */
    #[RequestMapping(path: 'base/print/order/detail', methods: 'post')]
    public function orderPrintDetail(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $result = \Hyperf\Support\make(PrintService::class)->orderPrintDetail($param);
        return $this->response->json($result);
    }

    /**
     * @DOC 搜索用户信息
     */
    #[RequestMapping(path: 'base/search/member', methods: 'post')]
    public function searchMember(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(MembersService::class)->searchMember($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 集货仓库列表
     */
    #[RequestMapping(path: 'base/warehouse', methods: 'post')]
    public function warehouse(RequestInterface $request)
    {
        $params = $request->all();
        $where  = [
            ['member_uid', '=', $request->UserInfo['parent_agent_uid']],
            ['status', '=', 1],
        ];
        if (!empty($params['ware_id'])) {
            $where[] = ['ware_id', '=', $params['ware_id']];
        }
        if (!empty($params['country_id'])) {
            $where[] = ['country_id', '=', $params['country_id']];
        }
        $data = WarehouseModel::where($where)
            ->select(['ware_id', 'ware_code', 'ware_name', 'contacts', 'phone_before', 'contact_phone', 'contact_address', 'country_id', 'confine'])
            ->get()->toArray();
        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => $data
        ]);
    }


    /**
     * @DOC 根据分词获取分类信息
     */
    #[RequestMapping(path: 'base/category/participle', methods: 'get,post')]
    public function GoodsCategoryParticiple(RequestInterface $request)
    {
        $text      = $request->input('text', '');
        $parent_id = $request->input('parent_id', 0);
        if (empty($text)) {
            throw new HomeException('请输入搜索词');
        }

        $baseServiceInterface = \Hyperf\Support\make(BaseServiceInterface::class);
        $result               = $baseServiceInterface->recordGoodsCategoryParticiple($text, 'ik_smart', $parent_id);
        return $this->response->json($result);
    }

    /**
     * @DOC 根据分词获取分类信息
     */
    #[RequestMapping(path: 'base/word/participle', methods: 'get,post')]
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
     * @DOC 工作台收货入库费用信息
     */
    #[RequestMapping(path: 'base/store/cost', methods: 'post')]
    public function storeCost()
    {
        $baseCacheService = \Hyperf\Support\make(BaseCacheService::class);
        $result['code']   = 200;
        $result['msg']    = '查找成功';
        $result['data']   = array_values($baseCacheService->ConfigPidCache(12200));
        return $this->response->json($result);

    }

    /**
     * @DOC 查询仓库库位
     */
    #[RequestMapping(path: 'base/ware/location', methods: 'post')]
    public function warehouseLocation(RequestInterface $request)
    {
        $params  = $request->all();
        $member  = $request->UserInfo;
        $service = \Hyperf\Support\make(WarehouseService::class);
        $result  = $service->locationList($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 查询仓库类型
     */
    #[RequestMapping(path: 'base/ware/type', methods: 'post')]
    public function warehouseType(RequestInterface $request)
    {
        $params  = $request->all();
        $member  = $request->UserInfo;
        $service = \Hyperf\Support\make(WarehouseService::class);
        $result  = $service->locationTypeList($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 查询仓库库区
     */
    #[RequestMapping(path: 'base/ware/area', methods: 'post')]
    public function warehouseArea(RequestInterface $request)
    {
        $params  = $request->all();
        $member  = $request->UserInfo;
        $service = \Hyperf\Support\make(WarehouseService::class);
        $result  = $service->areaList($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 查询代理线路
     */
    #[RequestMapping(path: 'base/line', methods: 'post')]
    public function memberLineLists(RequestInterface $request): ResponseInterface
    {
        $member  = $request->UserInfo;
        $where[] = ['uid', '=', $member['parent_agent_uid']];

        $data = MemberLineModel::where($where)
            ->with(['line' => function ($query) {
                $query->with([
                    'send'   => function ($send) {
                        $send->select(['country_id', 'country_name', 'country_code', 'zip_code']);
                    },
                    'target' => function ($target) {
                        $target->select(['country_id', 'country_name', 'country_code', 'zip_code']);
                    },
                ])->select(['line_id', 'line_name', 'send_country_id', 'send_country', 'target_country_id', 'target_country', 'status']);
            }])
            ->select(['line_id', 'member_line_id', 'node_id', 'status', 'uid'])
            ->get()->toarray();

        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => $data
        ]);
    }

    /**
     * @DOC 代理下所存在的口岸
     */
    #[RequestMapping(path: 'base/member/post', methods: 'post')]
    public function memberPort(RequestInterface $request): ResponseInterface
    {
        $member  = $request->UserInfo;
        $where[] = ['member_uid', '=', $member['parent_agent_uid']];
        $data    = MemberPortModel::where($where)->select(['port_id', 'port_name'])->get()->toArray();
        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => $data
        ]);
    }

    /**
     * @DOC 助手下载组件
     */
    #[RequestMapping(path: 'base/helper/download', methods: 'post')]
    public function helperDownload()
    {
        $data = make(BaseCacheService::class)->ConfigPidCache(30000);
        $data = array_values($data);
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
    }

}
