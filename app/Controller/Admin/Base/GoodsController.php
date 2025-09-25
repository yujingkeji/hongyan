<?php

namespace App\Controller\Admin\Base;

use App\Common\Lib\Arr;
use App\Controller\Admin\AdminBaseController;
use App\Exception\HomeException;
use App\JsonRpc\BaseServiceInterface;
use App\Model\GoodsCategoryItemModel;
use App\Model\GoodsCategoryModel;
use App\Model\GoodsTemplateFieldModel;
use App\Model\GoodsTemplateItemModel;
use App\Model\GoodsTemplateModel;
use App\Model\RecordCategoryGoodsModel;
use App\Model\TemplateCategoryModel;
use App\Request\LibValidation;
use App\Service\Cache\BaseEditUpdateCacheService;
use App\Service\GoodsService;

use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use function App\Common\batchUpdateSql;

#[Controller(prefix: '/', server: 'httpAdmin')]
class GoodsController extends AdminBaseController
{
    #[Inject]
    protected BaseServiceInterface $baseService;

    #[Inject]
    protected GoodsService $goodsService;

    /**
     * @DOC 商品列表
     */
    #[RequestMapping(path: 'base/goods/lists', methods: 'post')]
    public function lists(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '获取失败';
        $param          = $request->all();
        $data           = GoodsCategoryItemModel::query()->with([
            'cate1', 'cate2'
        ]);
        $where          = [];
        if (Arr::hasArr($param, 'pid', true)) {
            $data = $data->where('pid', $param['pid']);
        }
        if (Arr::hasArr($param, 'cate1', true)) {
            $data = $data->where('cate1', $param['cate1']);
        }
        if (Arr::hasArr($param, 'cate2', true)) {
            $data = $data->where('cate2', $param['cate2']);
        }
        if (Arr::hasArr($param, 'keyword')) {
            $data = $data->where(function ($query) use ($param) {
                $query->orWhere('name', 'like', '%' . $param['keyword'] . '%')
                    ->orWhere('name_en', 'like', '%' . $param['keyword'] . '%');
            });
        }
        $data           = $data->paginate($param['limit'] ?? 20);
        $result['code'] = 200;
        $result['msg']  = '查询成功';
        $result['data'] = [
            'total' => $data->total(),
            'data'  => $data->items(),
        ];
        return $this->response->json($result);
    }


    /**
     * @DOC 类目列表
     */
    #[RequestMapping(path: 'base/goods/cate/lists', methods: 'post')]
    public function GoodsCatelists(RequestInterface $request)
    {
        $param = $request->all();

        $data = RecordCategoryGoodsModel::query()
            ->with([
                'template'
            ]);

        if (!empty($param['keyword'])) {
            $data->where('goods_name', 'like', '%' . $param['keyword'] . '%');
        }
        if (!empty($param['country_id'])) {
            $data->where('country_id', $param['country_id']);
        }
        if (isset($param['parent_id']) && is_numeric($param['parent_id'])) {
            $data->where('parent_id', $param['parent_id']);
        }
        $data = $data->orderBy('sort')->paginate($param['limit'] ?? 20);

        $result['code']  = 200;
        $result['msg']   = '查询成功';
        $result['data']  = $data->items();
        $result['total'] = $data->total();
        return $this->response->json($result);
    }

    /**
     * 获取树状列表
     */
    #[RequestMapping(path: 'base/goods/cate/all', methods: 'post')]
    public function GoodsCateAll(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '新增失败';
        $CateDb         = RecordCategoryGoodsModel::get()->toArray();
        $CateDb         = Arr::tree($CateDb, 'id', 'parent_id');
        $CateDb         = Arr::reorder($CateDb, 'sort', 'SORT_ASC');
        $result['code'] = 200;
        $result['msg']  = '查询成功';
        $result['data'] = $CateDb;
        return $this->response->json($result);
    }

