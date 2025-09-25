<?php

/**
 *商品信息备案任务，并且处理相同的订单
 */
declare(strict_types=1);

namespace App\Process\JobProcess;

use App\Common\Lib\Arr;
use App\Common\Lib\UserDefinedIdGenerator;
use App\Exception\HomeException;
use App\Model\OrderExceptionItemModel;
use App\Model\OrderItemModel;
use App\Model\OrderModel;
use App\Model\RiBenModel;
use App\Request\LibValidation;
use App\Service\Cache\BaseCacheService;
use App\Service\TaskCenterPushService;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\Logger;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Redis\Redis;
use function App\Common\hasSort;

class RiBenDataToBaseJob
{
    protected Redis $redis;
    protected UserDefinedIdGenerator $idGenerator;
    #[Inject]
    protected LoggerFactory $loggerFactory;

    #[Inject]
    protected BaseCacheService $baseCacheService;

    protected Logger $logger;

    public function __construct()
    {
        $this->redis       = \Hyperf\Support\make(Redis::class);
        $this->logger      = $this->loggerFactory->get('default');
        $this->idGenerator = make(UserDefinedIdGenerator::class);
    }

    public function handle(array $job_data)
    {
        $member = $job_data['member'];
        if (Arr::hasArr($job_data, 'id')) {
            return $this->RiBenDataToBase(member: $member, id: $job_data['id']);
        }
        \App\Constants\Logger::error('会员信息', $member);
        return $this->RiBenDataToBase(member: $member);
    }

    public function getDb(array $where): array
    {
        $data = RiBenModel::query()->where($where)->first();
        if (empty($data)) {
            return [];
        }
        return $data->toArray();
    }


    /**
     * @DOC   :
     * @Name  : RiBenDataToBase
     * @Author: wangfei
     * @date  : 2025-03 14:28
     * @param array $member
     * @param int $id
     * @return array|false
     *
     */
    public function RiBenDataToBase(array $member, int $id = 0)
    {
        if ($id > 0) {
            $where['id'] = $id;
        } else {
            $where['status'] = 0;
        }
        return $this->handleGoods($where, $member);

    }

    public function handleGoods(array $where, array $member)
    {
        $singleData = $this->getDb($where);
        if (empty($singleData)) {
            return false;
        }
        $tax = $this->baseCacheService->tariffTaxWithIdCache();

        $goods_base_id            = $this->idGenerator->generate($member['uid']);
        $base                     = [];
        $base['goods_base_id']    = $goods_base_id;
        $base['category_item_id'] = 0;
        if (!empty($singleData['tax_number'])) {
            $tax_number               = trim($singleData['tax_number']);
            $base['category_item_id'] = $tax[$tax_number] ?? 0;
        }
        $base['member_uid']          = $member['uid'];
        $base['parent_join_uid']     = 0;
        $base['parent_agent_uid']    = $member['uid'];
        $base['goods_code']          = $singleData['barcode'];
        $base['brand_id']            = 0;
        $base['goods_name']          = $singleData['name_cn'];
        $base['goods_name_en']       = $singleData['name_en'];
        $base['goods_name_source']   = $singleData['name_jp'];
        $base['short_name']          = '';
        $base['brand_name']          = '';
        $base['brand_en']            = '';
        $base['origin_country']      = 3;
        $base['send_country']        = 3;
        $base['place_of_origin']     = '';
        $base['cc_checked']          = 1;
        $base['add_time']            = time();
        $sku                         = [];
        $sku[]                       =
            [
                'goods_base_id'    => $goods_base_id,
                'sku_code'         => $singleData['barcode'],
                'member_uid'       => $member['uid'],
                'parent_join_uid'  => 0,
                'parent_agent_uid' => $member['uid'],
                'barcode'          => $singleData['barcode'],
                'price'            => $singleData['price'],
                'price_unit'       => 'CNY',
                'in_number'        => 1,
                'suttle_weigh'     => $singleData['suttle_weigh'],
                'gross_weight'     => ($singleData['suttle_weigh'] && is_numeric($singleData['suttle_weigh'])) ?? $singleData['suttle_weigh'] * (1 + 0.2),
                'spec'             => $singleData['spec']
            ];
        $cc                          = [];
        $cc                          = [
            'goods_base_id' => $goods_base_id,
            'tax_number'    => $singleData['tax_number'],
            'tax_rate'      => $singleData['tax_rate']
        ];
        $handle                      = [];
        $handle['base']              = $base;
        $handle['sku']               = $sku;
        $handle['cc']                = $cc;
        $handle['base']['goods_md5'] = $this->md5GoodsBase($handle);
        Db::beginTransaction();
        try {
            // 通过goods_md5关联删除 goods_base, goods_cc,goods_sku的数据
            // $goodsBase = Db::table('goods_base')->where(['member_uid' => $member['uid'], 'goods_md5' => $handle['base']['goods_md5']])->first();
            $goodsBase = Db::table('goods_base')->where(['member_uid' => $member['uid'], 'goods_code' => $singleData['barcode']])->first();
            if (!empty($goodsBase)) {
                Db::table('goods_base')->where(['member_uid' => $member['uid'], 'goods_md5' => $handle['base']['goods_md5']])->delete();
                Db::table('goods_cc')->where(['goods_base_id' => $goodsBase->goods_base_id])->delete();
                Db::table('goods_sku')->where(['goods_base_id' => $goodsBase->goods_base_id])->delete();
            }
            Db::table('riben')->where('id', '=', $singleData['id'])->update(['status' => 1]);
            Db::table('goods_base')->insert($handle['base']);
            Db::table('goods_cc')->insert($handle['cc']);
            Db::table('goods_sku')->insert($handle['sku']);
            Db::commit();
            $result = ['code' => 200, 'msg' => '处理完成'];
        } catch (\Exception $e) {
            \App\Constants\Logger::error('处理报错' . $e->getMessage(), $singleData);
            Db::rollback();
            $result = ['code' => 201, 'msg' => '处理报错'];
        }

        if (!Arr::hasArr($where, 'id') && $result['code'] == 200) {
            return $this->handleGoods($where, $member);
        }
        return ['code' => 200, 'msg' => '处理完成'];
    }

    public function md5GoodsBase($handle)
    {
        $DelFiled = [
            'goods_base_id',
            'record_status',
            'add_time'
        ];
        foreach ($DelFiled as $key => $value) {
            unset($handle['base'][$value]);
            unset($handle['cc'][$value]);
            //sku是二维数组
            foreach ($handle['sku'] as $k => $v) {
                unset($handle['sku'][$k][$value]);
            }
        }
        return md5(hasSort($handle));
    }

    /**
     * @DOC   : 检验并添加任务
     * @Name  : pushTask
     * @Author: wangfei
     * @date  : 2025-01 10:09
     * @param array $redis_data
     * @return bool|\Redis|string
     *
     */
    public function pushTask(array $redis_data)
    {
        $redis_data = make(LibValidation::class)->validate($redis_data,
            [
                'id'                      => ['integer'],
                'member'                  => ['required', 'array'],
                'member.uid'              => ['required', 'integer'],
                'member.parent_join_uid'  => ['required', 'integer'],
                'member.parent_agent_uid' => ['required', 'integer'],
                'member.role_id'          => ['required', 'integer'],
                'member.warehouse_id'     => ['integer'],

            ]
        );

        $result['job_name'] = 'RiBenDataToBaseJob';
        $result['job_data'] = $redis_data;
        return $this->redis->lPush(TaskCenterPushService::TASK_CENTER_PUSH_KEY, json_encode($result));
    }
}
