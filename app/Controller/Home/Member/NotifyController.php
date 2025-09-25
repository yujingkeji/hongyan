<?php

namespace App\Controller\Home\Member;

use App\Common\Lib\Arr;
use App\Controller\Home\AbstractController;
use App\Exception\HomeException;
use App\Model\AgentMemberModel;
use App\Model\NotifyModel;
use App\Model\NotifyReadModel;
use App\Request\LibValidation;
use App\Request\NotifyRequest;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;
use Psr\Http\Message\ResponseInterface;


#[Controller(prefix: "member/notify")]
class NotifyController extends AbstractController
{

    /**
     * @DOC 查找并消息通知列表
     */
    #[RequestMapping(path: "index", methods: "get,post")]
    public function index(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        if (Arr::hasArr($param, 'notify_id')) {
            $where[] = ['notify_id', '=', $param['notify_id']];
        }
        if (Arr::hasArr($param, 'add_time')) {
            $where[] = ['add_time', '>=', $param['add_time']];
        }
        if (Arr::hasArr($param, 'end_time')) {
            $where[] = ['add_time', '<=', $param['end_time']];
        }
        # 条件：已发送，不是本人信息，同一平台下
        $where[] = ['status', '=', 1];
        $where[] = ['member_uid', '!=', $member['uid']];
        $where[] = ['parent_agent_uid', '=', $member['parent_agent_uid']];
        # 根据角色获取上级信息
        $superior_uid = $this->roleWhere($member);
        # 查询 用户阅读表 记录信息 倒序排序
        $data        = NotifyModel::where($where)
            ->whereIn('member_uid', $superior_uid)
            ->with(['member' => function ($member) {
                $member->select(['uid', 'user_name']);
            }, 'read'        => function ($read) use ($member) {
                $read->where('member_uid', '=', $member['uid']);
            }, 'type'        => function ($type) {
                $type->select(['cfg_id', 'name']);
            }])
            ->where('receive_status', '=', 1)
            ->orWhere(function ($query) use ($member, $where) {
                $query->where('receive_status', '=', 0)->where($where)
                    ->whereHas('read', function ($read) use ($member) {
                        $read->where('member_uid', '=', $member['uid']);
                    });
            })
            ->select(['*', 'type as sort'])
            ->orderBy('sort', 'DESC')
            ->orderBy('add_time', 'DESC')
            ->paginate($param['limit'] ?? 20);
        $unreadCount = 0;
        if (isset($param['type']) && $param['type'] == 'home') {
            unset($where);
            $where        = [
                ['status', '=', 1],
                ['member_uid', '!=', $member['uid']],
                ['parent_agent_uid', '=', $member['parent_agent_uid']]
            ];
            $readWhere    = [
                ['member_uid', '=', $member['uid']],
                ['status', '=', 0],
            ];
            $superior_uid = $this->roleWhere($member);
            # 查询 用户未读记录
            $unreadCount = NotifyModel::query()
                ->where($where)
                ->whereIn('member_uid', $superior_uid)
                ->where(function ($query) use ($readWhere) {
                    $query->where('receive_status', 1)
                        ->whereDoesntHave('read')
                        ->orWhere(function ($query) use ($readWhere) {
                            $query->where('receive_status', 0)
                                ->whereHas('read', function ($read) use ($readWhere) {
                                    $read->where($readWhere);
                                });
                        });
                })->count();
        }
        return $this->response->json([
            'code' => 200,
            'msg'  => '查询成功',
            'data' => [
                'unread_count' => $unreadCount,
                'count'        => $data->total(),
                'list'         => $data->items(),
            ]
        ]);
    }

    /**
     * @DOC 查看自己推送的消息
     */
    #[RequestMapping(path: "pushIndex", methods: "get,post")]
    public function pushIndex(): ResponseInterface
    {
        $param   = $this->request->all();
        $where[] = ['member_uid', '=', $this->request->UserInfo['uid']];
        if (Arr::hasArr($param, 'status', true)) {
            $where[] = ['status', '=', $param['status']];
        }
        $limit = $this->request->input('limit', 20);
        $data  = NotifyModel::where($where)
            ->with(['member' => function ($member) {
                $member->select(['uid', 'user_name']);
            }])
            ->orderBy('add_time', 'DESC')
            ->paginate($limit);
        return $this->response->json([
            'code' => 200,
            'msg'  => 'success',
            'data' => [
                'count' => $data->total(),
                'list'  => $data->items(),
            ]
        ]);
    }

