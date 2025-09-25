<?php

namespace App\Controller\Home\Config;


use App\Common\Lib\Arr;
use App\Common\Lib\Str;
use App\Controller\Home\AbstractController;
use App\Exception\HomeException;
use App\Model\ApiMemberPlatformAccountModel;
use App\Model\ApiMemberPlatformModel;
use App\Model\ApiPlatformModel;
use App\Request\LibValidation;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;
use Psr\Http\Message\ResponseInterface;
use function App\Common\batchUpdateSql;

#[Controller(prefix: "config/logistics")]
class LogisticsController extends AbstractController
{
    protected int $platform_cfg_id = 1627;//物流公司 查看 category 表。对应的平台类型

    /**
     * @DOC 物流公司列表
     */
    #[RequestMapping(path: "index", methods: "post")]
    public function index(RequestInterface $request): ResponseInterface
    {
        $param   = $request->all();
        $member  = $request->UserInfo;
        $where[] = ['member_id', '=', $member['parent_agent_uid']];
        $where[] = ['platform_cfg_id', '=', $this->platform_cfg_id];

        if (Arr::hasArr($param, 'country_id')) $where[] = ['country_id', '=', $param['country_id']];
        if (Arr::hasArr($param, 'keyword')) $where[] = ['member_platform_name', 'like', '%' . $param['keyword'] . '%'];

        $data = ApiMemberPlatformModel::where($where)
            ->with([
                'country'  => function ($query) {
                    $query->select(['country_id', 'country_name']);
                },
                'platform' => function ($query) {
                    $query->select(['platform_id', 'platform_name', 'platform_code', 'platform_url']);
                },
                'item'     => function ($query) {
                    $query->select(['platform_id', 'item_id', 'item_name', 'item_filed']);
                },
            ])
            ->select(['member_platform_id', 'member_platform_name', 'add_time', 'platform_id', 'info', 'country_id', 'status'])
            ->orderBy('add_time', 'desc')
            ->paginate($param['limit'] ?? 20)->toArray();

        foreach ($data['data'] as $key => $item) {
            if (is_null($item['country'])) {
                $country                       =
                    [
                        'country_id'   => null,
                        'country_name' => '默认国家'
                    ];
                $data['data'][$key]['country'] = $country;
            }
        }


        return $this->response->json(['code' => 200, 'msg' => '查询成功', 'total' => $data['total'], 'data' => $data]);
    }

    /**
     * @DOC 配置账号
     */
    #[RequestMapping(path: "cfg", methods: "post")]
    public function cfg(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param, [
            'country_id'    => ['required_unless:platform_id,9'],
            'platform_id'   => ['required'],
            'platform_name' => ['required'],
            'account'       => ['required_unless:platform_id,9'], # 9 虚拟物流公司
        ], [
            'country_id.required'    => '缺少国家地区平台ID',
            'platform_id.required'   => '缺少平台ID',
            'platform_name.required' => '缺少平台名称',
            'account.required'       => '缺少配置信息',
        ]);
        //检验明细
        if (Arr::hasArr($param, 'account')) {
            foreach ($param['account'] as $val) {
                $LibValidation->validate($val, [
                    'item_id'       => ['required'],
                    'account_value' => ['required'],
                ], [
                    'item_id.required'       => '缺少参数对应ID',
                    'account_value.required' => '值不可为空',
                ]);
            }
        }
        # 判断平台
        $Plat = ApiPlatformModel::where('platform_id', '=', $param['platform_id'])->first();
        if (empty($Plat)) {
            throw new HomeException('当前平台不存在');
        }

        $where                       = [];
        $data                        = [];
        $insertMemberPlatformAccount = [];
        $updateMemberPlatformAccount = [];
        $insertPlat                  = $updatePlat = [];

        # 更新配置信息
        if (Arr::hasArr($param, 'member_platform_id') && $param['member_platform_id'] > 0) {
            unset($where);
            $where[] = ['member_platform_id', '=', $param['member_platform_id']];
            $where[] = ['member_id', '=', $member['uid']];
            $data    = ApiMemberPlatformModel::where($where)
                ->with([
                    'country'  => function ($query) {
                        $query->select(['country_id', 'country_name']);
                    },
                    'platform' => function ($query) {
                        $query->select(['platform_id', 'platform_name', 'platform_code', 'platform_url']);
                    },
                    'item'     => function ($query) {
                        $query->where('member_write', '=', 1)->select(['platform_id', 'item_id', 'item_name', 'item_filed']);
                    },
                    'account'
                ])->first();
            if (empty($data)) {
                throw new HomeException('数据更新失败，未查询到数据');
            }
            $data = $data->toArray();

            $updatePlat['member_platform_name'] = $param['platform_name'];
            $updatePlat['info']                 = $param['info'];
            if (Arr::hasArr($data, ['item', 'account'])) {
                $accountDb = $data['account'];
                $accountDb = array_column($accountDb, null, 'item_id');
                foreach ($data['item'] as $key => $val) {
                    $data['item'][$key]['account'] = Arr::hasArr($accountDb, $val['item_id']) ? $accountDb[$val['item_id']] : [];
                }
            }
            $member_platform_id = $data['member_platform_id'];
        }

