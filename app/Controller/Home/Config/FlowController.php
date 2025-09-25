<?php

namespace App\Controller\Home\Config;

use App\Common\Lib\Arr;
use App\Common\Lib\LogLib;
use App\Common\Lib\UserDefinedIdGenerator;
use App\Controller\Home\AbstractController;
use App\Exception\HomeException;
use App\Model\FlowModel;
use App\Model\MemberThirdConfigureModel;
use App\Model\ThirdConfigureModel;
use App\Request\FlowRequest;
use App\Request\LibValidation;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: "config/flow")]
class FlowController extends AbstractController
{

    /**
     *禁止加盟商配置的流程
     * third_id=125 授信审核
     * @var array
     */
    protected array $joinNotThird = [
        125
    ];

    /**
     * @DOC 需要配置的流程
     * @Name   need
     * @Author wangfei
     * @date   2023/11/8 2023
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: 'need', methods: 'get,post')]
    public function need(RequestInterface $request): ResponseInterface
    {
        $result['code'] = 200;
        $result['msg']  = '查询成功';
        $member         = $this->request->UserInfo;

        // 判断代理 与 加盟商所获取的数据
        $whereIn = match ($member['role_id']) {
            1, 2 => ['flow_price_template', 'flow_after_pay'],
            3 => ['flow_price_template'],
            default => [],
        };

        $data = ThirdConfigureModel::query()
            ->where('pid', '=', 123)
            ->whereIn('third_code', $whereIn)
            ->with(['member_third' => function ($query) use ($member) {
                $query->with(['field'])->where('member_uid', '=', $member['uid']);
            }])
            ->select(['third_id', 'third_code', 'third_name'])
            ->get()->toArray();

        foreach ($data as $key => $value) {
            $data[$key]['flow'] = [];
            if (Arr::hasArr($value, 'member_third')) {
                $member_third = $value['member_third'];
                foreach ($member_third['field'] as $k => $v) {
                    if ($v['field'] == 'flow_id') {
                        $flow_id = $v['field_value'];
                        $flow    = FlowModel::query()->where('flow_id', '=', $flow_id)->select(['flow_id', 'flow_name'])->first();
                        if (!empty($flow)) {
                            $data[$key]['flow'] = $flow;
                        }
                    }
                }
            }
            unset($data[$key]['member_third']);
        }
        $result['data'] = $data;
        return $this->response->json($result);
    }

    /**
     * @DOC 流程配置
     * @Name   cfg
     * @Author wangfei
     * @date   2023/11/8 2023
     * @param RequestInterface $request
     * @return ResponseInterface
     */

