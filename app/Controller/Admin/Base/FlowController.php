<?php

namespace App\Controller\Admin\Base;

use App\Common\Lib\Arr;
use App\Controller\Admin\AdminBaseController;
use App\Exception\HomeException;
use App\Model\AdminLogModel;
use App\Model\FlowModel;
use App\Request\LibValidation;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;

#[Controller(prefix: '/', server: 'httpAdmin')]
class FlowController extends AdminBaseController
{
    protected string $target_table = 'flow';

    /**
     * @DOC 流程管理列表
     */
    #[RequestMapping(path: 'base/flow/lists', methods: 'post')]
    public function lists(RequestInterface $request)
    {
        $param = $request->all();

        $where[] = ['uid', '=', 0];
        $where[] = ['delete_time', '=', 0];

        if (Arr::hasArr($param, 'start_time')) {
            $where[] = ['add_time', '>=', $param['start_time']];
        }
        if (Arr::hasArr($param, 'end_time')) {
            $where[] = ['add_time', '<=', $param['end_time']];
        }
        if (Arr::hasArr($param, 'keyword')) {
            $where[] = ['flow_name', 'like', '%' . $param['keyword'] . '%'];
        }
        $data = FlowModel::where($where)
            ->paginate($param['limit'] ?? 20);

        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = [
            'total' => $data->total(),
            'data'  => $data->items(),
        ];
        return $this->response->json($result);
    }


