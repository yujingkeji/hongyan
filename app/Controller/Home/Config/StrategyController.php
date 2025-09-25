<?php

namespace App\Controller\Home\Config;

use App\Common\Lib\Arr;
use App\Controller\Home\AbstractController;
use App\Exception\HomeException;
use App\Model\ChannelSendModel;
use App\Model\MemberLineModel;
use App\Model\ProductStrategyModel;
use App\Model\WarehouseModel;
use App\Request\LibValidation;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;
use Psr\Http\Message\ResponseInterface;
use function App\Common\batchUpdateSql;

#[Controller(prefix: "config/strategy")]
class StrategyController extends AbstractController
{
    /**
     * @DOC 策略列表
     */
    #[RequestMapping(path: 'index', methods: 'post')]
    public function index(RequestInterface $request): ResponseInterface
    {
        $param   = $request->all();
        $member  = $request->UserInfo;
        $where[] = ['member_uid', '=', $member['parent_agent_uid']];
        if (Arr::hasArr($param, 'keyword')) {
            $where[] = ['strategy_name', 'like', '%' . $param['keyword'] . '%'];
        }
        if (Arr::hasArr($param, 'm_line_id')) {
            $where[] = ['m_line_id', '=', $param['m_line_id']];
        }
        if (isset($param['status']) && in_array($param['status'], [1, 0])) {
            $where[] = ['status', '=', $param['status']];
        }

        $data = ProductStrategyModel::where($where)
            ->with(['item', 'line'])
            ->orderBy('add_time', 'desc')
            ->paginate($param['limit'] ?? 20);
        return $this->response->json(['code' => 200, 'msg' => '查询成功', 'data' => ['total' => $data->total(), 'data' => $data->items()]]);
    }

    /**
     * @DOC 新增策略
     */
    #[RequestMapping(path: 'add', methods: 'post')]
    public function add(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $data   = $this->paramCheck($param, $member, 'add');
        Db::beginTransaction();
        try {
            $strategy_id           = Db::table('product_strategy')->insertGetId($data['strategy']);
            $addArr['strategy_id'] = $strategy_id;
            $item                  = Arr::pushArr($addArr, $data['itemAdd']);
            Db::table("product_strategy_item")->insert($item);
            Db::commit();
            return $this->response->json(['code' => 200, 'msg' => '新增成功', 'data' => []]);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return $this->response->json(['code' => 201, 'msg' => $e->getMessage(), 'data' => []]);
        }
    }

    /**
     * @DOC 策略编辑
     */
    #[RequestMapping(path: 'edit', methods: 'post')]
    public function edit(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $data   = $this->paramCheck($param, $member, 'edit');
        Db::beginTransaction();
        try {
            Db::table('product_strategy')->where('strategy_id', $param['strategy_id'])->update($data['strategy']);
            $addArr['strategy_id'] = $param['strategy_id'];
            //批量编辑
            if (!empty($data['itemUpdate'])) {
                $itemUpdate = Arr::pushArr($addArr, $data['itemUpdate']);
                $updateBrandDataSql = batchUpdateSql('product_strategy_item', $itemUpdate);
                Db::update($updateBrandDataSql);
            }
            //新增
            if (!empty($data['itemAdd'])) {
                $itemAdd = Arr::pushArr($addArr, $data['itemAdd']);
                Db::table("product_strategy_item")->insert($itemAdd);
            }
            //删除
            if (!empty($data['itemDel'])) {
                Db::table('product_strategy_item')->whereIn('item_id', $data['itemDel'])->delete();
            }
            Db::commit();
            return $this->response->json(['code' => 200, 'msg' => '编辑成功', 'data' => []]);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return $this->response->json(['code' => 201, 'msg' => $e->getMessage(), 'data' => []]);
        }
    }