    /**
     * @DOC 类目远程同步
     */
    #[RequestMapping(path: 'base/goods/category/synchronous', methods: 'post')]
    public function synchronous(RequestInterface $request)
    {
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($request->all(),
            [
                'ids' => ['required', 'array'],
            ],
            [
                'ids.required' => '同步错误，请选择同步信息',
                'ids.array'    => '同步错误，请选择同步信息',
            ]
        );
        // 远程数据
        $categoryDb = $this->baseService->recordGoodsCategoryInfo($param['ids']);

        if (isset($categoryDb['code']) && $categoryDb['code'] == 200 && !empty($categoryDb['data'])) {
            $category_ids = array_column($categoryDb['data'], 'id');
            $diff_ids     = array_diff($param['ids'], $category_ids);
            Db::beginTransaction();
            try {
                Db::table('record_category_goods')->whereIn('id', $diff_ids)->delete();
                $updateRecordDataSql = batchUpdateSql('record_category_goods', $categoryDb['data']);
                Db::update($updateRecordDataSql);
                Db::commit();
                // 缓存
                \Hyperf\Support\make(BaseEditUpdateCacheService::class)->recordCategoryGoodsCache();
                return $this->response->json(['code' => 200, 'msg' => '同步成功', 'data' => []]);
            } catch (\Exception $e) {
                Db::rollBack();
            }
        }
        return $this->response->json(['code' => 201, 'msg' => '未查询到数据，同步失败', 'data' => []]);
    }

    /**
     * @DOC 获取本地所有数据，及远程分类数据的条数
     */
    #[RequestMapping(path: 'base/goods/category/count', methods: 'post')]
    public function dataCount()
    {
        $categoryCount = RecordCategoryGoodsModel::count();
        $ret           = $this->baseService->recordGoodsCategoryByPage(1, 1);
        $data          = [
            'local'  => $categoryCount,
            'remote' => $ret['data']['total'] ?? 0,
        ];
        return $this->response->json(['code' => 200, 'msg' => '查询成功', 'data' => $data]);
    }

    /**
     * @DOC 分类数据全部同步
     */
    #[RequestMapping(path: 'base/goods/category/synchronous/all', methods: 'post')]
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
        $category_ids  = RecordCategoryGoodsModel::pluck('id')->toArray();
        // 初始 为0
        if ($param['page'] == 1) {
            RecordCategoryGoodsModel::where('status', '<>', 0)->update(['status' => 0]);
        }

        // 获取远程数据
        $ret = $this->baseService->recordGoodsCategoryByPage($param['page'], $param['limit']);
        if (isset($ret['code']) && $ret['code'] == 200) {
            // 逻辑处理
            foreach ($ret['data']['data'] as $k => $v) {
                $v['status'] = 1;
                Db::table('record_category_goods')->updateOrInsert(['id' => $v['id']], $v);
            }
        }
        // 完成 status = 0 删除
        if ($param['flay'] == 1) {
            RecordCategoryGoodsModel::where('status', '=', 0)->delete();
            // 缓存
            \Hyperf\Support\make(BaseEditUpdateCacheService::class)->recordCategoryGoodsCache();
        }
        return $this->response->json(['code' => 200, 'msg' => '同步成功', 'data' => []]);
    }



    /**
     * @DOC 分类设置模板
     */
    #[RequestMapping(path: 'base/goods/template/category', methods: 'post')]
    public function templateCategory(RequestInterface $request)
    {
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($request->all(),
            [
                'template_id' => ['required', 'integer'],
                'category_id' => ['required', 'integer'],
                'level'       => ['required', 'integer'],
            ],
            [
                'template_id.required' => '请选择模板',
                'template_id.integer'  => '请选择模板',
                'category_id.required' => '商品分类选择错误',
                'category_id.integer'  => '商品分类选择错误',
                'level.required'       => '缺少分类层级',
                'level.integer'        => '缺少分类层级',
            ]
        );
        $data          = [
            'template_id' => $param['template_id'],
            'category_id' => $param['category_id'],
        ];
        Db::table('template_category')->updateOrInsert(['category_id' => $param['category_id']], $data);

        $child_ids = RecordCategoryGoodsModel::where('parent_id', $param['category_id'])->pluck('id')->toArray();
        foreach ($child_ids as $child_id) {
            $data = [
                'template_id' => $param['template_id'],
                'category_id' => $child_id,
            ];
            Db::table('template_category')->updateOrInsert(['category_id' => $child_id], $data);
        }
        return $this->response->json(['code' => 200, 'msg' => '设置成功', 'data' => []]);
    }


}