    /**
     * @DOC 发布消息通知
     */
    #[RequestMapping(path: "release", methods: "post")]
    public function release(RequestInterface $request): ResponseInterface
    {
        $params        = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'title'          => ['required', 'between:1,50'],
                'type'           => ['required', 'integer'],
                'receive_type'   => ['required', Rule::in([0, 1, 2, 3])],
                'message'        => ['required'],
                'status'         => ['required', 'integer'],
                'receive_status' => ['required', 'integer'],
            ],
            [
                'title.required'          => '标题不能为空',
                'title.between'           => '标题数字超限',
                'type.required'           => '消息类型不能为空',
                'type.integer'            => '消息类型参数错误',
                'receive_type.required'   => '请选择收件人群体',
                'receive_type.in'         => '请选择收件人群体',
                'message.required'        => '消息不能为空',
                'member_uid.required'     => '用户不能为空',
                'member_uid.integer'      => '用户参数错误',
                'notify_id.required'      => '消息不能为空',
                'notify_id.integer'       => '消息参数错误',
                'status.required'         => '发布状态不能为空',
                'status.integer'          => '发布状态参数错误',
                'receive_status.required' => '收件人状态不能为空',
                'receive_status.integer'  => '收件人状态参数错误',
            ]
        );
        $member        = $this->request->UserInfo;

        // 验证 $param['message'] 字段非空  替换掉字段中的<p></p></p>
        $message = str_replace(['<p>', '</p>', '<br>'], '', $param['message']);
        if (empty($message)) {
            throw new HomeException('消息不能为空');
        }

        $data = [
            'title'            => $param['title'],
            'member_uid'       => $member['uid'],
            'parent_agent_uid' => $member['parent_agent_uid'],
            'type'             => $param['type'],
            'receive_status'   => $param['receive_status'],
            'message'          => json_encode($param['message'], true),
            'status'           => $param['status'],
            'add_time'         => time()
        ];
        # 入库 消息中心 用户已读表
        $notify_id = NotifyModel::insertGetId($data);
        if ($data['status'] && !$data['receive_status']) {
            $notifyRead = $this->getReceiveData($param, $member, $notify_id);
            if (!empty($notifyRead)) {
                NotifyReadModel::insert($notifyRead);
            }
        }
        return $this->response->json([
            'code' => 200,
            'msg'  => '操作成功',
            'data' => []
        ]);
    }

    protected function getReceiveData($param, $member, $notify_id)
    {
        $receive_uid = $notifyRead = [];
        switch ($param['receive_type']) {
            // 当前角色下的所有人
            case 0:
                if ($member['role_id'] == 1) {
                    $receive_uid = AgentMemberModel::where('parent_agent_uid', $member['parent_agent_uid'])->pluck('member_uid')->toArray();
                } else if ($member['role_id'] == 3) {
                    $receive_uid = AgentMemberModel::where('parent_agent_uid', $member['parent_agent_uid'])
                        ->where('parent_join_uid', $member['member_uid'])
                        ->pluck('member_uid')->toArray();
                }
                break;
            // 当前角色下的所有加盟商 3
            case 1:
                if ($member['role_id'] == 1) {
                    $receive_uid = AgentMemberModel::where('parent_agent_uid', $member['parent_agent_uid'])
                        ->where('role_id', 3)
                        ->pluck('member_uid')->toArray();
                }
                break;
            // 当前角色下的所有用户 4|5
            case 2:
                if ($member['role_id'] == 1) {
                    $receive_uid = AgentMemberModel::where('parent_agent_uid', $member['parent_agent_uid'])
                        ->whereIn('role_id', [4, 5])
                        ->pluck('member_uid')->toArray();
                } else if ($member['role_id'] == 3) {
                    $receive_uid = AgentMemberModel::where('parent_agent_uid', $member['parent_agent_uid'])
                        ->where('parent_join_uid', $member['member_uid'])
                        ->whereIn('role_id', [4, 5])
                        ->pluck('member_uid')->toArray();
                }
                break;
            // 当前角色下的仓管
            case 3:
                if ($member['role_id'] == 1) {
                    $receive_uid = AgentMemberModel::where('parent_agent_uid', $member['parent_agent_uid'])
                        ->where('role_id', 10)
                        ->pluck('member_uid')->toArray();
                }
                break;
            default:
                break;
        }

        foreach ($receive_uid as $v) {
            $notifyRead[] = ['member_uid' => $v, 'notify_id' => $notify_id];
        }
        return $notifyRead;

    }

    /**
     * @DOC 更新消息通知已读
     */
    #[RequestMapping(path: "read", methods: "post")]
    public function read(): ResponseInterface
    {
        $NotifyRequest = $this->container->get(NotifyRequest::class);
        $param         = $NotifyRequest->scene('unread')->validated();
        $where         = [
            ['notify_id', '=', $param['notify_id']],
            ['member_uid', '=', $param['member_uid']],
        ];

        $notify = NotifyModel::where('notify_id', $param['notify_id'])->exists();
        if (!$notify) {
            throw new HomeException('未查询到消息通知');
        }

        $notifyRead = NotifyReadModel::where($where)->first();
        if ($notifyRead) {
            if ($notifyRead['status'] == 1) {
                throw new HomeException('用户已读');
            }
            NotifyReadModel::where($where)->update(['status' => 1, 'read_time' => time()]);
        } else {
            $read = [
                'member_uid' => $param['member_uid'],
                'notify_id'  => $param['notify_id'],
                'status'     => 1,
                'read_time'  => time()
            ];
            NotifyReadModel::insert($read);
        }
        return $this->response->json([
            'code' => 200,
            'msg'  => 'success',
            'data' => []
        ]);
    }

    /**
     * @DOC 撤回未发布的消息通知
     */
    #[RequestMapping(path: "revoke", methods: "post")]
    public function revoke(): ResponseInterface
    {
        $notify_id = $this->request->input('notify_id', []);
        if (!$notify_id) {
            throw new HomeException('缺少删除条件');
        }
        NotifyModel::whereIn('notify_id', $notify_id)->where('status', '!=', 1)->delete();
        return $this->response->json(['code' => 200, 'msg' => '删除成功', 'data' => []]);
    }

    /**
     * @DOC 发布未发布的消息通知
     */
    #[RequestMapping(path: "republish", methods: "post")]
    public function republish(RequestInterface $request): ResponseInterface
    {
        $params        = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'notify_id'      => ['required'],
                'title'          => ['required', 'between:1,50'],
                'type'           => ['required', 'integer'],
                'receive_type'   => ['required', Rule::in([0, 1, 2, 3])],
                'message'        => ['required'],
                'status'         => ['required', 'integer'],
                'receive_status' => ['required', 'integer'],
            ],
            [
                'title.required'          => '标题不能为空',
                'title.between'           => '标题数字超限',
                'type.required'           => '消息类型不能为空',
                'type.integer'            => '消息类型参数错误',
                'receive_type.required'   => '请选择收件人群体',
                'receive_type.in'         => '请选择收件人群体',
                'message.required'        => '消息不能为空',
                'member_uid.required'     => '用户不能为空',
                'member_uid.integer'      => '用户参数错误',
                'notify_id.required'      => '消息不能为空',
                'notify_id.integer'       => '消息参数错误',
                'status.required'         => '发布状态不能为空',
                'status.integer'          => '发布状态参数错误',
                'receive_status.required' => '收件人状态不能为空',
                'receive_status.integer'  => '收件人状态参数错误',
            ]
        );
        $member        = $this->request->UserInfo;

        // 验证 $param['message'] 字段非空  替换掉字段中的<p></p></p>
        $message = str_replace(['<p>', '</p>', '<br>'], '', $param['message']);
        if (empty($message)) {
            throw new HomeException('消息不能为空');
        }

        $data = [
            'title'          => $param['title'],
            'type'           => $param['type'],
            'receive_status' => $param['receive_status'],
            'message'        => json_encode($param['message'], true),
            'status'         => $param['status'],
            'add_time'       => time()
        ];
        NotifyModel::where('notify_id', $param['notify_id'])->update($data);

        if ($data['status'] && !$data['receive_status']) {
            $notifyRead = $this->getReceiveData($param, $member, $param['notify_id']);
            if (!empty($notifyRead)) {
                NotifyReadModel::insert($notifyRead);
            }
        }
        return $this->response->json([
            'code' => 200,
            'msg'  => 'success',
            'data' => []
        ]);
    }

    /**
     * @DOC 获取当前用户未读的消息
     */
    #[RequestMapping(path: "unreadIndex", methods: "post")]
    public function unreadIndex(RequestInterface $request): ResponseInterface
    {
        $member = $request->UserInfo;
        $limit  = $this->request->input('limit', 20);

        $where        = [
            ['status', '=', 1],
            ['member_uid', '!=', $member['uid']],
            ['parent_agent_uid', '=', $member['parent_agent_uid']]
        ];
        $readWhere    = [
            ['member_uid', '=', $member['uid']],
            ['status', '=', 0],
        ];
        $superior_uid = $this->roleWhere($member);
        # 查询 用户未读记录
        $unread = NotifyModel::query()
            ->where($where)
            ->whereIn('member_uid', $superior_uid)
            ->with([
                'member' => function ($member) {
                    $member->select(['uid', 'user_name']);
                },
                'read'   => function ($read) use ($member) {
                    $read->where('member_uid', '=', $member['uid']);
                },
                'type'   => function ($type) {
                    $type->select(['cfg_id', 'name']);
                }
            ])
            ->where(function ($query) use ($readWhere) {
                $query->where('receive_status', 1)
                    ->whereDoesntHave('read')
                    ->orWhere(function ($query) use ($readWhere) {
                        $query->where('receive_status', 0)
                            ->whereHas('read', function ($read) use ($readWhere) {
                                $read->where($readWhere);
                            });
                    });
            })
            ->orderBy('add_time', 'DESC')
            ->paginate($limit);
        return $this->response->json([
            'code' => 200,
            'msg'  => 'success',
            'data' => [
                'count' => $unread->total(),
                'list'  => $unread->items(),
            ]
        ]);
    }


    /**
     * @DOC 获取当前用户组的数据信息
     */
    public function roleWhere($member): array
    {
        return match ($member['role_id']) {
            3, 10 => [$member['parent_agent_uid']],
            4, 5 => [$member['parent_join_uid'], $member['parent_agent_uid']],
            default => [],
        };
    }

    /**
     * @DOC 消息统计条数
     */
    #[RequestMapping(path: "lists/count", methods: "post")]
    public function listsCount(RequestInterface $request)
    {
        $member = $request->UserInfo;

        // 基础查询条件
        $baseWhere = [
            ['status', '=', 1],
            ['member_uid', '!=', $member['uid']],
            ['parent_agent_uid', '=', $member['parent_agent_uid']],
        ];

        // 获取上级用户ID集合
        $superior_uids = $this->roleWhere($member);

        // 本周和本月的时间范围
        $dateRanges = [
            [
                'start' => strtotime(date('Y-m-d 00:00:00', strtotime('-7 day'))),
                'end'   => time(),
                'label' => 'week',
            ],
            [
                'start' => strtotime(date('Y-m-01 00:00:00')),
                'end'   => strtotime(date('Y-m-t 23:59:59')), // 更正结束时间为上个月最后一天
                'label' => 'month',
            ],
            [
                'start' => 0,
                'end'   => strtotime(date('Y-m-01 00:00:00')),
                'label' => 'earlier',
            ],
        ];

        // 初始化数据容器
        $data  = [];
        $query = NotifyModel::query()
            ->whereIn('member_uid', $superior_uids)
            ->where($baseWhere);
        foreach ($dateRanges as $range) {
            $rangeQuery = clone $query;
            $rangeQuery->whereRaw('add_time BETWEEN ? AND ?', [$range['start'], $range['end']])
                ->where(function ($subQuery) use ($member) {
                    $subQuery->where(function ($q) {
                        $q->where('receive_status', 1);
                    })
                        ->orWhere(function ($q) use ($member) {
                            $q->where('receive_status', 0)
                                ->whereHas('read', function ($readQuery) use ($member) {
                                    $readQuery->where('member_uid', $member['uid']);
                                });
                        });
                });
            $data[$range['label']] = $rangeQuery->count();
        }

        // 未读条数统计
        $data['unread'] = NotifyModel::query()
            ->where($baseWhere)
            ->whereIn('member_uid', $superior_uids)
            ->where(function ($query) use ($member) {
                $query->where('receive_status', 1)
                    ->whereDoesntHave('read')
                    ->orWhere(function ($query) use ($member) {
                        $query->where('receive_status', 0)
                            ->whereHas('read', function ($readQuery) use ($member) {
                                $readQuery->where('member_uid', $member['uid'])->where('status', 0);
                            });
                    });
            })
            ->count();

        return $this->response->json([
            'code' => 200,
            'msg'  => '查询成功',
            'data' => $data,
        ]);
    }

}