    #[RequestMapping(path: 'cfg', methods: 'get,post')]
    public function cfg(RequestInterface $request): ResponseInterface
    {
        $result['code']   = 201;
        $result['msg']    = '配置失败';
        $params           = $request->all();
        $member           = $this->request->UserInfo;
        $ThirdConfigureDb = ThirdConfigureModel::query()
            ->with(['field' => function ($query) {
                $query->select(['field_id', 'third_id', 'field', 'default_value', 'field_type', 'info']);
            }])
            ->where('pid', '=', 123)->get()->toArray();
        $third_idArr      = array_column($ThirdConfigureDb, 'third_id');
        $LibValidation    = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params           = $LibValidation->validate($params, rules: [
            "third_id" => ['required', 'integer', Rule::in($third_idArr)],
            'flow_id'  => ['required', 'string', Rule::exists('flow')->where(function ($query) use ($params, $member) {
                $query->where('uid', '=', $member['uid'])->where('flow_id', '=', $params['flow_id']);
            })],
        ]);
        switch ($member['role_id']) {
            case 1:
                break;
            default:
                if (in_array($params['third_id'], $this->joinNotThird)) {
                    throw new HomeException('非平台代理、禁止配置此流程');
                }
                break;
        }

        $ThirdConfigureDb = array_column($ThirdConfigureDb, null, 'third_id');

        $ThirdDb                   = $ThirdConfigureDb[$params['third_id']];
        $ThirdConfigureInsert      = $ThirdConfigureUpdate = [];
        $memberThird['third_id']   = $params['third_id'];
        $memberThird['third_code'] = $ThirdDb['third_code'];
        $memberThird['status']     = 1;
        $memberThird['third_name'] = $ThirdDb['third_name'];
        $memberThird['member_uid'] = $member['uid'];
        $memberThirdConfigureDb    = MemberThirdConfigureModel::query()
            ->with(['field'])
            ->where('third_id', '=', $ThirdDb['third_id'])
            ->where('member_uid', '=', $member['uid'])
            ->first();
        if (empty($memberThirdConfigureDb)) {
            $ThirdConfigureInsert             = $memberThird;
            $ThirdConfigureInsert['add_time'] = time();
        } else {
            $memberThirdConfigureDb                  = $memberThirdConfigureDb->toArray();
            $member_third_id                         = $memberThirdConfigureDb['member_third_id'];
            $ThirdConfigureUpdate                    = $memberThird;
            $ThirdConfigureUpdate['member_third_id'] = $member_third_id;
        }
        $third_configure_item = [];
        Db::beginTransaction();
        try {
            if (!empty($ThirdConfigureInsert)) {
                $member_third_id = Db::table('member_third_configure')->insertGetId($ThirdConfigureInsert);
            }
            if (!empty($ThirdConfigureUpdate)) {
                Db::table('member_third_configure')->where('member_third_id', '=', $member_third_id)
                    ->update($ThirdConfigureUpdate);
                Db::table('member_third_configure_item')->where('member_third_id', '=', $member_third_id)
                    ->where('member_uid', '=', $member['uid'])
                    ->delete();
            }
            foreach ($ThirdDb['field'] as $key => $value) {
                $third_configure_item[$key]['member_third_id'] = $member_third_id;
                $third_configure_item[$key]['field']           = $value['field'];
                $third_configure_item[$key]['field_value']     = $value['default_value'];
                $third_configure_item[$key]['member_uid']      = $member['uid'];
                if (Arr::hasArr($params, $value['field'])) {
                    $third_configure_item[$key]['field_value'] = $params[$value['field']];
                }
            }
            Db::table('member_third_configure_item')->insert($third_configure_item);
            // 提交事务
            Db::commit();
            $result['code'] = 200;
            $result['msg']  = '配置成功';
        } catch (\Exception $e) {
            // 回滚事务
            $result['msg'] = $e->getMessage();
            Db::rollback();
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 里侧列表 默认到index
     */
    #[RequestMapping(path: '', methods: 'post')]
    public function index(RequestInterface $request): ResponseInterface
    {
        $result['code'] = 200;
        $result['msg']  = '查询成功';
        $params         = $request->all();
        $member         = $this->request->UserInfo;
        $LibValidation  = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params         = $LibValidation->validate($params,
            [
                'page'       => ['required', 'integer'],
                'limit'      => ['required', 'integer'],
                'status'     => [Rule::in([0, 1])],
                'keyword'    => ['string'],
                'start_time' => ['string', 'date_format:Y-m-d H:i:s', 'required_with:end_time'],
                'end_time'   => ['string', 'date_format:Y-m-d H:i:s', 'required_with:start_time']
            ],
            [
                'start_time.required_with'    => '结束时间存在、开始时间必须填写',
                'required_with.required_with' => '开始时间存在、结束时间必须填写'
            ]
        );

        $query   = FlowModel::query();
        $where[] = ['uid', '=', $member['uid']];
        if (Arr::hasArr($params, ['start_time', 'end_time'])) {
            $where[] = ['add_time', '>=', strtotime($params['start_time'])];
            $where[] = ['add_time', '<=', strtotime($params['end_time'])];
        }
        if (Arr::hasArr($params, 'keyword')) {
            $where[] = ['flow_name', 'like', $params['keyword'] . '%'];
        }
        $paginate       = $query->where($where)->orderBy('add_time', 'desc')->paginate($params['limit'] ?? 20);
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = $paginate;
        return $this->response->json($result);
    }

    /**
     * @DOC
     * @Name  add
     * @Author wangfei
     * @date   2023/10/30 2023
     * @param RequestInterface $request
     * @return \think\response\Json
     */

    #[RequestMapping(path: 'add', methods: 'get,post')]
    public function add(RequestInterface $request): ResponseInterface
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();
        $member         = $this->request->UserInfo;

        $LibValidation     = \Hyperf\Support\make(LibValidation::class, [$member]);
        $FlowRequest       = \Hyperf\Support\make(FlowRequest::class);
        $FlowRequestResult = $FlowRequest->rules('add', params: $params, member: $member);
        $params            = $LibValidation->validate($params, rules: $FlowRequestResult['rules'], messages: $FlowRequestResult['messages']);

        $UserDefinedIdGenerator = \Hyperf\Support\make(UserDefinedIdGenerator::class);
        $flow_id                = (string)$UserDefinedIdGenerator->generate($member['parent_agent_uid']);
        $time                   = time();

        $flowInsertData['flow_id']   = $flow_id;
        $flowInsertData['flow_name'] = $params['flow_name'];
        $flowInsertData['uid']       = $member['uid'];//后台管理员使用的时候 为0,前用户使用的时候，区分用户
        $flowInsertData['child_uid'] = $member['child_uid'];//后台管理员使用的时候 为0,前台子账号添加的时候，添加为子账号的ID
        $flowInsertData['add_time']  = $time;
        $flowInsertData['status']    = $params['status'];
        $flowInsertData['info']      = $params['info'];
        $flowInsertData['author']    = $member['user_name'];

        $nodeInsertData     = [];
        $reviewerInsertData = [];
        foreach ($params['flow_node'] as $nodes => $node) {
            $admin_reviewer = $node['reviewer'];
            unset($node['reviewer']);
            $node_id          = (string)$UserDefinedIdGenerator->generate($member['parent_agent_uid']);
            $node['flow_id']  = $flow_id;
            $node['node_id']  = $node_id;
            $nodeInsertData[] = $node;
            $admin_reviewer   = explode(',', $admin_reviewer);
            $reviewer         = [];
            foreach ($admin_reviewer as $key => $child_uid) {
                $reviewer['flow_id']   = $flow_id;
                $reviewer['node_id']   = $node_id;
                $reviewer['uid']       = $member['uid'];
                $reviewer['child_uid'] = $child_uid;
                $reviewerInsertData[]  = $reviewer;
            }
        }
        Db::beginTransaction();
        try {
            Db::table('flow')->insert($flowInsertData);
            Db::table('flow_node')->insert($nodeInsertData);
            Db::table('flow_node_reviewer')->insert($reviewerInsertData);
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
            LogLib::table('home_log')->targetTable('flow')->data([
                'member_uid' => $member['uid'],
                'table_id'   => $flow_id,
                'add_time'   => time()
            ])->write($member['user_name'] . '添加了流程');
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 编辑
     * @Name   edit
     * @Author wangfei
     * @date   2023/10/30 2023
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: 'edit', methods: 'get,post')]
    public function edit(RequestInterface $request): ResponseInterface
    {
        $result['code']              = 201;
        $result['msg']               = '处理失败';
        $params                      = $request->all();
        $member                      = $this->request->UserInfo;
        $LibValidation               = \Hyperf\Support\make(LibValidation::class, [$member]);
        $FlowRequest                 = \Hyperf\Support\make(FlowRequest::class);
        $FlowRequestResult           = $FlowRequest->rules('edit', params: $params, member: $member);
        $params                      = $LibValidation->validate($params, rules: $FlowRequestResult['rules'], messages: $FlowRequestResult['messages']);
        $UserDefinedIdGenerator      = \Hyperf\Support\make(UserDefinedIdGenerator::class);
        $flow_id                     = $params['flow_id'];
        $flowUpdateData['flow_name'] = $params['flow_name'];
        $flowUpdateData['child_uid'] = 0;//后台管理员使用的时候 为0
        $flowUpdateData['status']    = $params['status'];
        $flowUpdateData['info']      = $params['info'];

        $nodeInsertData     = [];
        $reviewerInsertData = [];
        foreach ($params['flow_node'] as $nodes => $node) {
            $admin_reviewer = $node['reviewer'];
            unset($node['reviewer']);
            $node_id          = (string)$UserDefinedIdGenerator->generate($member['parent_agent_uid']);
            $node['flow_id']  = $flow_id;
            $node['node_id']  = $node_id;
            $nodeInsertData[] = $node;
            $admin_reviewer   = explode(',', $admin_reviewer);
            $reviewer         = [];
            foreach ($admin_reviewer as $key => $child_uid) {
                $reviewer['flow_id']   = $flow_id;
                $reviewer['node_id']   = $node_id;
                $reviewer['uid']       = $member['uid'];
                $reviewer['child_uid'] = $child_uid;
                $reviewerInsertData[]  = $reviewer;
            }
        }
        Db::beginTransaction();
        try {
            Db::table('flow')->where('flow_id', '=', $flow_id)->update($flowUpdateData);
            Db::table('flow_node')->where('flow_id', '=', $flow_id)->delete();
            Db::table('flow_node_reviewer')->where('flow_id', '=', $flow_id)->delete();
            Db::table('flow_node')->insert($nodeInsertData);
            Db::table('flow_node_reviewer')->insert($reviewerInsertData);
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
            LogLib::table('home_log')->targetTable('flow')->data([
                'member_uid' => $member['uid'],
                'table_id'   => $params['flow_id'],
                'add_time'   => time()
            ])->write($member['user_name'] . '修改了流程');
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 修改状态
     * @Name   status
     * @Author wangfei
     * @date   2023/10/30 2023
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: 'status', methods: 'get,post')]
    public function status(RequestInterface $request): ResponseInterface
    {
        $result['code']    = 201;
        $result['msg']     = '处理失败';
        $params            = $request->all();
        $member            = $this->request->UserInfo;
        $LibValidation     = \Hyperf\Support\make(LibValidation::class, [$member]);
        $FlowRequest       = \Hyperf\Support\make(FlowRequest::class);
        $FlowRequestResult = $FlowRequest->rules('status', params: $params, member: $member);
        $params            = $LibValidation->validate($params, rules: $FlowRequestResult['rules'], messages: $FlowRequestResult['messages']);
        if (Db::table("flow")->where('flow_id', '=', $params['flow_id'])->update(['status' => $params['status']])) {
            $result['code'] = 200;
            $result['msg']  = '处理成功';
            LogLib::table('home_log')->targetTable('flow')->data([
                'member_uid'       => $member['uid'],
                'table_id'         => $params['flow_id'],
                'table_after_data' => $params['status'],
                'add_time'         => time()
            ])->write($member['user_name'] . '修改了流程状态：' . $params['status']);
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 锁定流程
     * @Name   lock
     * @Author wangfei
     * @date   2023/10/30 2023
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: 'lock', methods: 'get,post')]
    public function lock(RequestInterface $request): ResponseInterface
    {
        $result['code']    = 201;
        $result['msg']     = '处理失败';
        $params            = $request->all();
        $member            = $this->request->UserInfo;
        $LibValidation     = \Hyperf\Support\make(LibValidation::class, [$member]);
        $FlowRequest       = \Hyperf\Support\make(FlowRequest::class);
        $FlowRequestResult = $FlowRequest->rules('lock', params: $params, member: $member);
        $params            = $LibValidation->validate($params, rules: $FlowRequestResult['rules'], messages: $FlowRequestResult['messages']);
        if (Db::table("flow")->where('flow_id', '=', $params['flow_id'])->update(['lock' => $params['lock']])) {
            $result['code'] = 200;
            $result['msg']  = '处理成功';
            LogLib::table('home_log')->targetTable('flow')->data([
                'member_uid'       => $member['uid'],
                'table_id'         => $params['flow_id'],
                'table_after_data' => $params['lock'],
                'add_time'         => time()
            ])->write($member['user_name'] . '调整锁定值为：' . $params['lock']);
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 删除流程
     * @Name   del
     * @Author wangfei
     * @date   2023/10/30 2023
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: 'del', methods: 'get,post')]
    public function del(RequestInterface $request): ResponseInterface
    {
        $result['code']    = 201;
        $result['msg']     = '处理失败';
        $params            = $request->all();
        $member            = $this->request->UserInfo;
        $LibValidation     = \Hyperf\Support\make(LibValidation::class, [$member]);
        $FlowRequest       = \Hyperf\Support\make(FlowRequest::class);
        $FlowRequestResult = $FlowRequest->rules('del', params: $params, member: $member);
        $params            = $LibValidation->validate($params, rules: $FlowRequestResult['rules'], messages: $FlowRequestResult['messages']);
        if (Db::table("flow")->where('flow_id', '=', $params['flow_id'])->delete()) {
            $result['code'] = 200;
            $result['msg']  = '处理成功';
            LogLib::table('home_log')->targetTable('flow')->data([
                'member_uid' => $member['uid'],
                'table_id'   => $params['flow_id'],
                'add_time'   => time()
            ])->write($member['user_name'] . '删除了流程');

        }
        return $this->response->json($result);
    }

    /**
     * @DOC  流程详情
     * @Name   details
     * @Author wangfei
     * @date   2023/10/30 2023
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: 'details', methods: 'get,post')]
    public function details(RequestInterface $request): ResponseInterface
    {
        $result['code']    = 200;
        $result['msg']     = '查询成功';
        $params            = $request->all();
        $member            = $this->request->UserInfo;
        $LibValidation     = \Hyperf\Support\make(LibValidation::class, [$member]);
        $FlowRequest       = \Hyperf\Support\make(FlowRequest::class);
        $FlowRequestResult = $FlowRequest->rules('del', params: $params, member: $member);
        $params            = $LibValidation->validate($params, rules: $FlowRequestResult['rules'], messages: $FlowRequestResult['messages']);

        $result['data'] = FlowModel::query()
            ->with(['node' => function ($query) {
                $query->with(['reviewer' => function ($query) {
                    $query->with(['member', 'member_child'])->select(['*']);
                }])->select(['*']);
            }])
            ->where('flow_id', '=', $params['flow_id'])->first();
        return $this->response->json($result);
    }

    #[RequestMapping(path: 'log', methods: 'get,post')]
    public function log(RequestInterface $request): ResponseInterface
    {
        $result['code']        = 200;
        $result['msg']         = '查询成功';
        $params                = $request->all();
        $member                = $this->request->UserInfo;
        $LibValidation         = \Hyperf\Support\make(LibValidation::class, [$member]);
        $FlowRequest           = \Hyperf\Support\make(FlowRequest::class);
        $FlowRequestResult     = $FlowRequest->rules('del', params: $params, member: $member);
        $params                = $LibValidation->validate($params, rules: $FlowRequestResult['rules'], messages: $FlowRequestResult['messages']);
        $where['target_table'] = 'flow';
        $where['table_id']     = $params['flow_id'];
        $data                  = LogLib::table('home_log')->where($where)->select();
        $result['data']        = $data['data'] ?? [];
        $result['total']       = $data['total'] ?? 0;

        return $this->response->json($result);
    }

}
