<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Common\Lib\Arr;
use App\Common\Lib\Str;
use App\Exception\HomeException;
use App\JsonRpc\BaseServiceInterface;
use App\Model\CountryCodeModel;
use App\Request\LibValidation;
use App\Service\Cache\BaseCacheService;
use App\Service\Cache\BaseEditUpdateCacheService;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: '/', server: 'httpAdmin')]
class CountryController extends AdminBaseController
{
    #[Inject]
    protected BaseServiceInterface $baseService;

    # 限制操作标识
    protected bool $syn = true;

    /**
     * @DOC 获取国家信息
     */
    #[RequestMapping(path: 'country/read', methods: 'post')]
    public function read(): ResponseInterface
    {
        $baseCache = new BaseCacheService();
        $data      = $baseCache->CountryCodeCache();
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
    }

    /**
     * @DOC 国家地区列表
     */
    #[RequestMapping(path: 'country/lists', methods: 'post')]
    public function lists(RequestInterface $request): ResponseInterface
    {
        $param = $request->all();

        $where = [];
        if (Arr::hasArr($param, 'keyword')) {
            $where[] = ['country_name', 'like', '%' . $param['keyword'] . '%'];
        }
        $data = CountryCodeModel::where($where)->paginate($param['limit'] ?? 20);

        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => [
                'count' => $data->total(),
                'lists' => $data->items()
            ]
        ]);
    }

    /**
     * @DOC 修改国家地区状态
     */
    #[RequestMapping(path: 'country/status', methods: 'post')]
    public function handleStatus(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($param,
            [
                'country_id'   => 'required',
                'status'       => ['required', Rule::in([0, 1])],
                'country_name' => 'required',
            ], [
                'country_id.required'   => '国家必传',
                'status.required'       => '状态必传',
                'status.in'             => '状态错误',
                'country_name.required' => '国家必传',
            ]);


        $country = CountryCodeModel::where('country_id', $param['country_id'])->first();
        if (empty($country)) {
            throw new HomeException('数据不存在');
        }
        if ($country['status'] != $param['status'] && $country['country_name'] != $param['country_name']) {
            throw new HomeException('信息不匹配、调整状态');
        }
        $data['status'] = $param['status'];
        CountryCodeModel::where('country_id', $param['country_id'])->update($data);
        (new BaseEditUpdateCacheService())->CountryCodeCache();
        return $this->response->json(['code' => 200, 'msg' => '修改成功', 'data' => []]);
    }

    /**
     * @DOC 国家地区删除
     */
    #[RequestMapping(path: 'country/del', methods: 'post')]
    public function handleDel(RequestInterface $request): ResponseInterface
    {
        if ($this->syn) throw new HomeException('基数数据不提供维护功能、请移步到基数数据服务');
        $param         = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($param,
            [
                'country_id' => 'required',
                'status'     => ['required', Rule::in([0, 1])],
            ], [
                'country_id.required' => '国家必传',
                'status.required'     => '状态必传',
                'status.in'           => '状态错误',
            ]);

        $country = CountryCodeModel::where('country_id', $param['country_id'])->first();
        if (empty($country)) {
            throw new HomeException('数据不存在');
        }
        if ($country['status'] != $param['status'] && $country['status'] != 0) {
            throw new HomeException('非禁止状态、不能删除');
        }
        CountryCodeModel::where('country_id', $param['country_id'])->delete();
        (new BaseEditUpdateCacheService())->CountryCodeCache();
        return $this->response->json(['code' => 200, 'msg' => '删除成功', 'data' => []]);
    }


    /**
     * @DOC 国家地区新增
     */
    #[RequestMapping(path: 'country/add', methods: 'post')]
    public function handleAdd(RequestInterface $request): ResponseInterface
    {
        if ($this->syn) throw new HomeException('基数数据不提供维护功能、请移步到基数数据服务');
        $param         = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($param,
            [
                'country_name'         => 'required',
                'country_code'         => 'required',
                'country_full_code'    => 'required',
                'currency_code'        => 'required',
                'zip_code'             => 'required',
                'customs_country_code' => 'required',
                'status'               => ['required', Rule::in([0, 1])],
            ], [
                'country_name.required'         => '国家必填',
                'status.required'               => '状态必选',
                'status.in'                     => '状态错误',
                'country_code.required'         => '国家编码简称必填',
                'currency_code.required'        => '货币编码必填',
                'country_full_code.required'    => '国家编码全称必填',
                'zip_code.required'             => '国际邮编必填',
                'customs_country_code.required' => '海关代码必填',
            ]);
        $data          = $this->getData($param);
        CountryCodeModel::insert($data);
        (new BaseEditUpdateCacheService())->CountryCodeCache();
        return $this->response->json(['code' => 200, 'msg' => '添加成功', 'data' => []]);
    }

    /**
     * @DOC 国家地区修改
     */
    #[RequestMapping(path: 'country/edit', methods: 'post')]
    public function handleEdit(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($param,
            [
                'country_id'           => 'required',
                'country_name'         => 'required',
                'country_code'         => 'required',
                'country_full_code'    => 'required',
                'currency_code'        => 'required',
                'zip_code'             => 'required',
                'customs_country_code' => 'required',
                'status'               => ['required', Rule::in([0, 1])],
            ], [
                'country_id.required'           => '国家必填',
                'country_name.required'         => '国家必填',
                'status.required'               => '状态必选',
                'status.in'                     => '状态错误',
                'country_code.required'         => '国家编码简称必填',
                'currency_code.required'        => '货币编码必填',
                'country_full_code.required'    => '国家编码全称必填',
                'zip_code.required'             => '国际邮编必填',
                'customs_country_code.required' => '海关代码必填',
            ]);

        $data = $this->getData($param);
        CountryCodeModel::where('country_id', $param['country_id'])->update($data);
        (new BaseEditUpdateCacheService())->CountryCodeCache();
        return $this->response->json(['code' => 200, 'msg' => '编辑成功', 'data' => []]);
    }

    public function getData($param): array
    {
        $data['country_name']         = $param['country_name'];
        $data['country_code']         = Str::upper($param['country_code']);
        $data['lang_code']            = $param['lang_code'] ?? '';
        $data['country_lang']         = $param['country_lang'];
        $data['country_full_code']    = $param['country_full_code'];
        $data['currency_code']        = Str::upper($param['currency_code']);
        $data['zip_code']             = $param['zip_code'];
        $data['status']               = $param['status'];
        $data['customs_country_code'] = $param['customs_country_code'];
        return $data;
    }


    /**
     * @DOC 获取本地所有数据，及远程国家地区数据的条数
     */
    #[RequestMapping(path: 'country/count', methods: 'get,post')]
    public function CountryCount()
    {
        $countryCount = CountryCodeModel::count();
        $ret          = $this->baseService->countryCode('', 1, 1);
        $data         = [
            'local'  => $countryCount,
            'remote' => $ret['data']['total'] ?? 0,
        ];
        return $this->response->json(['code' => 200, 'msg' => '查询成功', 'data' => $data]);
    }

    /**
     * @DOC 国家地区数据全部同步
     */
    #[RequestMapping(path: 'country/synchronous/all', methods: 'post')]
    public function synchronousAll(RequestInterface $request)
    {
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($request->all(),
            [
                'page'  => ['required', 'integer'],
                'limit' => ['required', 'integer', 'min:1', 'max:200'],
                'flay'  => ['required']
            ],
            [
                'page.required'  => '缺少页码',
                'page.integer'   => '页码格式错误，必须为数字',
                'limit.required' => '缺少条数',
                'limit.integer'  => '条数格式错误，必须为数字',
                'limit.min'      => '最小值不少于1条',
                'limit.max'      => '最大值不超过200条',
                'flay.required'  => '缺少完成标识',
            ]
        );
        // 初始 为0
        if ($param['page'] == 1) {
            CountryCodeModel::where('status', '<>', 2)->update(['status' => 2]);
        }

        // 获取远程数据
        $ret = $this->baseService->countryCode(null, $param['page'], $param['limit']);
        if (isset($ret['code']) && $ret['code'] == 200) {
            // 逻辑处理
            foreach ($ret['data']['data'] as $k => $v) {
                Db::table('country_code')->updateOrInsert(['country_id' => $v['country_id']], $v);
            }
        }
        // 完成 status = 0 删除
        if ($param['flay'] == 1) {
            CountryCodeModel::where('status', '=', 2)->delete();
            (new BaseEditUpdateCacheService())->CountryCodeCache();
        }
        return $this->response->json(['code' => 200, 'msg' => '同步成功', 'data' => []]);
    }


}
