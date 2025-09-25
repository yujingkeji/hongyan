<?php

namespace App\Controller\Home\Member;

use App\Common\Lib\Arr;
use App\Common\Lib\TimeLib;
use App\Controller\Home\HomeBaseController;
use App\Exception\HomeException;
use App\Model\LineModel;
use App\Model\MemberLineModel;
use App\Request\LibValidation;
use App\Service\FlowService;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;


#[Controller(prefix: "member/line")]
class LineController extends HomeBaseController
{
    /**
     * @DOC 运营中的线路
     */
    #[RequestMapping(path: "index", methods: "post")]
    public function index(RequestInterface $request): ResponseInterface
    {
        $param   = $request->all();
        $member  = $request->UserInfo;
        $where[] = ['uid', '=', $member['parent_agent_uid']];
        if (Arr::hasArr($param, 'status')) {
            $where[] = ['status', '=', $param['status']];
        }
        $withWhere = [];
        if (Arr::hasArr($param, 'target_country_id')) {
            $withWhere[] = ['target_country_id', '=', $param['target_country_id']];
        }
        if (Arr::hasArr($param, 'send_country_id')) {
            $withWhere[] = ['send_country_id', '=', $param['send_country_id']];
        }
        $list = MemberLineModel::where($where)
            ->with(['line' => function ($query) {
                $query->with([
                    'send'   => function ($send) {
                        $send->select(['country_id', 'country_name', 'country_code', 'zip_code']);
                    },
                    'target' => function ($target) {
                        $target->select(['country_id', 'country_name', 'country_code', 'zip_code']);
                    },
                ])->select(['line_id', 'line_name', 'send_country_id', 'send_country', 'target_country_id', 'target_country', 'status']);
            }])
            ->whereHas('line', function ($query) use ($withWhere) {
                $query->where($withWhere);
            })
            ->select(['line_id', 'member_line_id', 'node_id', 'start_time', 'add_time', 'status', 'uid', 'update_time', 'end_time'])
            ->orderBy('add_time', 'desc')
            ->paginate($param['limit'] ?? 20);

        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => [
                'total' => $list->total(),
                'data'  => $list->items(),
            ]
        ]);
    }

    /**
     * @DOC 线路申请
     */
    #[RequestMapping(path: "apply", methods: "post")]
    public function apply(RequestInterface $request): ResponseInterface
    {
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($request->all(), [
            'send_country_id'   => ['required'],
            'target_country_id' => ['required'],
        ]);

        $param                      = $request->all();
        $where['send_country_id']   = $param['send_country_id'];
        $where['target_country_id'] = $param['target_country_id'];
        if ($member['role_id'] != 1) {
            throw new HomeException('只有平台代理才需要申请线路');
        }

        $data = LineModel::where($where)->with(
            [
                'send'   => function ($query) {
                    $query->select(['country_id', 'country_name', 'country_code']);
                },
                'target' => function ($query) {
                    $query->select(['country_id', 'country_name', 'country_code']);
                },
                'flow.node', 'flow.node.reviewer'
            ]
        )->first();

        if (empty($data)) {
            throw new HomeException('当前线路不存在、请联系管理员开通。');
        }

        unset($where);
        $where['line_id'] = $data['line_id'];
        $where['uid']     = $member['uid'];
        $memberLine       = MemberLineModel::where($where)->first();

        if (!empty($memberLine)) {
            throw new HomeException('当前线路已申请：若提交请等待审核。');
        }

        // 流程数据整理
        $time                = time();
        $afterTenDaytime     = TimeLib::daysAfter(10, $time);
        $apply['line_id']    = $data['line_id'];
        $apply['uid']        = $member['uid'];
        $apply['add_time']   = $time;
        $apply['start_time'] = Arr::hasArr($param, 'start_time') ? strtotime($param['start_time']) : TimeLib::daysAfter(1, $afterTenDaytime);
        $apply['end_time']   = TimeLib::yearAfter(1, $apply['start_time']);
        $apply['flow']       = json_encode($data['flow'], JSON_UNESCAPED_UNICODE);
        if (empty($data['flow'])) {
            throw new HomeException('线路未配置审核流程');
        }
        $data      = $data->toArray();
        $next      = FlowService::handle($data['flow'])->next();
        $nextNode  = [];
        $nextCheck = $next['check'];
        $time      = time();

        if (!Arr::hasArr($next, 'node')) {
            throw new HomeException('错误：当前线路申请流程为空、请联系客服');
        }
        if (Arr::hasArr($next, 'node')) $nextNode = $next['node'];
        $apply['node_id'] = Arr::hasArr($nextNode, 'node_id') ? $nextNode['node_id'] : 0;

        Db::beginTransaction();
        try {
            $member_line_id = Db::table('member_line')->insertGetId($apply);
            if (!empty($nextCheck)) {
                $add['line_id']        = $data['line_id'];
                $add['member_line_id'] = $member_line_id;
                $add['check_status']   = 0;
                $add['add_time']       = $time;
                $nextCheck             = Arr::pushArr($add, $nextCheck);
            }
            Db::table('member_line_check')->insert($nextCheck);
            Db::commit();
        } catch (\Throwable $e) {
            // 回滚事务
            Db::rollback();
            return $this->response->json(['code' => 201, 'msg' => '申请失败：' . $e->getMessage(), 'data' => []]);
        }
        return $this->response->json(['code' => 200, 'msg' => '申请成功：等待审核', 'data' => []]);
    }
}