    /**
     * @DOC 参数校验
     */
    public function paramCheck($param, $member, $type = 'add'): array
    {
        $rule    = [
            'strategy_id'   => ['required', 'integer', Rule::exists('product_strategy')->where(function ($query) use ($param) {
                $query->where('strategy_id', '=', $param['strategy_id']);
            })],
            'strategy_name' => ['required', 'min:3', Rule::unique('product_strategy')->where(function ($query) use ($param, $member, $type) {
                $query = $query->where('strategy_name', '=', $param['strategy_name'])
                    ->where('member_uid', '=', $member['uid']);
                if ($type == 'edit') {
                    $query = $query->whereNotIn('strategy_id', [$param['strategy_id']]);
                }
                return $query;
            })],
            'm_line_id'     => ['required', 'integer'],
            'before_back'   => ['required', Rule::in([1, 2])],
            'status'        => ['required', Rule::in([0, 1])],
            'item'          => ['required', 'array'],
        ];
        $message = [
            'strategy_id.required'   => '策略错误',
            'strategy_id.integer'    => '策略错误',
            'strategy_id.exists'     => '策略不存在',
            'strategy_name.required' => '策略名称必填',
            'strategy_name.min'      => '策略名称最少3位',
            'strategy_name.unique'   => '策略名称已存在',
            'm_line_id.required'     => '线路必选',
            'm_line_id.integer'      => '线路错误',
            'before_back.required'   => '取号模式必填',
            'before_back.in'         => '取号模式错误',
            'status.in'              => '状态错误',
            'status.required'        => '状态必选',
            'item.required'          => '配置必填',
            'item.array'             => '配置错误',
        ];

        if ($type == 'add') {
            unset($rule['strategy_id']);
        }
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($param, $rule, $message);

        $memberLineDb = MemberLineModel::where('member_line_id', $param['m_line_id'])
            ->where('uid', $member['uid'])
            ->where('status', 1)->first();
        if (empty($memberLineDb)) {
            throw new HomeException("错误：当前线路不存在。");
        }
        $memberLineDb = $memberLineDb->toArray();
        # 策略主表信息
        $time                      = time();
        $strategy['strategy_name'] = $param['strategy_name'];
        $strategy['member_uid']    = $member['uid'];
        $strategy['before_back']   = $param['before_back'];
        $strategy['status']        = $param['status'];
        $strategy['line_id']       = $memberLineDb['line_id'];
        $strategy['m_line_id']     = $memberLineDb['member_line_id'];
        if ($type == 'add') {
            $strategy['add_time'] = $time;
        }
        $strategy['update_time'] = $time;

        $itemDel = $itemIDArr = [];
        # 判断更新 获取当前的策略内容
        if (isset($param['strategy_id']) && $param['strategy_id']) {
            $productWhere[] = ['strategy_id', '=', $param['strategy_id']];
            $productWhere[] = ['member_uid', '=', $member['uid']];
            $strategyDb     = ProductStrategyModel::where($productWhere)->with(['item'])->first();
            if (empty($strategyDb)) {
                throw new HomeException("错误：当前策略不存在");
            }
            $strategyDb = $strategyDb->toArray();
            $itemDel    = $itemIDArr = array_column($strategyDb['item'], 'item_id');
        }

        //策略具体内容
        $itemAdd = $itemUpdate = [];
        foreach ($param['item'] as $key => $val) {
            $LibValidation->validate($val, [
                'goods_items'  => ['array'], // 页面回显使用
                'goods_item'   => ['required', 'array'],
                'distribution' => ['required', 'integer'],
                'channel'      => ['required', 'array'],
            ], [
                'goods_item.required'   => '缺少包裹内物类型',
                'goods_item.array'      => '缺少包裹内物类型',
                'distribution.required' => '细分策略错误',
                'distribution.integer'  => '细分策略错误',
                'channel.required'      => '渠道错误',
                'channel.array'         => '渠道错误',
            ]);

            // 判断当前策略所选择的渠道的集货仓库是否与可运送商品冲突
            // 查询渠道
            $channelIds = array_column($val['channel'], 'channel_id');
            $ware_ids   = ChannelSendModel::whereIn('channel_id', $channelIds)->pluck('ware_id');
            $wareDb     = WarehouseModel::whereIn('ware_id', $ware_ids)->select(['confine'])->get()->toArray();
            if (!empty($wareDb)) {
                foreach ($wareDb as $ware) {
                    if (!empty($ware['confine'])) {
                        foreach ($ware['confine'] as $conf) {
                            if (in_array($conf['goods_id'], $val['goods_item'])) {
                                throw new HomeException("错误：当前渠道集货仓库与可运送商品(" . $conf['goods_name'] . ")冲突");
                            }
                        }
                    }
                }
            }

            // 判断 同一渠道和策略合并
            unset($ret);
            $ret = $this->checkStrategy($val, $itemAdd, $itemUpdate);
            if ($ret[0]) {
                $itemAdd    = $ret[1];
                $itemUpdate = $ret[2];
                continue;
            }

            //更新内容
            if (Arr::hasArr($val, 'item_id') && (in_array($val['item_id'], $itemIDArr))) {
                $itemUpdate[$key]['item_id']      = $val['item_id'];
                $itemUpdate[$key]['goods_items']  = json_encode($val['goods_items']);
                $itemUpdate[$key]['goods_item']   = json_encode($val['goods_item']);
                $itemUpdate[$key]['distribution'] = $val['distribution'];
                $itemUpdate[$key]['channel']      = json_encode($val['channel']);

                $itemDel = Arr::del($itemDel, $val['item_id']);
            }
            //新增的内容；
            if (!isset($val['item_id']) || (isset($val['item_id']) && !in_array($val['item_id'], $itemIDArr))) {
                $itemAdd[$key]['goods_items']  = json_encode($val['goods_items']);
                $itemAdd[$key]['goods_item']   = json_encode($val['goods_item']);
                $itemAdd[$key]['distribution'] = $val['distribution'];
                $itemAdd[$key]['channel']      = json_encode($val['channel']);
            }

            if ($val['distribution'] == 3) {
                foreach ($val['channel'] as $v) {
                    $LibValidation->validate($v, [
                        'channel_id'    => ['required', 'integer'],
                        'channel_value' => ['required', 'integer', 'min:1'],
                    ], [
                        'channel_id.required'    => '渠道错误',
                        'channel_id.integer'     => '渠道错误',
                        'channel_value.required' => '渠道错误',
                        'channel_value.integer'  => '渠道错误',
                        'channel_value.min'      => '定量分配下：数量必须大于等于1',
                    ]);
                }
            }
        }
        return ['strategy' => $strategy, 'itemAdd' => $itemAdd, 'itemUpdate' => $itemUpdate, 'itemDel' => $itemDel];
    }

