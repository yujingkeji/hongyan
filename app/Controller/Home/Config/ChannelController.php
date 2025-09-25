<?php

namespace App\Controller\Home\Config;

use App\Common\Lib\Arr;
use App\Controller\Home\AbstractController;
use App\Exception\HomeException;
use App\Model\ApiMemberPlatformModel;
use App\Model\ChannelModel;
use App\Model\MemberLineModel;
use App\Model\MemberPortModel;
use App\Model\MemberSevModel;
use App\Request\LibValidation;
use App\Service\Cache\BaseCacheService;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: "config/channel")]
class ChannelController extends AbstractController
{
    /**
     * @DOC 渠道列表
     */
    #[RequestMapping(path: 'index', methods: 'post')]
    public function index(RequestInterface $request): ResponseInterface
    {
        $param   = $request->all();
        $member  = $request->UserInfo;
        $where[] = ['member_uid', '=', $member['parent_agent_uid']];

        $data = ChannelModel::query();
        if (Arr::hasArr($param, 'line_id')) $where[] = ['line_id', '=', $param['line_id']];
        if (Arr::hasArr($param, 'port_id')) {
            $data = $data->whereHas('import', function ($query) use ($param) {
                $query->where('port_id', '=', $param['port_id']);
            });
        }
        if (Arr::hasArr($param, 'channel_name')) $where[] = ['channel_name', 'like', '%' . $param['channel_name'] . '%'];
        if (Arr::hasArr($param, 'status')) $where[] = ['status', '=', $param['status']];
        if (Arr::hasArr($param, 'many_transport')) $where[] = ['many_transport', '=', $param['many_transport']];

        $data = $data->where($where)
            ->with([
                'port' => function ($query) {
                    $query->select(['port_id', 'name', 'airport', 'railwayport', 'highwayport', 'waterport']);
                },
                'line' => function ($query) {
                    $query->select(['line_id', 'line_name', 'send_country_id', 'send_country', 'target_country_id', 'target_country']);
                },
                'import.port'
            ])
            ->orderBy('add_time', 'DESC')
            ->paginate($param['limit'] ?? 20);
        return $this->response->json(['code' => 200, 'msg' => '查询成功', 'data' => ['total' => $data->total(), 'data' => $data->items()]]);
    }

    /**
     * @DOC 渠道详情
     */
    #[RequestMapping(path: 'info', methods: 'post')]
    public function info(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param, [
            'channel_id' => ['required', 'integer']
        ], [
            'channel_id.required' => '渠道错误',
            'channel_id.integer'  => '渠道错误'
        ]);
        $where['member_uid'] = $member['uid'];
        $where['channel_id'] = $param['channel_id'];

        $channelDb = ChannelModel::where($where)
            ->with(['send', 'export', 'import', 'transport', 'trunk'])
            ->first();
        $item      = [];
        if (!empty($channelDb)) {
            $channelDb = $channelDb->toArray();

            if ($channelDb['send']) $item[] = $channelDb['send'];
            if ($channelDb['export']) $item[] = $channelDb['export'];
            if ($channelDb['import']) $item[] = $channelDb['import'];
            if ($channelDb['transport']) $item[] = $channelDb['transport'];
            if ($channelDb['trunk']) $item[] = $channelDb['trunk'];
            $item = Arr::reorder($item, 'sort', 'SORT_ASC');
        }
        $channelDb['item'] = $item;
        unset($channelDb['send']);
        unset($channelDb['export']);
        unset($channelDb['import']);
        unset($channelDb['transport']);
        unset($channelDb['trunk']);

