<?php

declare(strict_types=1);

namespace App\Controller\Admin\Base;

use App\Controller\Admin\AdminBaseController;
use App\Exception\HomeException;
use App\Model\CategoryModel;
use App\Request\LibValidation;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;

#[Controller(prefix: '/', server: 'httpAdmin')]
class IndexController extends AdminBaseController
{

    /**
     * @DOC 常用数据列表
     */
    #[RequestMapping(path: 'base/index/lists', methods: 'post')]
    public function indexLists(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '获取失败';
        $param          = $request->all();
        $data['limit']  = (isset($param['pid']) && $param['pid'] > 0) ? 2000 : $param['limit'];
        $where          = [];
        if (isset($param['pid']) && !empty($param['pid'])) {
            $where[] = ['pid', '=', $param['pid']];
        } else {
            $where[] = ['pid', '=', 0];
        }
        if (isset($param['model']) && $param['model'] > 0) {
            $where[] = ['model', '=', $param['model']];
        }
        if (isset($param['keyword']) && !empty($param['keyword'])) {
            $where[] = ['title|title_en|code', 'like', '%' . $param['keyword'] . '%'];
        }
        $CategoryData = CategoryModel::withCount(['children'])->where($where)->paginate($data['limit']);
        $data         = $CategoryData->items();
        foreach ($data as $key => $val) {
            $data[$key]['hasChildren'] = (isset($val['children_count']) && $val['children_count'] > 0) ? true : false;
        }
        $result['code']  = 200;
        $result['msg']   = '获取成功';
        $result['data']  = $data;
        $result['count'] = $CategoryData->total();
        return $this->response->json($result);
    }

    /**
     * @DOC 常用数据新增
     */
    #[RequestMapping(path: 'base/index/add', methods: 'post')]
    public function indexAdd(RequestInterface $request)
    {
        if ($this->syn) return $this->baseSyn();
        $result['code'] = 201;
        $result['msg']  = '处理失败';

        $params        = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'title'    => ['required'],
                'status'   => ['required', Rule::in([0, 1])],
                'title_en' => ['nullable'],
                'pid'      => ['nullable', 'integer'],
                'code'     => ['nullable'],
                'model'    => ['nullable'],
                'desc'     => ['nullable'],
            ], [
                'title.required'  => '名称必传',
                'status.required' => '状态错误',
                'status.in'       => '状态错误',
            ]);

        $data['pid']      = $param['pid'] ?? 0;
        $data['title']    = $param['title'];
        $data['title_en'] = $param['title_en'] ?? '';
        $data['code']     = $param['code'] ?? '';
        $data['model']    = !empty($param['model']) ? $param['model'] : 0;
        $data['desc']     = $param['desc'] ?? '';
        $data['status']   = $param['status'];

        $where['title'] = $data['title'];
        $where['pid']   = $data['pid'];
        $category       = Db::table('category')->where($where)->first();
        if (empty($category)) {
            $cfg_id = Db::table('category')->insertGetId($data);
            if ($data['pid'] == 0) {
                Db::table('category')->where('cfg_id', '=', $cfg_id)->update(['model' => $cfg_id]);
            }
            $result['code'] = 200;
            $result['msg']  = '添加成功';
        } else {
            $result['msg'] = $data['title'] . '：已经存在';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 常用数据编辑
     */
    #[RequestMapping(path: 'base/index/edit', methods: 'post')]
    public function handleEdit(RequestInterface $request)
    {
        if ($this->syn) return $this->baseSyn();
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $param          = $LibValidation->validate($params,
            [
                'cfg_id'   => ['required'],
                'title'    => ['required'],
                'status'   => ['required', Rule::in([0, 1])],
                'title_en' => ['nullable'],
                'pid'      => ['nullable', 'integer'],
                'code'     => ['nullable'],
                'desc'     => ['nullable'],
            ], [
                'cfg_id.required' => '常用数据必传',
                'title.required'  => '名称必传',
                'status.required' => '状态错误',
                'status.in'       => '状态错误',
            ]);

        $where = [
            ['cfg_id', '!=', $param['cfg_id']],
            ['title', '=', $param['title']],
        ];

        if (Db::table('category')->where($where)->exists()) {
            throw new HomeException($param['title'] . '已经存在');
        }

        $data['cfg_id']   = $param['cfg_id'];
        $data['title']    = $param['title'];
        $data['title_en'] = $param['title_en'] ?? '';
        $data['status']   = $param['status'];
        $data['code']     = $param['code'] ?? '';
        $data['desc']     = $param['desc'] ?? '';

        if (Db::table('category')->where('cfg_id', '=', $data['cfg_id'])->update($data)) {
            $result['code'] = 200;
            $result['msg']  = '修改成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 常用数据删除
     */
    #[RequestMapping(path: 'base/index/del', methods: 'post')]
    public function handleDel(RequestInterface $request)
    {
        if ($this->syn) return $this->baseSyn();
        $result['code'] = 201;
        $result['msg']  = '处理失败';

        $params        = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'cfg_id' => ['required', Rule::exists('category', 'cfg_id')],
                'title'  => ['required'],
                'status' => ['required', Rule::in([0, 1])],
            ], [
                'cfg_id.required' => '常用数据必传',
                'cfg_id.exists'   => '数据不存在',
                'title.required'  => '名称必传',
                'status.required' => '状态错误',
                'status.in'       => '状态错误',
            ]);

        $data = CategoryModel::where('cfg_id', $param['cfg_id'])->first()->toArray();
        if ($data['status'] !== 0 || $data['status'] != $param['status']) {
            throw new HomeException('非禁用状态 禁止删除');
        }
        if ($data['title'] !== $param['title']) {
            throw new HomeException('请确认名称是否存在其他特殊符号');
        }
        if (Db::table('category')->where('cfg_id', $data['cfg_id'])->delete()) {
            $result['code'] = 200;
            $result['msg']  = '删除成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 常用数据状态改变
     */
    #[RequestMapping(path: 'base/index/status', methods: 'post')]
    public function handleStatus(RequestInterface $request)
    {
        if ($this->syn) return $this->baseSyn();
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $param          = $LibValidation->validate($params,
            [
                'cfg_id' => ['required', Rule::exists('category', 'cfg_id')],
                'title'  => ['required'],
                'status' => ['required', Rule::in([0, 1])],
            ], [
                'cfg_id.required' => '常用数据必传',
                'cfg_id.exists'   => '数据不存在',
                'title.required'  => '名称必传',
                'status.required' => '状态错误',
                'status.in'       => '状态错误',
            ]);

        $data = CategoryModel::where('cfg_id', $param['cfg_id'])->first()->toArray();
        if ($data['status'] == $param['status']) {
            throw new HomeException('当前状态无需更新');
        }
        if ($data['title'] !== $param['title']) {
            throw new HomeException('请确认名称是否存在其他特殊符号');
        }
        if (Db::table('category')->where('cfg_id', '=', $data['cfg_id'])->update(['status' => $param['status']])) {
            $result['code'] = 200;
            $result['msg']  = '修改成功';
        }
        return $this->response->json($result);
    }


}