    /**
     * @DOC 新增或修改时检查是否存在相同策略
     */
    protected function checkStrategy($item, $add, $update)
    {
        // 检查 $add 中是否存在 $item里的信息
        foreach ($add as &$addItem) {
            $channel = json_encode($item['channel']);
            if ($item['distribution'] == $addItem['distribution'] && $channel == $addItem['channel']) {
                $goods_item            = json_decode($addItem['goods_item']);
                $goods_item_merge      = array_merge($item['goods_item'], $goods_item);
                $goods_item_merge      = array_unique($goods_item_merge);
                $addItem['goods_item'] = json_encode($goods_item_merge);
                return [true, $add, $update];
            }
        }
        // 检查 $update 中是否存在 $item里的信息
        foreach ($update as &$updateItem) {
            $channel = json_encode($item['channel']);
            if ($item['distribution'] == $updateItem['distribution'] && $channel == $updateItem['channel']) {
                $goods_item               = json_decode($updateItem['goods_item']);
                $goods_item_merge         = array_merge($item['goods_item'], $goods_item);
                $goods_item_merge         = array_unique($goods_item_merge);
                $updateItem['goods_item'] = json_encode($goods_item_merge);
                return [true, $add, $update];
            }
        }
        return [false, [], []];
    }

    /**
     * @DOC 状态修改
     */
    #[RequestMapping(path: 'status', methods: 'post')]
    public function status(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param, [
            'strategy_id' => ['required', 'integer'],
            'status'      => ['required', Rule::in([0, 1])],
        ], [
            'strategy_id.required' => '产品策略错误',
            'strategy_id.integer'  => '产品策略错误',
            'status.required'      => '状态必选',
            'status.in'            => '状态错误',
        ]);

        $where['strategy_id'] = $param['strategy_id'];
        $where['member_uid']  = $member['uid'];
        $strategy             = ProductStrategyModel::where($where)->first();
        if (empty($strategy)) {
            throw new HomeException('处理失败：产品策略不存在');
        }
        if (Db::table('product_strategy')->where($where)->update(['status' => $param['status']])) {
            return $this->response->json(['code' => 200, 'msg' => '修改成功', 'data' => []]);
        }
        return $this->response->json(['code' => 201, 'msg' => '修改失败', 'data' => []]);
    }


}