        return $this->response->json(['code' => 200, 'msg' => '查询成功', 'data' => $channelDb]);
    }

    /**
     * @DOC 状态修改
     */
    #[RequestMapping(path: 'status', methods: 'post')]
    public function status(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param, [
            'channel_id' => ['required', 'integer'],
            'status'     => ['required', Rule::in([0, 1])]
        ], [
            'channel_id.required' => '渠道错误',
            'channel_id.integer'  => '渠道错误',
            'status.required'     => '状态错误',
            'status.in'           => '状态错误',
        ]);

        $member              = $request->UserInfo;
        $where['member_uid'] = $member['uid'];
        $where['channel_id'] = $param['channel_id'];
        $channelDb           = ChannelModel::where($where)->first();
        if (empty($channelDb)) {
            throw new HomeException('当前渠道不存在，禁止修改');
        }
        if ($channelDb['status'] == $param['status']) {
            throw new HomeException('当前状态未改变：禁止修改');
        }
        if (ChannelModel::where($where)->update(['status' => $param['status']])) {
            return $this->response->json(['code' => 200, 'msg' => '修改成功', 'data' => []]);
        }
        return $this->response->json(['code' => 201, 'msg' => '修改失败', 'data' => []]);
    }

    /**
     * @DOC 渠道新增
     */
    #[RequestMapping(path: 'add', methods: 'post')]
    public function add(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        try {
            $channel = $this->handleChannel($param, $member);
            $data    = $this->paramCheck($param, $member, 'add');
        } catch (\Exception $e) {
            throw new HomeException($e->getMessage());
        }
        //写入数据库
        Db::beginTransaction();
        try {
            $channel_id = Db::table('channel')->insertGetId($channel);
            if (isset($data['send']) && !empty($data['send'])) {
                $data['send']['channel_id'] = $channel_id;
                Db::table('channel_send')->insert($data['send']);
            }
            if (isset($data['export']) && !empty($data['export'])) {
                $data['export']['channel_id'] = $channel_id;
                Db::table('channel_export')->insert($data['export']);
            }
            if (isset($data['trunk']) && !empty($data['trunk'])) {
                $data['trunk']['channel_id'] = $channel_id;
                Db::table('channel_trunk')->insert($data['trunk']);
            }
            if (isset($data['import']) && !empty($data['import'])) {
                $data['import']['channel_id'] = $channel_id;
                Db::table('channel_import')->insert($data['import']);
            }
            if (isset($data['transport']) && !empty($data['transport'])) {
                $data['transport']['channel_id'] = $channel_id;
                Db::table('channel_transport')->insert($data['transport']);
            }
            // 提交事务
            Db::commit();
            return $this->response->json(['code' => 200, 'msg' => '添加成功', 'data' => []]);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return $this->response->json(['code' => 201, 'msg' => '添加失败：' . $e->getMessage(), 'data' => []]);
        }
    }

    /**
     * @DOC 渠道编辑
     */
    #[RequestMapping(path: 'edit', methods: 'post')]
    public function edit(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        try {
            $channel = $this->handleChannel($param, $member);
            unset($channel['add_time']);
            $data = $this->paramCheck($param, $member, 'edit');
        } catch (\Exception $e) {
            throw new HomeException($e->getMessage());
        }

        //写入数据库
        Db::beginTransaction();
        try {
            $where[] = ['channel_id', '=', $param['channel_id']];
            Db::table('channel')->where($where)->update($channel);
            if (isset($data['send']) && !empty($data['send'])) {
                Db::table('channel_send')->updateOrInsert(['channel_id' => $param['channel_id']],$data['send']);
            } else {
                Db::table('channel_send')->where($where)->delete();
            }

            if (isset($data['export']) && !empty($data['export'])) {
                Db::table('channel_export')->updateOrInsert(['channel_id' => $param['channel_id']],$data['export']);
            } else {
                Db::table('channel_export')->where($where)->delete();
            }
            if (isset($data['trunk']) && !empty($data['trunk'])) {
                Db::table('channel_trunk')->updateOrInsert(['channel_id' => $param['channel_id']],$data['trunk']);
            } else {
                Db::table('channel_trunk')->where($where)->delete();
            }
            if (isset($data['import']) && !empty($data['import'])) {
                Db::table('channel_import')->updateOrInsert(['channel_id' => $param['channel_id']],$data['import']);
            } else {
                Db::table('channel_import')->where($where)->delete();
            }
            if (isset($data['transport']) && !empty($data['transport'])) {
                Db::table('channel_transport')->updateOrInsert(['channel_id' => $param['channel_id']],$data['transport']);
            } else {
                Db::table('channel_transport')->where($where)->delete();
            }
            // 提交事务
            Db::commit();
            return $this->response->json(['code' => 200, 'msg' => '修改成功', 'data' => []]);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return $this->response->json(['code' => 201, 'msg' => '修改失败：' . $e->getMessage(), 'data' => []]);
        }

    }

    /**
     * @DOC 参数校验
     */
    protected function paramCheck($param, $member, $type = 'add'): array
    {
        $rule    = [
            'channel_name'   => ['required', 'min:3'],
            'm_line_id'      => ['required', 'integer'],
            //            'port_id'        => ['required', 'integer'],
            'item'           => ['required', 'array'],
            'many_transport' => ['integer', Rule::in([0, 1])],
        ];
        $message = [
            'channel_name.required' => '渠道名称必填',
            'channel_name.min'      => '渠道名称最少3位',
            'm_line_id.required'    => '线路必选',
            'm_line_id.integer'     => '线路错误',
            //            'port_id.required'      => '口岸必选',
            //            'port_id.integer'       => '口岸错误',
            'item.required'         => '节点必填',
            'item.array'            => '节点错误',
        ];

        if ($type == 'edit') {
            $rule['channel_id']             = ['required', 'integer', Rule::exists('channel')->where(function ($query) use ($param) {
                $query->where('channel_id', '=', $param['channel_id']);
            })];
            $message['channel_id.required'] = '渠道错误';
            $message['channel_id.integer']  = '渠道错误';
            $message['channel_id.exists']   = '渠道不存在';
        }
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param, $rule, $message);

        $nodeGroup = $this->nodeGroup($param['item']);
        $data      = [];
        foreach ($param['item'] as $val) {
            if (!Arr::hasArr($val, 'node_cfg_id')) {
                throw new HomeException('节点类型不存在');
            }
            $mWhere   = [];
            $mWhere[] = [
                ['member_sev_id' => $val['m_sev_id']],
                ['use_uid' => $member['uid']],
                ['status' => 1],
            ];

            # 查询节点
            $mSevDb = MemberSevModel::where($mWhere)->first();
            if (empty($mSevDb)) {
                throw new HomeException($val['node_cfg_id'] . ' 当前服务不存在');
            }
            switch ($val['node_cfg_id']) {
                # channel_send 发出集货 表
                case 1619:
                    $rule    = [
                        'node_cfg_id'       => ['required', 'integer'],
                        'm_sev_id'          => ['required', 'integer'],
                        'ware_id'           => ['required', 'integer'],
                        //                        'take_up'           => ['required', Rule::in([0, 1])],
                        'take_text'         => ['array'],
                        'price_template_id' => ['integer'],
                        'sort'              => ['required', 'integer'],
                    ];
                    $message = [
                        'node_cfg_id.required'      => '操作节点编号错误',
                        'node_cfg_id.integer'       => '操作节点编号错误',
                        'm_sev_id.required'         => '服务错误',
                        'm_sev_id.integer'          => '服务错误',
                        'ware_id.required'          => '集货仓库错误',
                        'ware_id.integer'           => '集货仓库错误',
                        //                        'take_up.required'          => '请选择是否支持上门取件',
                        //                        'take_up.in'                => '请选择是否支持上门取件',
                        'take_text.array'           => '上门取件内容错误',
                        'price_template_id.integer' => '价格模板错误',
                        'sort.required'             => '排序错误',
                        'sort.integer'              => '排序错误',
                    ];

                    $LibValidation->validate($val, $rule, $message);
                    if (isset($nodeGroup[$val['node_cfg_id']]) && count($nodeGroup[$val['node_cfg_id']]) >= 2) {
                        throw new HomeException('错误：一个渠道只能一个集货发出节点');
                    }

                    $data['send']['node_cfg_id']       = $val['node_cfg_id'];
                    $data['send']['m_sev_id']          = $val['m_sev_id'];
                    $data['send']['country_id']        = $val['country_id'];
                    $data['send']['m_sev_id']          = $mSevDb['member_sev_id'];
                    $data['send']['sev_id']            = $mSevDb['sev_id'];
                    $data['send']['price_template_id'] = $val['price_template_id'];
                    $data['send']['ware_id']           = $val['ware_id'];
                    $data['send']['take_up']           = $val['take_up'] ?? 0;
                    $data['send']['take_text']         = isset($val['take_text']) && !empty($val['take_text']) ? json_encode($val['take_text'], true) : '';
                    $data['send']['sort']              = $val['sort'];
                    unset($mSevDb, $mWhere);
                    break;
                # channel_export 干线报关：发出地区出口 表
                case 1620:
                    $rule    = [
                        'node_cfg_id'       => ['required', 'integer'],
                        'port_id'           => ['required', 'integer'],
                        'm_sev_id'          => ['required', 'integer'],
                        'supervision_id'    => ['integer'],
                        'ports_id'          => ['integer'],
                        'way_id'            => ['integer'],
                        //                        'm_platform_id'     => ['required', 'integer'],
                        'price_template_id' => ['integer'],
                        'sort'              => ['required', 'integer'],
                    ];
                    $message = [
                        'node_cfg_id.required'      => '操作节点编号错误',
                        'node_cfg_id.integer'       => '操作节点编号错误',
                        'port_id.required'          => '总库口岸错误',
                        'port_id.integer'           => '总库口岸错误',
                        'm_sev_id.required'         => '服务错误',
                        'm_sev_id.integer'          => '服务错误',
                        'supervision_id.integer'    => '监管方式错误',
                        'ports_id.integer'          => '港口机场错误',
                        'way_id.integer'            => '认证方式错误',
                        //                        'm_platform_id.required'    => '快递公司错误',
                        //                        'm_platform_id.integer'     => '快递公司错误',
                        'price_template_id.integer' => '价格模板错误',
                        'sort.required'             => '排序错误',
                        'sort.integer'              => '排序错误',
                    ];
                    $LibValidation->validate($val, $rule, $message);
                    if (isset($nodeGroup[$val['node_cfg_id']]) && count($nodeGroup[$val['node_cfg_id']]) >= 2) {
                        throw new HomeException('错误：一个渠道只能一个发出报关节点');
                    }
                    $data['export']['node_cfg_id'] = $val['node_cfg_id'];
                    $data['export']['country_id']  = $val['country_id'];
                    $data['export']['m_sev_id']    = $mSevDb['member_sev_id'];
                    $data['export']['sev_id']      = $mSevDb['sev_id'];

                    $pWhere   = [];
                    $pWhere[] = [
                        ['port_id' => $val['port_id']],
                        ['member_uid' => $member['uid']],
                        ['status' => 1],
                    ];
                    $mPortDb  = MemberPortModel::where($pWhere)->first();
                    if (empty($mPortDb)) {
                        throw new HomeException("干线报关：当前口岸不存在", 201);
                    }
                    $data['export']['m_port_id'] = $mPortDb['member_port_id'];
                    $data['export']['port_id']   = $mPortDb['port_id'];

                    $platWhere   = [];
                    $platWhere[] = [
                        ['member_platform_id' => $val['m_platform_id']],
                        ['member_id' => $member['uid']],
                        ['status' => 1],
                    ];
                    $mPlatformDb = ApiMemberPlatformModel::where($platWhere)->first();

                    if (!empty($mPlatformDb)) {
                        $mPlatformDb                     = $mPlatformDb->toArray();
                        $data['export']['m_platform_id'] = $mPlatformDb['member_platform_id'];
                        $data['export']['platform_id']   = $mPlatformDb['platform_id'];
                    }
                    $data['export']['supervision_id']    = $val['supervision_id'];
                    $data['export']['ports_id']          = $val['ports_id'];
                    $data['export']['way_id']            = Arr::hasArr($val, 'way_id') ? $val['way_id'] : 0;
                    $data['export']['price_template_id'] = $val['price_template_id'];
                    $data['export']['sort']              = $val['sort'];
                    break;
                # channel_trunk 干线运输，就是 空运、海运、主要是两个国家地区之间的干线 表
                case 1621:
                    $rule    = [
                        'node_cfg_id'       => ['required', 'integer'],
                        'company_id'        => ['required', 'integer'],
                        'company_item_id'   => ['required', 'integer'],
                        'm_sev_id'          => ['required', 'integer'],
                        'price_template_id' => ['integer'],
                        'sort'              => ['required', 'integer'],
                    ];
                    $message = [
                        'node_cfg_id.required'      => '操作节点编号错误',
                        'node_cfg_id.integer'       => '操作节点编号错误',
                        'company_id.required'       => '货运公司错误',
                        'company_id.integer'        => '货运公司错误',
                        'company_item_id.required'  => '货运公司具体信息错误',
                        'company_item_id.integer'   => '货运公司具体信息错误',
                        'm_sev_id.required'         => '服务错误',
                        'm_sev_id.integer'          => '服务错误',
                        'price_template_id.integer' => '价格模板错误',
                        'sort.required'             => '排序错误',
                        'sort.integer'              => '排序错误',
                    ];
                    $LibValidation->validate($val, $rule, $message);
                    if (isset($nodeGroup[$val['node_cfg_id']]) && count($nodeGroup[$val['node_cfg_id']]) >= 2) {
                        throw new HomeException('错误：一个渠道只能一个干线运输节点');
                    }
                    $data['trunk']['node_cfg_id']       = $val['node_cfg_id'];
                    $data['trunk']['country_id']        = $val['country_id'];
                    $data['trunk']['m_sev_id']          = $mSevDb['member_sev_id'];
                    $data['trunk']['sev_id']            = $mSevDb['sev_id'];
                    $data['trunk']['company_id']        = $val['company_id'];
                    $data['trunk']['company_item_id']   = $val['company_item_id'];
                    $data['trunk']['price_template_id'] = $val['price_template_id'];
                    $data['trunk']['sort']              = $val['sort'];
                    break;
                # channel_import 干线清关：目的地区进口，也就是干线到达后，目的国家或地区的清关节点 表
                case 1622:
                    $rule    = [
                        'node_cfg_id'       => ['required', 'integer'],
                        'm_sev_id'          => ['required', 'integer'],
                        'port_id'           => ['required', 'integer'],
                        'supervision_id'    => ['integer'],
                        'ports_id'          => ['integer'],
                        'way_id'            => ['integer'],
                        'price_template_id' => ['integer'],
                        'sort'              => ['required', 'integer'],
                    ];
                    $message = [
                        'node_cfg_id.required'      => '操作节点编号错误',
                        'node_cfg_id.integer'       => '操作节点编号错误',
                        'port_id.required'          => '总库口岸错误',
                        'port_id.integer'           => '总库口岸错误',
                        'm_sev_id.required'         => '服务错误',
                        'm_sev_id.integer'          => '服务错误',
                        'supervision_id.integer'    => '监管方式错误',
                        'ports_id.integer'          => '港口机场错误',
                        'way_id.integer'            => '认证方式错误',
                        'price_template_id.integer' => '价格模板错误',
                        'sort.required'             => '排序错误',
                        'sort.integer'              => '排序错误',
                    ];
                    $LibValidation->validate($val, $rule, $message);
                    if (isset($nodeGroup[$val['node_cfg_id']]) && count($nodeGroup[$val['node_cfg_id']]) >= 2) {
                        throw new HomeException('错误：一个渠道只能一个干线清关节点');
                    }
                    $data['import']['node_cfg_id'] = $val['node_cfg_id'];
                    $data['import']['country_id']  = $val['country_id'];
                    $data['import']['m_sev_id']    = $mSevDb['member_sev_id'];
                    $data['import']['sev_id']      = $mSevDb['sev_id'];

                    $pWhere   = [];
                    $pWhere[] = [
                        ['port_id' => $val['port_id']],
                        ['member_uid' => $member['uid']],
                        ['status' => 1],
                    ];
                    $mPortDb  = MemberPortModel::where($pWhere)->first();
                    if (empty($mPortDb)) {
                        throw new HomeException('当前口岸不存在');
                    }
                    $data['import']['m_port_id']         = $mPortDb['member_port_id'];
                    $data['import']['port_id']           = $mPortDb['port_id'];
                    $data['import']['supervision_id']    = $val['supervision_id'];
                    $data['import']['ports_id']          = $val['ports_id'];
                    $data['import']['way_id']            = Arr::hasArr($val, 'way_id') ? $val['way_id'] : 0;
                    $data['import']['price_template_id'] = $val['price_template_id'];
                    $data['import']['sort']              = $val['sort'];
                    break;
                # channel_transport 落地转运，落地转运，主要是落地服务商的配置 表
                case 1623:
                    $rule    = [
                        'node_cfg_id'       => ['required', 'integer'],
                        'm_sev_id'          => ['required', 'integer'],
                        'm_platform_id'     => ['required', 'integer'],
                        'print_template_id' => ['integer'],
                        'price_template_id' => ['integer'],
                        'sort'              => ['required', 'integer'],
                    ];
                    $message = [
                        'node_cfg_id.required'      => '操作节点编号错误',
                        'node_cfg_id.integer'       => '操作节点编号错误',
                        'm_sev_id.required'         => '服务错误',
                        'm_sev_id.integer'          => '服务错误',
                        'm_platform_id.required'    => '快递公司错误',
                        'm_platform_id.integer'     => '快递公司错误',
                        'price_template_id.integer' => '价格模板错误',
                        'print_template_id.integer' => '打印模板错误',
                        'sort.required'             => '排序错误',
                        'sort.integer'              => '排序错误',
                    ];
                    $LibValidation->validate($val, $rule, $message);
                    if (isset($nodeGroup[$val['node_cfg_id']]) && count($nodeGroup[$val['node_cfg_id']]) >= 2) {
                        throw new HomeException('错误：一个渠道只能一个落地转运节点');
                    }

                    $data['transport']['node_cfg_id'] = $val['node_cfg_id'];
                    $data['transport']['country_id']  = $val['country_id'];
                    $data['transport']['m_sev_id']    = $mSevDb['member_sev_id'];
                    $data['transport']['sev_id']      = $mSevDb['sev_id'];

                    $platWhere   = [];
                    $platWhere[] = [
                        ['member_platform_id' => $val['m_platform_id']],
                        ['member_id' => $member['uid']],
                        ['status' => 1],
                    ];
                    $mPlatformDb = ApiMemberPlatformModel::where($platWhere)->first();
                    if (empty($mPlatformDb)) {
                        throw new HomeException('落地转运：快递公司不存在');
                    }
                    $data['transport']['m_platform_id']     = $mPlatformDb['member_platform_id'];
                    $data['transport']['platform_id']       = $mPlatformDb['platform_id'];
                    $data['transport']['print_template_id'] = $val['print_template_id'];
                    $data['transport']['price_template_id'] = $val['price_template_id'];
                    $data['transport']['sort']              = $val['sort'];
                    break;
                default:
                    throw new HomeException('不存在的类型编号：' . $val['node_cfg_id']);
            }
        }
        return $data;
    }

    /**
     * @DOC 处理渠道
     */
    protected function handleChannel($param, $member): array
    {
        $where['member_line_id'] = $param['m_line_id'];
        $where['uid']            = $member['uid'];
        $where['status']         = 1;
        $mLineDb                 = MemberLineModel::where($where)->select(['member_line_id', 'line_id', 'start_time', 'end_time'])->first();
        if (empty($mLineDb)) {
            throw new HomeException("当前线路不存在", 201);
        }
        if (Arr::hasArr($param, 'channel_id')) {
            $channel['channel_id'] = $param['channel_id'];
        }
        $channel['channel_name'] = $param['channel_name'];
        $channel['m_line_id']    = $mLineDb['member_line_id'];
        $channel['line_id']      = $mLineDb['line_id'];
        $channel['member_uid']   = $member['uid'];
        $channel['port_id']      = $param['port_id'] ?? 0;
        $channel['add_time']     = time();
        $channel['status']       = $param['status'] ?? 0;
        return $channel;
    }

    /**
     * @DOC  渠道节点分组。
     */
    protected function nodeGroup($node): array
    {
        $node_cfg   = array_column($node, 'node_cfg_id');
        $node_group = [];
        foreach ($node_cfg as $k => $v) {
            $node_group[$v][] = $v;
        }
        return $node_group;
    }

}
