<?php

namespace App\Controller\Admin\Base;

use App\Common\Lib\Arr;
use App\Controller\Admin\AdminBaseController;
use App\Model\CategoryModel;
use App\Model\CountryAreaModel;
use App\Model\CustomsSupervisionModel;
use App\Model\PortModel;
use App\Model\ProductModel;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

#[Controller(prefix: '/', server: 'httpAdmin')]
class SupervisionController extends AdminBaseController
{

    /**
     * @DOC 监管方式列表
     */
    #[RequestMapping(path: 'base/supervision/lists', methods: 'post')]
    public function lists(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '获取失败';
        $param          = $request->all();
        $data           = CustomsSupervisionModel::with([
            'country' => function ($query) {
                $query->select(['country_id', 'country_name', 'country_code']);
            },
            'cfg'     => function ($query) {
                $query->select(['cfg_id', 'title']);
            }
        ]);
        if (Arr::hasArr($param, 'country_id')) {
            $data = $data->where('country_id', $param['country_id']);
        }
        if (Arr::hasArr($param, 'keyword')) {
            $data = $data->where(function ($query) use ($param) {
                $query->orWhere('supervision_name', 'like', '%' . $param['keyword'] . '%')
                    ->orWhere('supervision_code', 'like', '%' . $param['keyword'] . '%');
            });
        }
        $data = $data->paginate($param['limit'] ?? 20);

        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = [
            'total' => $data->total(),
            'data'  => $data->items(),
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
    #[RequestMapping(path: 'base/supervision/single', methods: 'post')]
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
}
