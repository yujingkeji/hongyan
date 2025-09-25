<?php

namespace App\Controller\Admin\Base;

use App\Common\Lib\Arr;
use App\Controller\Admin\AdminBaseController;
use App\Exception\HomeException;
use App\Model\CountryCodeModel;
use App\Model\LineModel;
use App\Model\MemberLineCheckModel;
use App\Model\MemberLineModel;
use App\Request\LibValidation;
use App\Service\FlowService;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

#[Controller(prefix: '/', server: 'httpAdmin')]
class LineController extends AdminBaseController
{

    /**
     * @DOC 线路管理列表
     */
    #[RequestMapping(path: 'base/line/lists', methods: 'post')]
    public function lists(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '获取失败';

        $param = $request->all();
        $where = [];
        if (Arr::hasArr($param, 'send_country_id')) {
            $where[] = ['send_country_id', '=', $param['send_country_id']];
        }
        if (Arr::hasArr($param, 'target_country_id')) {
            $where[] = ['target_country_id', '=', $param['target_country_id']];
        }
        if (Arr::hasArr($param, 'keyword')) {
            $where[] = ['line_name', 'like', '%' . $param['keyword'] . '%'];
        }
        $data = LineModel::where($where)->paginate($param['limit'] ?? 20);

        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = [
            'total' => $data->total(),
            'data'  => $data->items(),
        ];
        return $this->response->json($result);
    }