    /**
     * @DOC 修改状态
     */
    #[RequestMapping(path: 'base/flow/status', methods: 'post')]
    public function handleStatus(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'flow_id'   => ['required'],
                'status'    => ['required', Rule::in([0, 1])],
                'flow_name' => ['required'],
            ], [
                'flow_id.required'   => '流程不存在',
                'status.required'    => '状态必填',
                'status.in'          => '状态错误',
                'flow_name.required' => '名称必填',
            ]);

        $where['flow_id'] = $param['flow_id'];
        $where['uid']     = 0;
        $flowData         = FlowModel::where($where)->first();
        if (empty($flowData)) {
            throw new HomeException('禁止修改状态：当前流程不存在', 201);
        }
        $flowData = $flowData->toArray();
        if ($flowData['flow_name'] != $param['flow_name']) {
            throw new HomeException('禁止修改状态：当前数据不匹配', 201);
        }
        if ($flowData['lock']) {
            throw new HomeException('禁止修改状态：流程已锁定', 201);
        }
        if (FlowModel::where($where)->update(['status' => $param['status']])) {
            $result['code']               = 200;
            $result['msg']                = '处理成功';
            $log_data['admin_uid']        = $request->UserInfo['uid'];
            $log_data['target_table']     = $this->target_table;
            $log_data['target_table_id']  = $flowData['flow_id'];
            $log_data['target_table_val'] = $param['status'];
            $log_data['add_time']         = time();
            $log_data['log_info']         = $request->UserInfo['user_name'] . '调整状态为：' . $param['status'];
            AdminLogModel::insert($log_data);
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 查看每个流程的日志
     */
    #[RequestMapping(path: 'base/flow/log', methods: 'post')]
    public function log(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $param          = $LibValidation->validate($params,
            [
                'flow_id' => ['required'],
            ], [
                'flow_id.required' => '流程不存在',
            ]);

        $where['target_table']    = $this->target_table;
        $where['target_table_id'] = $param['flow_id'];

        $data = AdminLogModel::where($where)->get()->toArray();

        $result['code'] = 200;
        $result['msg']  = '查询成功';
        $result['data'] = $data;
        return $this->response->json($result);
    }

    /**
     * @DOC 锁定
     */
    #[RequestMapping(path: 'base/flow/lock', methods: 'post')]
    public function handleLock(RequestInterface $request)
    {
        $result['code']   = 201;
        $result['msg']    = '处理失败';
        $params           = $request->all();
        $LibValidation    = \Hyperf\Support\make(LibValidation::class);
        $param            = $LibValidation->validate($params,
            [
                'flow_id' => ['required'],
                'status'  => ['required', Rule::in([0, 1])],
                'lock'    => ['required'],
            ], [
                'flow_id.required' => '流程不存在',
                'status.required'  => '状态必填',
                'status.in'        => '状态错误',
                'lock.required'    => '锁定值错误',
            ]);
        $where['flow_id'] = $param['flow_id'];
        $where['uid']     = 0;
        $flowData         = FlowModel::where($where)->first();
        if (empty($flowData)) {
            throw new HomeException('禁止修改状态：当前流程不存在');
        }
        $flowData = $flowData->toArray();
        if ($flowData['status'] != $param['status']) {
            throw new HomeException('禁止修改状态：当前数据不匹配');
        }

        if (FlowModel::where($where)->update(['lock' => $param['lock']])) {
            $result['code']               = 200;
            $result['msg']                = '处理成功';
            $log_data['admin_uid']        = $request->UserInfo['uid'];
            $log_data['target_table']     = $this->target_table;
            $log_data['target_table_id']  = $flowData['flow_id'];
            $log_data['target_table_val'] = $param['lock'];
            $log_data['add_time']         = time();
            $log_data['log_info']         = $request->UserInfo['user_name'] . '调整锁定值为：' . $param['lock'];
            AdminLogModel::insert($log_data);
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 删除
     */
    #[RequestMapping(path: 'base/flow/del', methods: 'post')]
    public function handleDel(RequestInterface $request)
    {
        $result['code'] = 201;
        $params         = $request->all();

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'flow_id' => ['required'],
            ], [
                'flow_id.required' => '流程不存在',
            ]);

        $where['flow_id'] = $param['flow_id'];
        $where['uid']     = 0;
        $flowData         = FlowModel::where($where)->first();
        if (empty($flowData)) {
            throw new HomeException('禁止删除：当前流程不存在');
        }
        $flowData = $flowData->toArray();
        if ($flowData['lock'] == 1) {
            throw new HomeException('禁止删除：当前流程已锁定、禁止删除');
        }
        if (FlowModel::where('flow_id', $param['flow_id'])->update(['delete_time' => time()])) {
            $result['code'] = 200;
            $result['msg']  = '删除成功';

            $log_data['admin_uid']       = $request->UserInfo['uid'];
            $log_data['target_table']    = $this->target_table;
            $log_data['target_table_id'] = $flowData['flow_id'];
            $log_data['add_time']        = time();
            $log_data['log_info']        = $request->UserInfo['user_name'] . '删除操作';
            AdminLogModel::insert($log_data);
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 添加审核流程
     */
    #[RequestMapping(path: 'base/flow/add', methods: 'post')]
    public function handleAdd(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $param          = $LibValidation->validate($params,
            [
                'flow_name' => ['required'],
                'info'      => ['required'],
                'flow_node' => ['required'],
                'status'    => ['required'],
            ], [
                'flow_name.required' => '流程名称必填',
                'info.required'      => '流程配置必填',
                'flow_node.required' => 'node错误',
                'status.required'    => '状态错误',
            ]);
        //验证输入的第二级数据
        $time              = time();
        $flow['flow_name'] = $param['flow_name'];
        $flow['uid']       = 0;//后台管理员使用的时候 为0
        $flow['child_uid'] = 0;//后台管理员使用的时候 为0
        $flow['add_time']  = $time;
        $flow['status']    = $param['status'];
        $flow['info']      = $param['info'];
        $flow['author']    = $request->UserInfo['user_name'];
        foreach ($param['flow_node'] as $nodes => $node) {
            if (Arr::hasArr($node, 'reviewer')) $node['admin_reviewer'] = $node['reviewer'];
            $LibValidation->validate($node,
                [
                    'node_name'   => ['required'],
                    'role_id'     => ['required'],
                    'node_status' => ['required'],
                    'layer'       => ['required'],
                    'must_reply'  => ['required'],
                ], [
                    'node_name.required'  => '节点名称必填',
                    'role_id.required'    => '角色必填',
                    'node_status.array'   => '状态错误',
                    'layer.required'      => '审核层级错误',
                    'must_reply.required' => '理由必填',
                ]);
        }
        //开始数据保存
        $where['flow_name'] = $flow['flow_name'];
        $where['uid']       = 0;// 总平台的审核流程
        $flowData           = Db::table('flow')->where($where)->exists();
        if (!empty($flowData)) {
            throw new HomeException('当前流程已存在');
        }
        Db::beginTransaction();
        try {
            $flow_id = Db::table('flow')->insertGetId($flow);
            foreach ($param['flow_node'] as $nodes => $node) {
                $admin_reviewer = $node['reviewer'];
                unset($node['reviewer']);
                $node['flow_id'] = $flow_id;
                $node_id         = Db::table('flow_node')->insertGetId($node);
                $admin_reviewer  = explode(',', $admin_reviewer);
                $reviewer        = [];
                foreach ($admin_reviewer as $key => $val) {
                    $reviewer[$key]['uid']     = $val;
                    $reviewer[$key]['flow_id'] = $flow_id;
                    $reviewer[$key]['node_id'] = $node_id;
                }
                Db::table('flow_node_reviewer')->insert($reviewer);
            }
            // 提交事务
            Db::commit();
            $result['code'] = 200;
            $result['msg']  = '添加成功';
        } catch (\Exception $e) {
            // 回滚事务
            $result['msg'] = $e->getMessage();
            Db::rollback();
        }
        if ($result['code'] == 200) {
            $log_data['admin_uid']       = $request->UserInfo['uid'];
            $log_data['target_table']    = $this->target_table;
            $log_data['target_table_id'] = $flow_id;
            $log_data['add_time']        = time();
            $log_data['log_info']        = $request->UserInfo['user_name'] . '添加流程';
            AdminLogModel::insert($log_data);
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 编辑线路
     */
    #[RequestMapping(path: 'base/flow/edit', methods: 'post')]
    public function handleEdit(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $param          = $LibValidation->validate($params,
            [
                'flow_id'   => ['required'],
                'flow_name' => ['required'],
                'info'      => ['required'],
                'flow_node' => ['required'],
                'status'    => ['required'],
            ], [
                'flow_id.required'   => '流程不存在',
                'flow_name.required' => '流程名称必填',
                'info.required'      => '流程配置必填',
                'flow_node.required' => 'node错误',
                'status.required'    => '状态错误',
            ]);

        $where['flow_id'] = $param['flow_id'];// 总平台的审核流程ID
        $where['uid']     = 0;// 总平台的审核流程
        $flowData         = FlowModel::where($where)->first();
        if (empty($flowData) || (Arr::hasArr($flowData, 'flow_name') && $param['flow_name'] !== $flowData['flow_name'])) {
            throw new HomeException('当前流程不存在');
        }

        if ($flowData['lock'] == 1) {
            throw new HomeException('流程已锁定：禁止修改');
        }

        foreach ($param['flow_node'] as $nodes => $node) {
            if (Arr::hasArr($node, 'reviewer')) $node['admin_reviewer'] = $node['reviewer'];
            $LibValidation->validate($node,
                [
                    'node_name'   => ['required'],
                    'role_id'     => ['required'],
                    'node_status' => ['required'],
                    'layer'       => ['required'],
                    'must_reply'  => ['required'],
                ], [
                    'node_name.required'  => '节点名称必填',
                    'role_id.required'    => '角色必填',
                    'node_status.array'   => '状态错误',
                    'layer.required'      => '审核层级错误',
                    'must_reply.required' => '理由必填',
                ]);
        }
        $flow_id           = $flowData['flow_id'];
        $flow['flow_name'] = $param['flow_name'];
        $flow['uid']       = 0;//后台管理员使用的时候 为0
        $flow['child_uid'] = 0;//后台管理员使用的时候 为0
        $flow['status']    = $param['status'];
        $flow['info']      = $param['info'];
        Db::beginTransaction();
        try {
            Db::table('flow')->where($where)->update($flow);
            unset($where);
            $where['flow_id'] = $param['flow_id'];// 总平台的审核流程ID
            //删除两个辅助表里内容
            Db::table('flow_node')->where($where)->delete();
            Db::table('flow_node_reviewer')->where($where)->delete();

            foreach ($param['flow_node'] as $nodes => $node) {
                $admin_reviewer = $node['reviewer'];
                unset($node['reviewer']);
                $node['flow_id'] = $flow_id;
                $node_id         = Db::table('flow_node')->insertGetId($node);
                $admin_reviewer  = explode(',', $admin_reviewer);
                $reviewer        = [];
                foreach ($admin_reviewer as $key => $val) {
                    $reviewer[$key]['uid']     = $val;
                    $reviewer[$key]['flow_id'] = $flow_id;
                    $reviewer[$key]['node_id'] = $node_id;
                }
                Db::table('flow_node_reviewer')->insert($reviewer);
            }
            // 提交事务
            Db::commit();
            $result['code'] = 200;
            $result['msg']  = '编辑成功';
        } catch (\Exception $e) {
            // 回滚事务
            $result['msg'] = $e->getMessage();
            Db::rollback();
        }
        if ($result['code'] == 200) {
            $log_data['admin_uid']       = $request->UserInfo['uid'];
            $log_data['target_table']    = $this->target_table;
            $log_data['target_table_id'] = $flow_id;
            $log_data['add_time']        = time();
            $log_data['log_info']        = $request->UserInfo['user_name'] . '编辑流程';
            AdminLogModel::insert($log_data);
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 流程详情
     */
    #[RequestMapping(path: 'base/flow/info', methods: 'post')]
    public function info(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '获取失败';
        $params         = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $param          = $LibValidation->validate($params,
            [
                'flow_id' => ['required'],
            ], [
                'flow_id.required' => '流程不存在',
            ]);

        $where['flow_id'] = $param['flow_id'];

        $data = FlowModel::with(
            [
                'node' => function ($query) {
                    $query->orderBy('layer');
                }, 'node.reviewer', 'node.reviewer.user'
            ]
        )->where($where)->first();

//        $data['node']   = Arr::reorder($data['node'], 'layer', 'SORT_ASC');
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = $data;
        return $this->response->json($result);
    }
}
