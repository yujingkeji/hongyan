<?php

namespace App\Controller\Admin\Base;

use App\Common\Lib\Arr;
use App\Controller\Admin\AdminBaseController;
use App\JsonRpc\BaseServiceInterface;
use App\Model\CountryAreaModel;
use App\Request\LibValidation;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

#[Controller(prefix: '/', server: 'httpAdmin')]
class AreaController extends AdminBaseController
{
    #[Inject]
    protected BaseServiceInterface $baseService;

    /**
     * @DOC 行政区域列表
     */
    #[RequestMapping(path: 'base/area/lists', methods: 'post')]
    public function lists(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '获取失败';
        $param          = $request->all();


        $parent_id = Arr::hasArr($param, 'pid') ? $param['pid'] : 0;
        $where[]   = ['status', '=', 1];
        $where[]   = ['parent_id', '=', $parent_id];

        $data = CountryAreaModel::with(
            [
                'country'  => function ($query) {
                    $query->select(['country_id', 'country_name', 'country_code']);
                },
                'children' => function ($query) {
                    $query->select(['id', 'level', 'name', 'name_en', 'parent_id', 'name_zh', 'first_letter', 'country_id', 'code']);
                },
            ])->where($where)
            ->select(['id', 'level', 'name', 'name_en', 'name_zh', 'first_letter', 'country_id', 'code', 'status', 'parent_id', 'level'])
            ->paginate($param['limit'] ?? 20);
        $list = $data->items();
        foreach ($list as $key => $val) {
            $list[$key]['hasChildren'] = isset($val['children'][0]['id']) ? true : false;
            $parent                    = $this->parent($val['id'], []);
            $list[$key]['parent']      = $parent;
            unset($list[$key]['children']);
        }
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = [
            'total' => $data->total(),
            'data'  => $list,
        ];
        return $this->response->json($result);
    }

    /**
     * @DOC 寻找上级
     */
    protected function parent($id, array $result)
    {
        $data = CountryAreaModel::where(['id' => $id])->first();

        if (isset($data['parent_id']) && $data['parent_id'] >= 0) {
            $result[$data['level']] = $data;
            return $this->parent($data['parent_id'], $result);
        }
        ksort($result);
        $param['id']   = array_column($result, 'id');
        $param['name'] = array_column($result, 'name');
        return $param;

    }

    /**
     * @DOC 搜索
     */
    #[RequestMapping(path: 'base/area/single', methods: 'post')]
    public function handleSingle(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '查找失败';
        $param          = $request->all();

        $data = CountryAreaModel::query()->where('status', '=', 1);
        if (Arr::hasArr($param, 'keyword')) {
            $data = $data->where(function ($query) use ($param) {
                $query->orWhere('name', 'like', $param['keyword'] . '%')
                    ->orWhere('name_zh', 'like', $param['keyword'] . '%')
                    ->orWhere('name_en', 'like', $param['keyword'] . '%');
            });
        } else {
            $data = $data->where('parent_id', '=', 0);
        }
        $data = $data->with(
            [
                'country'  => function ($query) {
                    $query->select(['country_id', 'country_name', 'country_code']);
                },
                'children' => function ($query) {
                    $query->select(['id', 'level', 'name', 'name_en', 'parent_id', 'name_zh', 'first_letter', 'country_id', 'code']);
                },
            ])
//            ->select(['id', 'level', 'name', 'name_en', 'name_zh', 'first_letter', 'country_id', 'code', 'status', 'parent_id', 'level'])
            ->paginate($param['limit'] ?? 20);

        $list = $data->items();

        foreach ($list as $key => $val) {
            $list[$key]['hasChildren'] = isset($val['children'][0]['id']) ? true : false;
            $parent                    = $this->parent($val['id'], []);
            $list[$key]['parent']      = $parent;
            unset($list[$key]['children']);
        }
        $result['code'] = 200;
        $result['msg']  = '查询成功';
        $result['data'] = [
            'total' => $data->total(),
            'data'  => $list,
        ];
        return $this->response->json($result);
    }

    /**
     * @DOC 获取本地所有数据，及远程品牌数据的条数
     */
    #[RequestMapping(path: 'base/area/count', methods: 'post')]
    public function CountryCount()
    {
        $CountryCount = CountryAreaModel::count();
        $ret          = $this->baseService->countryAreaByPage(1, 1);
        $data         = [
            'local'  => $CountryCount,
            'remote' => $ret['data']['total'] ?? 0,
        ];
        return $this->response->json(['code' => 200, 'msg' => '查询成功', 'data' => $data]);
    }

    /**
     * @DOC 国家地区数据全部同步
     */
    #[RequestMapping(path: 'base/area/synchronous/all', methods: 'post')]
    public function synchronousAll(RequestInterface $request)
    {
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($request->all(),
            [
                'page'  => ['required', 'integer'],
                'limit' => ['required', 'integer', 'min:1', 'max:1000'],
                'flay'  => ['required']
            ],
            [
                'page.required'  => '缺少页码',
                'page.integer'   => '页码格式错误，必须为数字',
                'limit.required' => '缺少条数',
                'limit.integer'  => '条数格式错误，必须为数字',
                'limit.min'      => '最小值不少于1条',
                'limit.max'      => '最大值不超过1000条',
                'flay.required'  => '缺少完成标识',
            ]
        );
        // 初始 为0
        if ($param['page'] == 1) {
            CountryAreaModel::where('status', '<>', 2)->update(['status' => 2]);
        }

        // 获取远程数据
        $ret = $this->baseService->countryAreaByPage($param['page'], $param['limit']);
        if (isset($ret['code']) && $ret['code'] == 200) {
            // 逻辑处理
            foreach ($ret['data']['data'] as $k => $v) {
                Db::table('country_area')->updateOrInsert(['id' => $v['id']], $v);
            }
        }
        // 完成 status = 0 删除
        if ($param['flay'] == 1) {
            CountryAreaModel::where('status', '=', 2)->delete();
        }
        return $this->response->json(['code' => 200, 'msg' => '同步成功', 'data' => []]);
    }
}