        $accountIdArr = [];
        if (isset($data['item']) && !empty($data['item'])) {
            $account      = array_column($data['item'], 'account');
            $accountIdArr = array_column($account, 'account_id');
        }
        # 新增
        if (empty($data)) {
            unset($where);
            $where['member_id']            = $member['uid'];
            $where['member_platform_name'] = $param['platform_name'];
            $memberPlat                    = ApiMemberPlatformModel::where($where)->first();
            if (empty($memberPlat)) {
                $insertPlat['platform_id']          = $param['platform_id'];
                $insertPlat['country_id']           = $param['country_id'];
                $insertPlat['member_platform_name'] = $param['platform_name'];
                $insertPlat['info']                 = $param['info'];
                $insertPlat['status']               = 1;
                $insertPlat['platform_cfg_id']      = $this->platform_cfg_id;
                $insertPlat['member_id']            = $member['uid'];
                $insertPlat['add_time']             = time();
            }
        }
        # 处理配置信息
        if (Arr::hasArr($param, 'account')) {
            foreach ($param['account'] as $val) {
                if (!isset($val['account_id'])) {
                    $item                          = [];
                    $item['item_id']               = $val['item_id'];
                    $item['platform_id']           = $param['platform_id'];
                    $item['member_id']             = $member['uid'];
                    $item['account_value']         = $val['account_value'];
                    $insertMemberPlatformAccount[] = $item;
                }

                if (isset($val['account_id']) && (in_array($val['account_id'], $accountIdArr))) {
                    $item                          = [];
                    $item['account_id']            = $val['account_id'];
                    $item['platform_id']           = $param['platform_id'];
                    $item['member_id']             = $member['uid'];
                    $item['item_id']               = $val['item_id'];
                    $item['account_value']         = $val['account_value'];
                    $updateMemberPlatformAccount[] = $item;
                }
            }
        }

        Db::beginTransaction();
        try {
            if (!empty($insertPlat)) {
                $member_platform_id = Db::table("api_member_platform")->insertGetId($insertPlat);
            }
            if (!empty($updatePlat)) {
                Db::table("api_member_platform")->where('member_platform_id', '=', $param['member_platform_id'])->update($updatePlat);
            }
            if (!empty($insertMemberPlatformAccount)) {
                $addArr['member_platform_id'] = $member_platform_id;
                $insertMemberPlatformAccount  = Arr::pushArr($addArr, $insertMemberPlatformAccount);
                Db::table('api_member_platform_account')->insert($insertMemberPlatformAccount);
            }

            if (!empty($updateMemberPlatformAccount)) {
                $addArr['member_platform_id'] = $member_platform_id;
                $updateMemberPlatformAccount  = Arr::pushArr($addArr, $updateMemberPlatformAccount);
                $updateBrandDataSql           = batchUpdateSql('api_member_platform_account', $updateMemberPlatformAccount, ['account_id']);
                Db::update($updateBrandDataSql);
            }
            // 提交事务
            Db::commit();
            return $this->response->json(['code' => 200, 'msg' => '维护成功', 'data' => []]);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return $this->response->json(['code' => 201, 'msg' => '维护失败：' . $e->getMessage(), 'data' => []]);
        }
    }


    /**
     * @DOC 修改状态
     */
    #[RequestMapping(path: "handleStatus", methods: "post")]
    public function handleStatus(RequestInterface $request): ResponseInterface
    {
        $param = $request->all();

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param, [
            'member_platform_id' => ['required'],
            'platform_name'      => ['required'],
            'status'             => ['required', Rule::in([0, 1]),],
        ], [
            'member_platform_id.required' => '缺少ID',
            'platform_name.required'      => '缺少名称',
            'status.required'             => '状态不存在',
            'status.in'                   => '状态不存在',
        ]);

        $where['member_id']          = $request->UserInfo['uid'];
        $where['member_platform_id'] = $param['member_platform_id'];

        $data = ApiMemberPlatformModel::where($where)->first();
        if (empty($data)) {
            throw new HomeException('错误：当前信息存在');
        }

        if ($data['member_platform_name'] != $param['platform_name']) {
            throw new HomeException('错误：名称不正确');
        }
        if (ApiMemberPlatformModel::where($where)->update(['status' => $param['status']])) {
            return $this->response->json(['code' => 200, 'msg' => '修改成功', 'data' => []]);
        }
        return $this->response->json(['code' => 201, 'msg' => '修改失败', 'data' => []]);

    }

    /**
     * @DOC 物流公司平台列表
     */
    #[RequestMapping(path: "platform", methods: "post")]
    public function platform(RequestInterface $request): ResponseInterface
    {
        $param   = $request->all();
        $where[] = ['platform_cfg_id', '=', 1627];
        if (Arr::hasArr($param, 'platform_id')) {
            $where[] = ['platform_id', '=', $param['platform_id']];
        }
        $data = ApiPlatformModel::where($where)
            ->whereHas('item', function ($item) {
                $item->where('member_write', '=', 1);
            })
            ->with(['item' => function ($query) {
                $query->select(['item_id', 'item_name', 'platform_id', 'item_filed']);
            }])->paginate($param['limit'] ?? 20);

        return $this->response->json([
            'code' => 200,
            'msg'  => '查询成功',
            'data' => [
                'total' => $data->total(),
                'data'  => $data->items(),
            ],
        ]);

    }

}
