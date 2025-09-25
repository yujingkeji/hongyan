<?php

namespace App\Controller\Admin\Base;

use App\Common\Lib\Arr;
use App\Controller\Admin\AdminBaseController;
use App\Service\ConfigService;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

#[Controller(prefix: '/', server: 'httpAdmin')]
class ConfigController extends AdminBaseController
{

    /**
     * @DOC 通用配置列表
     */
    #[RequestMapping(path: 'base/config/lists', methods: 'post')]
    public function lists(RequestInterface $request)
    {
        $param  = $request->all();
        $result = ConfigService::configLists($param);
        return $this->response->json($result);
    }

    /**
     * @DOC 渠道节点
     */
    #[RequestMapping(path: 'base/config/channel/node', methods: 'post')]
    public function channelNode(RequestInterface $request)
    {
        $param   = $request->all();
        $where[] = ['status', '=', 1];
        $where[] = ['pid', '=', 1618];
        if (Arr::hasArr($param, 'cfg_id')) $where[] = ['cfg_id', '=', $param['cfg_id']];
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $data           = Db::table('category')->where($where)->get()->toArray();
        $result['data'] = Arr::reorder($data, 'sort', 'SORT_ASC');
        unset($data);
        return $this->response->json($result);
    }


    /**
     * @DOC 接口类型
     */
    #[RequestMapping(path: 'base/config/interface', methods: 'post')]
    public function interface(RequestInterface $request)
    {
        $param   = $request->all();
        $where[] = ['status', '=', 1];
        $where[] = ['pid', '=', 1680];
        if (Arr::hasArr($param, 'cfg_id')) $where[] = ['cfg_id', '=', $param['cfg_id']];
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $data           = Db::table('category')->where($where)->get()->toArray();
        $result['data'] = Arr::reorder($data, 'sort', 'SORT_ASC');
        unset($data);
        return $this->response->json($result);
    }


    /**
     * @DOC 进出口方式
     */
    #[RequestMapping(path: 'base/config/export', methods: 'post')]
    public function export(RequestInterface $request)
    {
        $param   = $request->all();
        $where[] = ['status', '=', 1];
        $where[] = ['pid', '=', 1660];
        if (Arr::hasArr($param, 'cfg_id')) $where[] = ['cfg_id', '=', $param['cfg_id']];
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $data           = Db::table('category')->where($where)->get()->toArray();
        $result['data'] = Arr::reorder($data, 'sort', 'SORT_ASC');
        unset($data);
        return $this->response->json($result);
    }

    /**
     * @DOC 平台类型
     */
    #[RequestMapping(path: 'base/config/platform', methods: 'post')]
    public function platform(RequestInterface $request)
    {
        $param   = $request->all();
        $where[] = ['status', '=', 1];
        $where[] = ['pid', '=', 1625];
        if (Arr::hasArr($param, 'cfg_id')) $where[] = ['cfg_id', '=', $param['cfg_id']];
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $data           = Db::table('category')->where($where)->get()->toArray();
        $result['data'] = Arr::reorder($data, 'sort', 'SORT_ASC');
        unset($data);
        return $this->response->json($result);
    }

    /**
     * @DOC 认证要素
     */
    #[RequestMapping(path: 'base/config/element', methods: 'post')]
    public function element(RequestInterface $request)
    {
        $param   = $request->all();
        $where[] = ['status', '=', 1];
        $where[] = ['pid', '=', 1700];
        if (Arr::hasArr($param, 'cfg_id')) $where[] = ['cfg_id', '=', $param['cfg_id']];
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $data           = Db::table('category')->where($where)->get()->toArray();
        $result['data'] = Arr::reorder($data, 'sort', 'SORT_ASC');
        unset($data);
        return $this->response->json($result);
    }

    /**
     * @DOC 检验检疫类别
     */
    #[RequestMapping(path: 'base/config/ciq', methods: 'post')]
    public function ciq(RequestInterface $request)
    {
        $param   = $request->all();
        $where[] = ['status', '=', 1];
        $where[] = ['pid', '=', 1760];
        if (Arr::hasArr($param, 'cfg_id')) $where[] = ['cfg_id', '=', $param['cfg_id']];
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $data           = Db::table('category')->where($where)
            ->select(['cfg_id', 'title', 'code', 'sort'])->get()->toArray();
        $result['data'] = Arr::reorder($data, 'sort', 'SORT_ASC');
        unset($data);
        return $this->response->json($result);
    }

    /**
     * @DOC 监管条件
     */
    #[RequestMapping(path: 'base/config/condition', methods: 'post')]
    public function sup_condition(RequestInterface $request)
    {
        $param   = $request->all();
        $where[] = ['status', '=', 1];
        $where[] = ['pid', '=', 1720];
        if (Arr::hasArr($param, 'cfg_id')) $where[] = ['cfg_id', '=', $param['cfg_id']];
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $data           = Db::table('category')->where($where)
            ->select(['cfg_id', 'title', 'code', 'sort'])->get()->toArray();
        $result['data'] = Arr::reorder($data, 'sort', 'SORT_ASC');
        unset($data);
        return $this->response->json($result);
    }


    /**
     * @DOC 单位管理
     */
    #[RequestMapping(path: 'base/config/unit', methods: 'post')]
    public function unit(RequestInterface $request)
    {
        $param   = $request->all();
        $where[] = ['status', '=', 1];
        $where[] = ['pid', '=', 89];
        if (Arr::hasArr($param, 'cfg_id')) $where[] = ['cfg_id', '=', $param['cfg_id']];
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $data           = Db::table('category')->where($where)
            ->select(['cfg_id', 'title', 'code', 'sort'])->get()->toArray();
        $result['data'] = Arr::reorder($data, 'sort', 'SORT_ASC');
        unset($data);
        return $this->response->json($result);
    }

}
