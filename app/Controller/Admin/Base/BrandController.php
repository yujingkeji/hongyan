<?php

declare(strict_types=1);

namespace App\Controller\Admin\Base;

use App\Controller\Admin\AdminBaseController;
use App\Exception\HomeException;
use App\JsonRpc\BaseServiceInterface;
use App\Model\BrandModel;
use App\Model\BrandWordModel;
use App\Request\LibValidation;
use App\Service\BrandService;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;
use Mockery\Exception;
use function App\Common\batchUpdateSql;

#[Controller(prefix: '/', server: 'httpAdmin')]
class BrandController extends AdminBaseController
{
    #[Inject]
    protected BaseServiceInterface $baseService;
    protected array $searchWhere =
        [
            'brand_name', //中文名称
            'brand_en_name', //英文名称
            'source_name', //发源地名称
            'py_short', //品牌简拼
            'py', //品牌全拼
        ];

    /**
     * @DOC 品牌列表
     */
    #[RequestMapping(path: 'base/brand/lists', methods: 'post')]
    public function brandLists(RequestInterface $request)
    {
        $param = $request->all();
        $result = (new BrandService())->getBrand($param);
        return $this->response->json($result);
    }

    /**
     * @DOC 品牌新增
     */
    #[RequestMapping(path: 'base/brand/add', methods: 'post')]
    public function brandAdd(RequestInterface $request)
    {
        if ($this->syn) return $this->baseSyn();
        $result['code'] = 201;
        $result['msg'] = '处理失败';

        $params = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param = $LibValidation->validate($params,
            [
                'source_name' => ['nullable'],
                'brand_name' => ['required'],
                'brand_en_name' => ['nullable'],
                'py' => ['nullable'],
                'py_short' => ['nullable'],
                'brand_logo' => ['nullable'],
                'source_country_id' => ['nullable'],
                'status' => ['required', Rule::in([0, 1])],
            ], [
                'brand_name.required' => '中文品牌必填',
                'status.required' => '状态错误',
                'status.in' => '状态错误',
            ]);

        $where = [
            ['brand_name', '=', $param['brand_name']],
            ['brand_en_name', '=', $param['brand_en_name']],
        ];
        if (Db::table('brand')->where($where)->exists()) {
            throw new HomeException('当前中文品牌名称和英文品牌名称已存在');
        }

        if (Db::table('brand')->insert($param)) {
            $result['code'] = 200;
            $result['msg'] = '添加成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 品牌编辑
     */
    #[RequestMapping(path: 'base/brand/edit', methods: 'post')]
    public function brandEdit(RequestInterface $request)
    {
        if ($this->syn) return $this->baseSyn();
        $result['code'] = 201;
        $result['msg'] = '处理失败';
        $params = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param = $LibValidation->validate($params,
            [
                'brand_id' => ['required'],
                'brand_name' => ['required'],
                'source_name' => ['nullable'],
                'brand_en_name' => ['nullable'],
                'py' => ['nullable'],
                'py_short' => ['nullable'],
                'brand_logo' => ['nullable'],
                'source_country_id' => ['nullable'],
                'status' => ['required', Rule::in([0, 1])],
            ], [
                'brand_id.required' => '品牌必传',
                'brand_name.required' => '中文品牌必填',
                'status.required' => '状态错误',
                'status.in' => '状态错误',
            ]);

        $where = [
            ['brand_id', '!=', $param['brand_id']],
            ['brand_name', '=', $param['brand_name']],
            ['brand_en_name', '=', $param['brand_en_name']],
        ];
        if (Db::table('brand')->where($where)->exists()) {
            throw new HomeException('当前中文品牌名称和英文品牌名称已存在');
        }

        if (Db::table('brand')->where('brand_id', $param['brand_id'])->update($param)) {
            $result['code'] = 200;
            $result['msg'] = '修改成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 品牌删除
     */
    #[RequestMapping(path: 'base/brand/del', methods: 'post')]
    public function handleDel(RequestInterface $request)
    {
        if ($this->syn) return $this->baseSyn();
        $result['code'] = 201;
        $result['msg'] = '处理失败';

        $params = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param = $LibValidation->validate($params,
            [
                'brand_id' => ['required', Rule::exists('brand', 'brand_id')],
                'source_name' => ['string'],
                'source_country_id' => ['integer'],
                'status' => ['required'],
            ], [
                'brand_id.required' => '品牌不存在',
                'brand_id.exists' => '品牌不存在',
                'status.required' => '状态错误',
            ]);

        $data = BrandModel::where('brand_id', $param['brand_id'])->first()->toArray();

        if ($data['source_name'] != $param['source_name']) {
            throw new HomeException('品牌名不正确');
        }
        if ($data['source_country_id'] != $param['source_country_id']) {
            throw new HomeException('提交的品牌数据与系统种的不匹配不能删除');
        }
        if ($data['status'] !== $param['status'] && $data['status'] !== 0) {
            throw new HomeException('只有禁用状态下才能被删除');
        }
        if (Db::table('brand')->where('brand_id', $data['brand_id'])->delete()) {
            $result['code'] = 200;
            $result['msg'] = '删除成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 品牌状态改变
     */
    #[RequestMapping(path: 'base/brand/status', methods: 'post')]
    public function handleStatus(RequestInterface $request)
    {
        if ($this->syn) return $this->baseSyn();
        $result['code'] = 201;
        $result['msg'] = '处理失败';
        $params = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param = $LibValidation->validate($params,
            [
                'brand_id' => ['required', Rule::exists('brand', 'brand_id')],
                'source_name' => ['string'],
                'source_country_id' => ['integer'],
                'status' => ['required'],
            ], [
                'brand_id.required' => '品牌不存在',
                'brand_id.exists' => '品牌不存在',
                'status.required' => '状态错误',
            ]);

        $data = BrandModel::where('brand_id', $param['brand_id'])->first()->toArray();
        if ($data['source_name'] != $param['source_name']) {
            throw new HomeException('品牌名不正确');
        }
        if ($data['source_country_id'] != $param['source_country_id']) {
            throw new HomeException('提交的品牌数据与系统种的不匹配不能删除');
        }
        if (Db::table('brand')->where('brand_id', $data['brand_id'])->update(['status' => $param['status']])) {
            $result['code'] = 200;
            $result['msg'] = '修改成功';
        }
        return $this->response->json($result);
    }

    /****************************************************************************************/

    /**
     * @DOC 品牌列表
     */
    #[RequestMapping(path: 'base/brand/word/index', methods: 'post')]
    public function wordIndex(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg'] = '获取失败';
        $param = $request->all();
        $where = $wordWhere = [];
        if (isset($param['brand_id']) && !empty($param['brand_id'])) {
            $where[] = ['brand_id', '=', $param['brand_id']];
            $wordWhere[] = ['brand_id', '=', $param['brand_id']];
        }
        // 品牌
        $Brand = BrandModel::where($where)
            ->select(['brand_id', 'brand_name', 'brand_en_name'])->first();
        if (($Brand['brand_name'] !== $param['brand_zh']) && ($Brand['brand_en_name'] !== $param['brand_en'])) {
            throw new Exception('当前品牌数据数据不匹配');
        }
        // 词库
        if (isset($param['keyword']) && !empty($param['keyword'])) {
            $wordWhere[] = ['word', 'like', '%' . $param['keyword'] . '%'];
        }

        $BrandWordData = BrandWordModel::where($wordWhere)->paginate($param['limit'] ?? 20);
        $result['code'] = 200;
        $result['msg'] = '列表获取成功';
        $result['data'] = [
            'total' => $BrandWordData->total(),
            'data' => $BrandWordData->items(),
        ];
        return $this->response->json($result);
    }

    /**
     * @DOC 查询绑定词库
     */
    #[RequestMapping(path: 'base/brand/bind/search', methods: 'post')]
    public function bindSearch(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg'] = '处理失败';
        $params = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param = $LibValidation->validate($params,
            [
                'brand_id' => ['required', Rule::exists('brand', 'brand_id')],
                'word' => ['required'],
            ], [
                'brand_id.required' => '品牌不存在',
                'brand_id.exists' => '品牌不存在',
                'word.required' => '关键词不存在',
            ]);
        $where = [
            ['brand_id', '=', $param['brand_id']],
            ['word', '=', $param['word']],
        ];
        $wordData = Db::table('brand_word')->where($where)->get()->toArray();
        $result['code'] = 200;
        $result['msg'] = '查询成功';
        $result['data'] = $wordData;
        return $this->response->json($result);
    }

    /**
     * @DOC 品牌远程同步
     */
    #[RequestMapping(path: 'base/brand/synchronous', methods: 'post')]
    public function synchronous(RequestInterface $request)
    {
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param = $LibValidation->validate($request->all(),
            [
                'ids' => ['required', 'array'],
            ],
            [
                'ids.required' => '同步错误，请选择同步信息',
                'ids.array' => '同步错误，请选择同步信息',
            ]
        );
        // 远程数据
        $brandDb = $this->baseService->brandById($param['ids']);
        if (isset($brandDb['code']) && $brandDb['code'] == 200 && !empty($brandDb['data'])) {
            $brand_ids = array_column($brandDb['data'], 'brand_id');
            $diff_ids = array_diff($param['ids'], $brand_ids);
            Db::beginTransaction();
            try {
                Db::table('brand')->whereIn('brand_id', $diff_ids)->delete();
                $updateBrandDataSql = batchUpdateSql('brand', $brandDb['data'],['brand_id']);
                Db::update($updateBrandDataSql);
                Db::commit();
                return $this->response->json(['code' => 200, 'msg' => '同步成功', 'data' => []]);
            } catch (\Exception $e) {
                Db::rollBack();
            }
        }
        return $this->response->json(['code' => 201, 'msg' => '未查询到数据，同步失败', 'data' => []]);
    }

    /**
     * @DOC 获取本地所有数据，及远程品牌数据的条数
     */
    #[RequestMapping(path: 'base/brand/count', methods: 'post')]
    public function BrandCount()
    {
        $brandCount = BrandModel::count();
        $ret = $this->baseService->brand('', 1, 1);
        $data = [
            'local' => $brandCount,
            'remote' => $ret['total'] ?? 0,
        ];
        return $this->response->json(['code' => 200, 'msg' => '查询成功', 'data' => $data]);
    }

    /**
     * @DOC 品牌数据全部同步
     */
    #[RequestMapping(path: 'base/brand/synchronous/all', methods: 'post')]
    public function synchronousAll(RequestInterface $request)
    {
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param = $LibValidation->validate($request->all(),
            [
                'page' => ['required', 'integer'],
                'limit' => ['required', 'integer', 'min:1', 'max:1000'],
                'flay' => ['required']
            ],
            [
                'page.required' => '缺少页码',
                'page.integer' => '页码格式错误，必须为数字',
                'limit.required' => '缺少条数',
                'limit.integer' => '条数格式错误，必须为数字',
                'limit.min' => '最小值不少于1条',
                'limit.max' => '最大值不超过1000条',
                'flay.required' => '缺少完成标识',
            ]
        );
        // 初始 为0
        if ($param['page'] == 1) {
            BrandModel::where('status', '<>', 2)->update(['status' => 2]);
        }

        // 获取远程数据
        $ret = $this->baseService->brand('', $param['page'], $param['limit']);
        if (isset($ret['code']) && $ret['code'] == 200) {
            // 逻辑处理
            foreach ($ret['data'] as $k => $v) {
                Db::table('brand')->updateOrInsert(['brand_id' => $v['brand_id']], $v);
            }
        }
        // 完成 status = 0 删除
        if ($param['flay'] == 1) {
            BrandModel::where('status', '=', 2)->delete();
        }
        return $this->response->json(['code' => 200, 'msg' => '同步成功', 'data' => []]);
    }


}
