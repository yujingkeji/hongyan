<?php

namespace App\Controller\Admin\Base;

use App\Controller\Admin\AdminBaseController;
use App\JsonRpc\BaseServiceInterface;
use App\Model\CategoryModel;
use App\Model\PortModel;
use App\Request\LibValidation;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

#[Controller(prefix: '/', server: 'httpAdmin')]
class PortController extends AdminBaseController
{

    /**
     * @DOC 口岸列表
     */
    #[RequestMapping(path: 'base/port/lists', methods: 'post')]
    public function lists(RequestInterface $request)
    {
        $param = $request->all();
        $query = PortModel::query();

        if (!empty($param['name'])) {
            $query->where('name', 'like', '%' . $param['name'] . '%');
        }
        $count = $query->count('port_id');
        $data  = $query->forPage($param['page'] ?? 1, $param['limit'] ?? 20)->get()->toArray();

        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = [
            'total' => $count,
            'data'  => $data,
        ];
        return $this->response->json($result);
    }

    /**
     * @DOC 寻找上级
     */
    protected function parent($id, array $result)
    {
        $where = ['port_id' => $id];
        $data  = PortModel::where($where)->first();
        if (!empty($data)) {
            $result[$data['parent_id']] = $data;
            return $this->parent($data['parent_id'], $result);
        }
        ksort($result);
        $param['port_id'] = array_column($result, 'port_id');
        $param['name']    = array_column($result, 'name');
        return $param;
    }

    public function sigleParent($id, array $result)
    {
        $where = ['cfg_id' => $id];
        $data  = CategoryModel::where($where)->first();
        if (!empty($data)) {
            $data = $data->toArray();
            if (isset($data['pid']) && $data['pid'] >= 0) {
                $result[$data['pid']] = $data;
                return self::sigleParent($data['pid'], $result);
            }
        }

        ksort($result);
        $param['cfg_id'] = array_column($result, 'cfg_id');
        $param['name']   = array_column($result, 'title');
        return $param;
    }

    /**
     * @DOC 搜索
     */
    #[RequestMapping(path: 'base/port/single', methods: 'post')]
    public function handleSingle(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '查找失败';
        $param          = $request->all();
        $where          =
            [
                ['name', 'like', $param['keyword'] . '%']
            ];
        $data           = [];
        $data           = $this->single($where, $data);

        $result['code'] = 200;
        $result['msg']  = '查询成功';
        $result['data'] = $data;
        return $this->response->json($result);
    }

    public function single($where, &$result = [])
    {
        $data = PortModel::where($where)->get()->toArray();

        foreach ($data as $key => $val) {
            $parent        = $this->parent($val['port_id'], []);
            $val['parent'] = $parent;
            if (!empty($val['cfg_id'])) {
                $customs        = $this->sigleParent($val['cfg_id'], []);
                $val['customs'] = $customs;
            }
            array_push($result, $val);
        }
        $ParentArr = array_column($data, 'parent_id');
        $ParentArr = array_unique($ParentArr);
        foreach ($ParentArr as $k => $v) {
            if ($v == 0) {
                unset($ParentArr[$k]);
            }
        }
        if (!empty($ParentArr) && count($ParentArr) > 0) {
            unset($where);
            $where = [
                ['port_id', 'in', $ParentArr]
            ];
            return $this->single($where, $result);
        }
        return $result;
    }

    /**
     * @DOC 口岸列表
     */
    #[RequestMapping(path: 'base/port/have/lists', methods: 'post')]
    public function haveLists(RequestInterface $request)
    {
        $params         = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $param          = $LibValidation->validate($params,
            [
                'country_id' => ['required'],
            ], [
                'country_id.required' => '国家必填',
            ]);
        $data           = PortModel::where('country_id', '=', $param['country_id'])
            ->where('port_code', '<>', '0')
            ->select(['port_id', 'country_id', 'name', 'port_code', 'cfg_id'])
            ->get()->toArray();
        $result['code'] = 200;
        $result['msg']  = '查询成功';
        $result['data'] = $data;
        return $this->response->json($result);
    }

    /**
     * @DOC 获取本地所有数据，及远程口岸数据的条数
     */
    #[RequestMapping(path: 'base/port/count', methods: 'post')]
    public function portCount()
    {
        $CountryCount = PortModel::count();
        $baseService  = \Hyperf\Support\make(BaseServiceInterface::class);
        $ret          = $baseService->port(null, 0, 1, 1);
        $data         = [
            'local'  => $CountryCount,
            'remote' => $ret['total'] ?? 0,
        ];
        return $this->response->json(['code' => 200, 'msg' => '查询成功', 'data' => $data]);
    }


    /**
     * @DOC 口岸地区数据全部同步
     */
    #[RequestMapping(path: 'base/port/synchronous/all', methods: 'post')]
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
                'limit.max'      => '最大值不超过1000条',
                'flay.required'  => '缺少完成标识',
            ]
        );
        // 初始 为0
        if ($param['page'] == 1) {
            PortModel::where('status', '<>', 2)->update(['status' => 2]);
        }

        $baseService = \Hyperf\Support\make(BaseServiceInterface::class);
        $ret         = $baseService->port(null, 0, $param['page'], $param['limit']);
        // 获取远程数据
        if (!empty($ret['data'])) {
            // 逻辑处理
            foreach ($ret['data'] as $k => $v) {
                Db::table('port')->updateOrInsert(['port_id' => $v['port_id']], $v);
            }
        }
        // 完成 status = 0 删除
        if ($param['flay'] == 1) {
            PortModel::where('status', '=', 2)->delete();
        }
        return $this->response->json(['code' => 200, 'msg' => '同步成功', 'data' => []]);
    }


}
