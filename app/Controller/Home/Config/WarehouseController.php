<?php

namespace App\Controller\Home\Config;

use App\Common\Lib\Arr;
use App\Controller\Home\AbstractController;
use App\Exception\HomeException;
use App\Model\CountryAreaModel;
use App\Model\WarehouseModel;
use App\Request\LibValidation;
use App\Service\Cache\BaseCacheService;
use App\Service\Cache\BaseEditUpdateCacheService;
use App\Service\LoginService;
use App\Service\WarehouseService;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: "config/warehouse")]
class WarehouseController extends AbstractController
{
    /**
     * @DOC 仓库列表
     */
    #[RequestMapping(path: 'index', methods: 'post')]
    public function index(RequestInterface $request): ResponseInterface
    {
        $param   = $request->all();
        $where[] = ['member_uid', '=', $request->UserInfo['parent_agent_uid']];
        if (Arr::hasArr($param, 'ware_name')) {
            $where[] = ['ware_name', 'like', '%' . $param['ware_name'] . '%'];
        }
        if (Arr::hasArr($param, 'ware_code')) {
            $where[] = ['ware_code', 'like', '%' . $param['ware_code'] . '%'];
        }
        if (Arr::hasArr($param, 'country_id')) {
            $country_id = CountryAreaModel::where('parent_id', 0)
                ->where('country_id', $param['country_id'])->value('id');
            $where[]    = ['country_id', '=', $country_id];
        }
        if (Arr::hasArr($param, 'status', true)) {
            $where[] = ['status', '=', $param['status']];
        }
        if (Arr::hasArr($param, 'ware_id')) {
            $where[] = ['ware_id', '=', $param['ware_id']];
        }
        $data = WarehouseModel::where($where)
            ->with(['type'])
            ->orderBy('add_time', 'desc')
            ->paginate($param['limit'] ?? 20);
        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => [
                'total' => $data->total(),
                'data'  => $data->items(),
            ]]);
    }


    /**
     * @DOC 仓库新增
     */
    #[RequestMapping(path: 'add', methods: 'post')]
    public function add(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        if ($member['role_id'] != 1) throw new HomeException('当前级别不足：平台代理才能创建仓库');
        # 验证参数
        $this->checkValidate($param, $member);
        # 获取参数
        $ware  = $this->getData($param, $member, 'add');
        $where = [
            ['ware_name', '=', $param['ware_name']],
            ['member_uid', '=', $member['uid']],
        ];

        $data = WarehouseModel::where($where)
            ->first();
        if (!empty($data)) {
            throw new HomeException('错误：当前仓库已经存在');
        }
        WarehouseModel::insert($ware);
        return $this->response->json(['code' => 200, 'msg' => '添加成功', 'data' => []]);
    }

    /**
     * @DOC 生成仓库编号
     */
    protected function wareNo(): string
    {
        $wareNo   = date("ymd") . LoginService::random(3, 1);
        $wareNoDb = WarehouseModel::where('ware_no', $wareNo)->exists();
        if (empty($wareNoDb)) {
            return $wareNo;
        }
        return $this->wareNo();
    }

    /**
     * @DOC 仓库修改
     */
    #[RequestMapping(path: 'edit', methods: 'post')]
    public function edit(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        if (empty($param['ware_id'])) {
            throw new HomeException('未检查到仓库信息');
        }
        if ($member['role_id'] != 1) throw new HomeException('当前级别不足：平台代理才能创建仓库');
        # 参数校验
        $this->checkValidate($param, $member, 'edit');
        $where_name = [
            ['ware_name', '=', $param['ware_name']],
            ['member_uid', '=', $member['uid']]
        ];
        $data       = WarehouseModel::where($where_name)
            ->whereNotIn('ware_id', [$param['ware_id']])->first();
        if (!empty($data)) {
            throw new HomeException('错误：当前仓库名称已存在');
        }
        $where            = [
            ['ware_id', '=', $param['ware_id']],
            ['member_uid', '=', $member['uid']],
        ];
        $WarehouseModelDb = WarehouseModel::where($where)->exists();
        if (!$WarehouseModelDb) {
            throw new HomeException('错误：当前仓库不存在');
        }

        $ware = $this->getData($param, $member, 'edit');
        WarehouseModel::where($where)->update($ware);
        (\Hyperf\Support\make(BaseEditUpdateCacheService::class))->WareHouseCache($member['parent_agent_uid']);
        return $this->response->json(['code' => 200, 'msg' => '编辑成功', 'data' => []]);
    }

    /**
     * @DOC 验证参数
     */
    protected function checkValidate($param, $member, $type = 'add')
    {
        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $rules         = [
            'ware_code'     => ['required', 'min:3', 'max:8', Rule::unique('warehouse')->where(function ($query) use ($param, $type) {
                $query = $query->where('ware_code', '=', $param['ware_code']);
                if ($type == 'edit') {
                    $query = $query->whereNotIn('ware_id', [$param['ware_id']]);
                }
                return $query;
            })],
            'ware_name'     => ['required', 'min:4'],
            'country_id'    => ['required', 'integer'],
            'contacts'      => ['required', 'min:2'],
//            'line_id'       => ['required', 'integer'],
            'phone_before'  => ['required', 'min:2'],
            'contact_phone' => ['required', 'min:8'],
            //            'contact_address' => ['required', 'min:10'],
            //            'area'            => ['array'],
            'confine'       => ['nullable', 'array'],
        ];
        $message       = [
            'ware_code.required'       => '仓库代码必填',
            'ware_code.min'            => '仓库代码长度最少3位',
            'ware_code.unique'         => '仓库代码已存在',
            'ware_code.max'            => '仓库代码长度最大8位',
            'ware_name.required'       => '仓库名称必填',
            'ware_name.min'            => '仓库名称长度最少4位',
            'country_id.required'      => '国家地区必选',
            'country_id.integer'       => '国家地区错误',
            'line_id.required'         => '线路必选',
            'line_id.integer'          => '线路错误',
            'phone_before.required'    => '电话前缀必填',
            'phone_before.min'         => '电话前缀长度最少2位',
            'contact_phone.required'   => '电话必填',
            'contact_phone.min'        => '电话长度最少8位',
            'contact_address.required' => '地址必填',
            'contact_address.min'      => '地址长度最少10位',
            'area.array'               => '集货区域错误',
            'confine.required'         => '限制区域必填',
            'confine.array'            => '限制商品错误',
        ];
        if ($type == 'edit') {
            $rules['ware_id']            = ['required', 'integer'];
            $message['ware_id.required'] = '仓库必选';
            $message['ware_id.integer']  = '仓库错误';
        }
        $LibValidation->validate($param, $rules, $message);
    }

    /**
     * @DOC 获取参数
     */
    public function getData($param, $member, $type = 'add'): array
    {

        $ware['ware_code']       = $param['ware_code'] ?? '';
        $ware['member_uid']      = $member['uid'];
        $ware['ware_name']       = $param['ware_name'] ?? '';
        $ware['ware_no']         = $this->wareNo();
        $ware['line_id']         = $param['line_id'] ?? 0;
        $ware['country_id']      = $param['country_id'] ?? 0;
        $ware['country']         = $param['country'] ?? '';
        $ware['province_id']     = $param['province_id'] ?? 0;
        $ware['province']        = $param['province'] ?? '';
        $ware['city_id']         = $param['city_id'] ?? 0;
        $ware['city']            = $param['city'] ?? '';
        $ware['district_id']     = $param['district_id'] ?? 0;
        $ware['district']        = $param['district'] ?? '';
        $ware['street_id']       = $param['street_id'] ?? 0;
        $ware['street']          = $param['street'] ?? '';
        $ware['address']         = $param['address'] ?? '';
        $ware['ware_cfg_id']     = $param['ware_cfg_id'] ?? 0;
        $ware['contacts']        = $param['contacts'] ?? '';
        $ware['status']          = $param['status'] ?? 0;
        $ware['phone_before']    = $param['phone_before'] ?? '';
        $ware['contact_phone']   = $param['contact_phone'] ?? '';
        $ware['zip']             = $param['zip'] ?? '';
        $ware['contact_address'] = $ware['country'] . $ware['province'] . $ware['city'] . $ware['district'] . $ware['street'] . $ware['address'];
//        $ware['area']            = empty($param['area']) ? '' : json_encode($param['area']);
        $ware['confine']      = empty($param['confine']) ? '' : json_encode($param['confine'], JSON_UNESCAPED_UNICODE);
        $ware['confine_back'] = empty($param['confine_back']) ? '' : json_encode($param['confine_back'], JSON_UNESCAPED_UNICODE);
        if ($type == 'add') {
            $ware['add_time'] = time();
        }

        return $ware;
    }

    /**
     * @DOC 调整状态
     */
    #[RequestMapping(path: 'status', methods: 'post')]
    public function status(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param, [
            'ware_id' => ['required', 'integer'],
            'status'  => ['required', Rule::in([0, 1])],
        ], [
            'ware_id.required' => '仓库错误',
            'ware_id.integer'  => '仓库错误',
            'status.required'  => '状态必选',
            'status.in'        => '状态错误',
        ]);

        $where['ware_id']    = $param['ware_id'];
        $where['member_uid'] = $request->UserInfo['uid'];
        $warehouse           = WarehouseModel::where($where)->exists();
        if (!$warehouse) {
            throw new HomeException('处理失败：当前数据不存在');
        }
        if (WarehouseModel::where($where)->update(['status' => $param['status']])) {
            return $this->response->json(['code' => 200, 'msg' => '修改成功', 'data' => []]);
        }
        return $this->response->json(['code' => 201, 'msg' => '修改失败', 'data' => []]);
    }

    /**
     * @DOC 仓库类型
     */
    #[RequestMapping(path: 'ware/type', methods: 'get,post')]
    public function ware_type(): ResponseInterface
    {
        $baseCache = \Hyperf\Support\make(BaseCacheService::class);
        $data      = $baseCache->WarehouseTypeCache();
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
    }

    //***********************************库区表************************************/

    /**
     * @DOC 库区表
     */
    #[RequestMapping(path: 'area', methods: 'post')]
    public function areaList(RequestInterface $request)
    {
        $params  = $request->all();
        $member  = $request->UserInfo;
        $service = \Hyperf\Support\make(WarehouseService::class);
        $result  = $service->areaList($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 库区新增
     */
    #[RequestMapping(path: 'area/add', methods: 'post')]
    public function areaAdd(RequestInterface $request)
    {
        $params  = $request->all();
        $member  = $request->UserInfo;
        $service = \Hyperf\Support\make(WarehouseService::class);
        $result  = $service->areaAdd($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 库区编辑
     */
    #[RequestMapping(path: 'area/edit', methods: 'post')]
    public function areaEdit(RequestInterface $request)
    {
        $params  = $request->all();
        $member  = $request->UserInfo;
        $service = \Hyperf\Support\make(WarehouseService::class);
        $result  = $service->areaEdit($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 库区删除
     */
    #[RequestMapping(path: 'area/del', methods: 'post')]
    public function areaDel(RequestInterface $request)
    {
        $params  = $request->all();
        $member  = $request->UserInfo;
        $service = \Hyperf\Support\make(WarehouseService::class);
        $result  = $service->areaDel($params, $member);
        return $this->response->json($result);
    }

    //************仓库货位类型*****************/


    /**
     * @DOC 仓库货位类型列表
     */
    #[RequestMapping(path: 'location/type', methods: 'get,post')]
    public function locationTypeList(RequestInterface $request)
    {
        $params  = $request->all();
        $member  = $request->UserInfo;
        $service = \Hyperf\Support\make(WarehouseService::class);
        $result  = $service->locationTypeList($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 仓库货位类型新增
     */
    #[RequestMapping(path: 'location/type/add', methods: 'post')]
    public function locationTypeAdd(RequestInterface $request)
    {
        $params  = $request->all();
        $member  = $request->UserInfo;
        $service = \Hyperf\Support\make(WarehouseService::class);
        $result  = $service->locationTypeAdd($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 仓库货位类型编辑
     */
    #[RequestMapping(path: 'location/type/edit', methods: 'post')]
    public function locationTypeEdit(RequestInterface $request)
    {
        $params  = $request->all();
        $member  = $request->UserInfo;
        $service = \Hyperf\Support\make(WarehouseService::class);
        $result  = $service->locationTypeEdit($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 仓库货位类型删除
     */
    #[RequestMapping(path: 'location/type/del', methods: 'post')]
    public function locationTypeDel(RequestInterface $request)
    {
        $params  = $request->all();
        $member  = $request->UserInfo;
        $service = \Hyperf\Support\make(WarehouseService::class);
        $result  = $service->locationTypeDel($params, $member);
        return $this->response->json($result);
    }


    //************************仓库货位************************************/

    /**
     * @DOC 仓库货位列表
     */
    #[RequestMapping(path: 'location', methods: 'post')]
    public function locationList(RequestInterface $request)
    {
        $params  = $request->all();
        $member  = $request->UserInfo;
        $service = \Hyperf\Support\make(WarehouseService::class);
        $result  = $service->locationList($params, $member);
        return $this->response->json($result);
    }


    /**
     * @DOC 创建货位
     */
    #[RequestMapping(path: 'location/add', methods: 'post')]
    public function locationAdd(RequestInterface $request)
    {
        $params  = $request->all();
        $member  = $request->UserInfo;
        $service = \Hyperf\Support\make(WarehouseService::class);
        $result  = $service->locationAdd($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 货位更新
     */
    #[RequestMapping(path: 'location/edit', methods: 'post')]
    public function locationEdit(RequestInterface $request)
    {
        $params  = $request->all();
        $member  = $request->UserInfo;
        $service = \Hyperf\Support\make(WarehouseService::class);
        $result  = $service->locationEdit($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 货位删除
     */
    #[RequestMapping(path: 'location/del', methods: 'post')]
    public function locationDel(RequestInterface $request)
    {
        $params  = $request->all();
        $member  = $request->UserInfo;
        $service = \Hyperf\Support\make(WarehouseService::class);
        $result  = $service->locationDel($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 打印货位标签
     */
    #[RequestMapping(path: 'location/label', methods: 'post')]
    public function locationLabel(RequestInterface $request)
    {
        $params  = $request->all();
        $service = \Hyperf\Support\make(WarehouseService::class);
        $result  = $service->locationLabel($params);
        return $this->response->json($result);
    }

}