    /**
     * @DOC 线路管理新增
     */
    #[RequestMapping(path: 'base/line/add', methods: 'post')]
    public function add(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'line_name'         => ['required'],
                'send_country_id'   => ['required'],
                'flow_id'           => ['required'],
                'target_country_id' => ['required'],
                'status'            => ['required'],
            ], [
                'line_name.required'         => '线路名称必填',
                'send_country_id.required'   => '起运国必填',
                'target_country_id.required' => '送达国必填',
                'flow_id.required'           => '流程必填',
                'status.required'            => '状态必填',
            ]);

        $lineWhere['send_country_id']   = $param['send_country_id'];
        $lineWhere['target_country_id'] = $param['target_country_id'];

        $line = LineModel::where($lineWhere)->first();
        if (!empty($line)) {
            throw new HomeException('禁止添加：当前线路已经存在', 201);
        }
        $sendCountry   = CountryCodeModel::where('country_id', $param['send_country_id'])
            ->where('status', '=', 1)
            ->first()->toArray();
        $targetCountry = CountryCodeModel::where('country_id', $param['target_country_id'])
            ->where('status', '=', 1)
            ->first()->toArray();

        $insert['line_name']         = $param['line_name'];
        $insert['send_country_id']   = $sendCountry['country_id'];
        $insert['target_country_id'] = $targetCountry['country_id'];
        $insert['send_country']      = $sendCountry['country_name'];
        $insert['target_country']    = $targetCountry['country_name'];
        $insert['flow_id']           = $param['flow_id'];
        $insert['status']            = $param['status'];
        $insert['add_time']          = time();
        if (LineModel::insert($insert)) {
            $result['code'] = 200;
            $result['msg']  = '添加成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 编辑线路
     */
    #[RequestMapping(path: 'base/line/edit', methods: 'post')]
    public function edit(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'line_id'           => ['required'],
                'line_name'         => ['required'],
                'send_country_id'   => ['required'],
                'flow_id'           => ['required'],
                'target_country_id' => ['required'],
                'status'            => ['required'],
            ], [
                'line_id.required'           => '线路不存在',
                'line_name.required'         => '线路名称必填',
                'send_country_id.required'   => '起运国必填',
                'target_country_id.required' => '送达国必填',
                'flow_id.required'           => '流程必填',
                'status.required'            => '状态必填',
            ]);

        $lineWhere['line_id'] = $param['line_id'];
        $line                 = LineModel::where($lineWhere)->first();
        if (empty($line)) {
            throw new HomeException('禁止编辑：当前线路不存在', 201);
        }

        if ($line['send_country_id'] != $param['send_country_id'] || $line['target_country_id'] != $param['target_country_id']) {
            throw new HomeException('禁止编辑：禁止修改发出、目的区域', 201);
        }
        $sendCountry                 = CountryCodeModel::where('country_id', $param['send_country_id'])
            ->where('status', '=', 1)
            ->first()->toArray();
        $targetCountry               = CountryCodeModel::where('country_id', $param['target_country_id'])
            ->where('status', '=', 1)
            ->first()->toArray();
        $update['line_name']         = $param['line_name'];
        $update['send_country_id']   = $sendCountry['country_id'];
        $update['target_country_id'] = $targetCountry['country_id'];
        $update['send_country']      = $sendCountry['country_name'];
        $update['target_country']    = $targetCountry['country_name'];
        $update['flow_id']           = $param['flow_id'];
        $update['status']            = $param['status'];
        if (LineModel::where($lineWhere)->update($update)) {
            $result['code'] = 200;
            $result['msg']  = '编辑成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 修改线路状态
     */
    #[RequestMapping(path: 'base/line/status', methods: 'post')]
    public function handleStatus(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'line_id'           => ['required'],
                'line_name'         => ['required'],
                'send_country_id'   => ['required'],
                'target_country_id' => ['required'],
                'status'            => ['required'],
            ], [
                'line_id.required'           => '线路不存在',
                'line_name.required'         => '线路名称不存在',
                'send_country_id.required'   => '起运国必填',
                'target_country_id.required' => '送达国必填',
                'status.required'            => '状态必填',
            ]);

        $lineWhere['line_id'] = $param['line_id'];
        $line                 = LineModel::where($lineWhere)->first();
        if (empty($line)) {
            throw new HomeException('禁止修改状态：当前线路不存在');
        }

        if ($line['send_country_id'] != $param['send_country_id'] || $line['target_country_id'] != $param['target_country_id']) {
            throw new HomeException('禁止修改状态：当前数据不匹配');
        }

        $sendCountry              = CountryCodeModel::where('country_id', $param['send_country_id'])
            ->where('status', '=', 1)
            ->first()->toArray();
        $targetCountry            = CountryCodeModel::where('country_id', $param['target_country_id'])
            ->where('status', '=', 1)
            ->first()->toArray();
        $update['line_name']      = $param['line_name'];
        $update['send_country']   = $sendCountry['country_name'];
        $update['target_country'] = $targetCountry['country_name'];
        $update['status']         = $param['status'];

        if (LineModel::where($lineWhere)->update($update)) {
            $result['code'] = 200;
            $result['msg']  = '修改成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 待审核列表
     */
    #[RequestMapping(path: 'base/line/review/lists', methods: 'post')]
    public function reviewLists(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '获取失败';
        $param          = $request->all();

        $where = [];
        if ($this->adminSysUID != $request->UserInfo['uid']) {
            $where[] = ['check_uid', '=', $request->UserInfo['uid']];
        }
        $data           = MemberLineCheckModel::with(['user', 'line', 'memberLine', 'memberLine.member'])
            ->where($where)->paginate($param['limit'] ?? 20)->toArray();
        $items          = $this->handleCheck($data['data']);
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = [
            'total' => $data['total'],
            'data'  => $items
        ];

        return $this->response->json($result);
    }

    protected function handleCheck(array $data)
    {
        $result = [];
        foreach ($data as $key => $val) {
            if (empty($val['user'])) {
                throw new HomeException('请检查线路的审核流程、审核人员数据缺失');
            }
            if (empty($val['line'])) {
                throw new HomeException('请检查线路的数据、线路数据缺失');
            }
            $Line                            = $val['line'];
            $handleLine['line_id']           = Arr::hasArr($Line, 'line_id') ? $Line['line_id'] : 0;
            $handleLine['line_name']         = Arr::hasArr($Line, 'line_name') ? $Line['line_name'] : '';
            $handleLine['send_country_id']   = Arr::hasArr($Line, 'send_country_id') ? $Line['send_country_id'] : '';
            $handleLine['send_country']      = Arr::hasArr($Line, 'send_country') ? $Line['send_country'] : '';
            $handleLine['target_country_id'] = Arr::hasArr($Line, 'target_country_id') ? $Line['target_country_id'] : 0;
            $handleLine['target_country']    = Arr::hasArr($Line, 'target_country') ? $Line['target_country'] : '';
            $result[$key]                    = $handleLine;
            $memberLine                      = $val['member_line'];
            $result[$key]['status']          = $memberLine['status']; //线路状态
            $result[$key]['uid']             = $memberLine['uid'];
            $result[$key]['user_name']       = $memberLine['member']['user_name'];
            $result[$key]['flow']            = $memberLine['flow'];
            /***************************************************/
            $data[$key]['check_name']      = $val['user']['user_name'];
            $data[$key]['check_real_name'] = $val['user']['real_name'];
            unset($data[$key]['line']);
            unset($data[$key]['memberLine']);
            unset($data[$key]['user']);
            $result[$key]['check'] = $data[$key];
        }
        return $result;
    }


    /**
     * @DOC 审核操作：同意
     */
    #[RequestMapping(path: 'base/line/review/agree', methods: 'post')]
    public function reviewAgree(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '获取失败';
        $params         = $request->all();
        $where          = [];

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'check_id'       => ['required'],
                'member_line_id' => ['required'],
                'line_id'        => ['required'],
                'info'           => ['nullable'],
            ], [
                'check_id.required'       => '审核信息必填',
                'member_line_id.required' => '用户线路信息必填',
                'line_id.required'        => '申请的线路信息必填',
            ]);

        if (Arr::hasArr($param, 'check_id')) $where[] = ['check_id', '=', $param['check_id']];
        if (Arr::hasArr($param, 'member_line_id')) $where[] = ['member_line_id', '=', $param['member_line_id']];
        if (Arr::hasArr($param, 'line_id')) $where[] = ['line_id', '=', $param['line_id']];

        $data = MemberLineCheckModel::with(['user', 'line', 'memberLine', 'memberLine.member'])->where($where)->first();
        if (empty($data)) {
            throw new HomeException('当前审核数据不存在。');
        }
        $data = $data->toArray();
        if ($data['check_uid'] !== $request->UserInfo['uid']) {
            throw new HomeException('错误：当前账号无审核该数据权限');
        }
        if ($data['check_status'] == 3) {
            throw new HomeException('错误：当前审核已由其他人审核完成。');
        }
        if ($data['check_status'] != 0) {
            throw new HomeException('错误：当前审核操作完成。');
        }
        if (!Arr::hasArr($data['member_line'], 'flow')) {
            throw new HomeException('错误：用户申请线路错误');
        }
        $flowData                = json_decode($data['member_line']['flow'], true);
        $prev['check_node_id']   = Arr::hasArr($data, 'check_node_id') ? $data['check_node_id'] : 0;
        $prev['check_uid']       = Arr::hasArr($data, 'check_uid') ? $data['check_uid'] : 0;
        $prev['check_child_uid'] = Arr::hasArr($data, 'check_child_uid') ? $data['check_child_uid'] : 0;
        $prev['reviewer_total']  = Arr::hasArr($data, 'reviewer_total') ? $data['reviewer_total'] : 0;

        $next = FlowService::handle($flowData)->member($this->request->UserInfo)->checkNode($prev['check_node_id'])->next();

        $time                  = time();
        $checkNode             = $next['checkNode'];
        $nextCheck             = $next['check'];
        $nextNode              = $next['node'];
        $memberLine['node_id'] = 0;

        if (!empty($nextCheck)) {
            $memberLine['node_id'] = $nextNode['node_id'];
            //$add['project_cfg_id'] = $this->project_cfg_id;
            $add['flow_id']      = $flowData['flow_id'];
            $add['check_id']     = $data['check_id'];
            $add['add_time']     = $time;
            $add['check_status'] = 0;
            $nextCheck           = Arr::pushArr($add, $nextCheck);
        }

        $currentCheck['check_id']       = $param['check_id'];
        $currentCheck['member_line_id'] = $data['member_line_id'];
        $currentCheck['check_info']     = Arr::hasArr($params, 'info') ? $params['info'] : '同意';
        $currentCheck['check_time']     = $time;
        $currentCheck['check_status']   = 1; //同意

        Db::beginTransaction();
        try {
            if (!empty($nextCheck)) {
                Db::table('member_line_check')->insert($nextCheck);
            } else {
                $memberLine['status'] = 1;
            }
            Db::table('member_line')
                ->where('member_line_id', '=', $currentCheck['member_line_id'])
                ->update($memberLine);
            Db::table('member_line_check')->where('check_id', '=', $currentCheck['check_id'])->update($currentCheck);
            //TODO 或审的，修改其他的状态为：3
            if (Arr::hasArr($checkNode, 'node_status') && $checkNode['node_status'] == 2) {
                Db::table('member_line_check')
                    ->where('member_line_id', '=', $currentCheck['member_line_id'])
                    ->where('check_node_id', '=', $checkNode['node_id'])
                    ->whereNotIn('check_id', [$currentCheck['check_id']])
                    ->update(['check_status' => 3]);
            }

            // 提交事务
            Db::commit();
            $result['code'] = 200;
            $result['msg']  = '审核成功';
        } catch (\Exception $e) {
            // 回滚事务
            $result['msg'] = $e->getMessage();
            Db::rollback();
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 拒绝
     */
    #[RequestMapping(path: 'base/line/review/refuse', methods: 'post')]
    public function reviewRefuse(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '获取失败';
        $params         = $request->all();

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'check_id'       => ['required'],
                'member_line_id' => ['required'],
                'line_id'        => ['required'],
                'info'           => ['nullable'],
            ], [
                'check_id.required'       => '审核信息必填',
                'member_line_id.required' => '用户线路信息必填',
                'line_id.required'        => '申请的线路信息必填',
            ]);

        if (Arr::hasArr($param, 'check_id')) $where[] = ['check_id', '=', $param['check_id']];
        if (Arr::hasArr($param, 'member_line_id')) $where[] = ['member_line_id', '=', $param['member_line_id']];
        if (Arr::hasArr($param, 'line_id')) $where[] = ['line_id', '=', $param['line_id']];
        $where[] = ['check_uid', '=', $request->UserInfo['uid']];
        $data    = MemberLineCheckModel::with(
            ['user', 'line', 'memberLine', 'memberLine.member']
        )->where($where)->first();
        if (empty($data)) {
            throw new HomeException('当前审核数据不存在。');
        }
        $data = $data->toArray();
        if ($data['check_status'] == 3) {
            throw new HomeException('错误：当前审核已由其他人审核完成。');
        }
        if ($data['check_status'] != 0) {
            throw new HomeException('错误：当前审核操作完成。');
        }

        if (!Arr::hasArr($data['member_line'], 'flow')) {
            throw new HomeException('错误：用户申请线路错误');
        }
        $flowData = json_decode($data['member_line']['flow'], true);

        $prev['check_node_id']   = Arr::hasArr($data, 'check_node_id') ? $data['check_node_id'] : 0;
        $prev['check_uid']       = Arr::hasArr($data, 'check_uid') ? $data['check_uid'] : 0;
        $prev['check_child_uid'] = Arr::hasArr($data, 'check_child_uid') ? $data['check_child_uid'] : 0;

        $time                           = time();
        $currentCheck['check_id']       = $param['check_id'];
        $currentCheck['member_line_id'] = $param['member_line_id'];
        $currentCheck['check_info']     = $params['info'] ?? '拒绝';
        $currentCheck['check_time']     = $time;
        $currentCheck['check_status']   = 2; //拒绝
        $next                           = FlowService::handle($flowData)->member($this->request->UserInfo)->checkNode($prev['check_node_id'])->next();
        $checkNode                      = $next['checkNode'];

        Db::beginTransaction();
        try {
            $memberLine['status'] = 2;
            Db::table('member_line')
                ->where('member_line_id', '=', $currentCheck['member_line_id'])
                ->update($memberLine);
            Db::table('member_line_check')->where('check_id', '=', $currentCheck['check_id'])->update($currentCheck);

            //TODO 或审的，修改其他的状态为：2 拒绝
            if (Arr::hasArr($checkNode, 'node_status') && $checkNode['node_status'] == 2) {
                Db::table('member_line_check')
                    ->where('member_line_id', '=', $currentCheck['member_line_id'])
                    ->where('check_node_id', '=', $checkNode['node_id'])
                    ->update(['check_status' => 2]);
            }

            // 提交事务
            Db::commit();
            $result['code'] = 200;
            $result['msg']  = '操作成功';
        } catch (\Exception $e) {
            // 回滚事务
            $result['msg'] = $e->getMessage();
            Db::rollback();
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 商家线路列表
     */
    #[RequestMapping(path: 'base/line/member/lists', methods: 'post')]
    public function memberLists(RequestInterface $request)
    {
        $param = $request->all();
        $where = [];
        if (Arr::hasArr($param, 'status', true)) $where[] = ['status', '=', $param['status']];
        if (Arr::hasArr($param, 'uid')) $where[] = ['uid', '=', $param['uid']];
        if (Arr::hasArr($param, 'line_id')) $where[] = ['line_id', '=', $param['line_id']];
        $data = MemberLineModel::with(
            [
                'line',
                'member' => function ($query) {
                    $query->select(['uid', 'user_name']);
                }
            ]
        )->where($where)
            ->select(['member_line_id', 'line_id', 'uid', 'add_time', 'update_time', 'start_time', 'end_time', 'node_id', 'status'])
            ->paginate($param['limit'] ?? 20);


        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = [
            'total' => $data->total(),
            'data'  => $data->items()
        ];

        return $this->response->json($result);
    }

    /**
     * @DOC 商家线路修改状态
     */
    #[RequestMapping(path: 'base/line/member/status', methods: 'post')]
    public function memberStatus(RequestInterface $request)

    {
        $result['code']       = 201;
        $result['msg']        = '处理失败';
        $param                = $request->all();
        $lineWhere['line_id'] = $param['line_id'];
        $line                 = LineModel::where($lineWhere)->first();
        if (empty($line)) {
            throw new HomeException('禁止修改状态：当前线路不存在');
        }
        $update['status'] = $param['status'];
        if (LineModel::where($lineWhere)->update($update)) {
            $result['code'] = 200;
            $result['msg']  = '修改成功';
        }
        return $this->response->json($result);
    }


}
