<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Controller\Home;

use App\Common\Lib\Crypt;
use App\Common\Lib\Str;
use App\Common\Lib\UserDefinedIdGenerator;
use App\JsonRpc\RecordServiceInterface;
use App\JsonRpc\Service\WareService;
use App\Model\AgentMemberModel;
use App\Model\BlModel;
use App\Model\OrderModel;
use App\Model\ParcelExportModel;
use App\Model\ParcelModel;
use App\Process\JobProcess\OrderItemToRecordPassJob;
use App\Process\JobProcess\RiBenDataToBaseJob;
use App\Request\LibValidation;
use App\Service\AnalyseChannelService;
use App\Service\BillMonthService;
use App\Service\BillSettlementService;
use App\Service\Cache\BaseCacheService;
use App\Service\DataHandle\RiBenDbToBaseService;
use App\Service\Express\ExpressService;
use App\Service\Express\OrderToParcelService;
use App\Service\GoodsRecordService;
use App\Service\ParcelChannelNodeSwitchService;
use App\Service\ParcelWeightCalcService;
use App\Service\PrintService;
use App\Service\QueueService;
use App\Service\TaskCenterPushService;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Redis\Redis;
use Hyperf\Snowflake\IdGeneratorInterface;
use Hyperf\Snowflake\MetaGenerator\RedisMetaGenerator;
use Lysice\HyperfRedisLock\RedisLock;
use Psr\Log\LoggerInterface;
use function App\Common\batchUpdateSql;
use function App\Common\Format;


#[Controller(prefix: 'wangfei')]
class AlongController extends AbstractController
{
    #[Inject]
    protected QueueService $queueService;


    protected int $channel_id = 100;


    #[Inject]
    protected BaseCacheService $baseCacheService;
    #[Inject]
    protected Redis $redis;

    protected ExpressService $ExpressService;
    protected Crypt $Crypt;
    protected LoggerInterface $logger;


    function groupBy($data, $field)
    {
        return array_reduce($data, function ($carry, $item) use ($field) {
            if (!isset($item[$field])) {
                throw new InvalidArgumentException("Field '$field' does not exist in item.");
            }
            $key = $item[$field];
            if (!isset($carry[$key])) {
                $carry[$key] = [];
            }
            $carry[$key][] = $item;
            return $carry;
        }, []);
    }

    #[RequestMapping(path: 'get/print', methods: 'get,post')]
    public function getPrint(RequestInterface $request)
    {
        $member               = $request->UserInfo;
        $redis_data['member'] = $member;
        $params               = $request->all();
        return make(PrintService::class)->getPrintData($params, $member);

    }

    /**
     * @DOC   : riben表数据处理
     * @Name  : renben
     * @Author: wangfei
     * @date  : 2025-03 13:19
     * @return mixed
     *
     */
    #[RequestMapping(path: 'riben', methods: 'get,post')]
    public function renben(RequestInterface $request)
    {
        $member               = $request->UserInfo;
        $redis_data['member'] = $member;
        // $v                    = $this->baseCacheService->tariffTaxWithIdCache();

        $v = make(RiBenDataToBaseJob::class)->pushTask($redis_data);

        // $v                    = make(RiBenDataToBaseJob::class)->handleGoods(['id'=>3],$member);
        return $v;
    }


    #[RequestMapping(path: 'country/area', methods: 'get,post')]
    public function countryAray()
    {
        $v = make(BaseCacheService::class)->CountryAreaCache();
        return $v;
    }

    #[RequestMapping(path: 'record/to/origin', methods: 'get,post')]
    public function recordToOrigin(RequestInterface $request)
    {
        $goods_base_id = $request->all()['goods_base_id'];
        $member        = $request->UserInfo;
        return make(GoodsRecordService::class)->localRecordToTargetData(source_base_id: $goods_base_id, member: $member);
    }


    /**
     * @DOC   : 测试仓库包裹查询
     * @Name  : queryParcel
     * @Author: wangfei
     * @date  : 2025-02 15:52
     * @return mixed
     *
     */
    #[RequestMapping(path: 'ware/parcel/query', methods: 'get,post')]
    public function parcelQuery()
    {
        $data = [
            ['sn' => 101, 'sc' => 102, 'desc' => '测试1'],
            ['sn' => 102, 'sc' => 102, 'desc' => '测试2'],
            ['sn' => 103, 'sc' => 104, 'desc' => '测试3'],
            ['sn' => 104, 'sc' => 104, 'desc' => '测试4'],
        ];
        print_r(array_column($data, 'sn'));
        print_r(array_column($data, null, 'sn'));
        print_r(array_column($data, 'sc'));
        print_r(array_column($data, null, 'sc'));

        $mergedData = $this->groupBy($data, 'sc');

// 如果需要将键转换为数字索引
        // $mergedData = array_values($mergedData);

        print_r($mergedData);
        /*  $wareService = \Hyperf\Support\make(WareService::class);
          $redis_data  = $wareService->queryParcel(ak: 'ck-a1RIPyY2EeZ9OKM4iWkvTU6AqBCCBVztdtn4106Z7qEHk49m',
              parcel_sn: '20250211115101');*/

        // print_r($redis_data);


    }

    /**
     * @DOC   : 包裹上架
     * @Name  : parcelUp
     * @Author: wangfei
     * @date  : 2025-02 10:39
     * @return void
     *
     */
    #[RequestMapping(path: 'ware/parcel/up', methods: 'get,post')]
    public function parcelUp()
    {

        $wareService = \Hyperf\Support\make(WareService::class);
        $redis_data  = $wareService->parcelUpBatch(ak: 'ck-a1RIPyY2EeZ9OKM4iWkvTU6AqBCCBVztdtn4106Z7qEHk49m',
            location: 'JH-1-1-1-1-1',
            data: [
                [
                    'parcel_sn' => '20250211115101',
                    'desc'      => '备注'
                ],
                [
                    'parcel_sn' => '20250211115102',
                    'desc'      => '备注'
                ]
            ]);

        print_r($redis_data);


    }

    #[RequestMapping(path: 'redis', methods: 'get,post')]
    public function redis()
    {
        //{"job_name":"OrderItemToRecordPassJob","job_data":{"item_id":1586}}
        $redis = \Hyperf\Support\make(Redis::class);

        $redis_data = $redis->get(TaskCenterPushService::TASK_CENTER_PUSH_KEY);

        print_r('start');
        print_r($redis_data);
        print_r('end');
        return $redis_data;

    }

    /**
     * @DOC   :
     * @Name  : itemRecordPass
     * @Author: wangfei
     * @date  : 2025-01 10:42
     * @return void
     *
     */
    #[RequestMapping(path: 'item/record/pass', methods: 'get,post')]
    public function itemRecordPass()
    {
        $itemRecordPass = make(OrderItemToRecordPassJob::class);
        // $result         = $itemRecordPass->orderItemToRecordPass(orderSysSn: '735856419827703809');
        $result = $itemRecordPass->orderItemToOtherRecordPass(1590);
        print_r($result);

    }

    #[RequestMapping(path: 'snowflake', methods: 'get,post')]
    public function snowflake()
    {
        $generator = \Hyperf\Support\make(UserDefinedIdGenerator::class);
        foreach (range(1, 1000) as $i) {
            $id = $generator->generate(1000, 32);
            print_r($id);
            print_r("__");
            $id = $generator->generate(1028, 10);
            print_r($id);
            print_r("__");
            $id = $generator->generate(2038, 11);
            print_r($id);
            echo PHP_EOL;
        }
        /*       $id = $generator->degenerate($id);
               print_r($id);*/
        //echo PHP_EOL;

    }

    #[RequestMapping(path: 'batchBySkuId', methods: 'get,post')]
    public function batchBySkuId()
    {
        $RecordService = \Hyperf\Support\make(RecordServiceInterface::class);
        $bilMonth      = $RecordService->batchBySkuId([1]);
        return $bilMonth;
    }

    #[RequestMapping(path: 'month', methods: 'get,post')]
    public function month()
    {
        $BillMonthService = \Hyperf\Support\make(BillMonthService::class);
        $bilMonth         = $BillMonthService->needBillMember(year: 2023, month: 11);
        return $bilMonth;
    }


    //获取订单数据
    #[RequestMapping(path: 'geTransport', methods: 'get,post')]
    public function geTransport(RequestInterface $request)
    {

        $order_sys_sn         = $request->all()['order_sys_sn'];
        $this->ExpressService = \Hyperf\Support\make(ExpressService::class, ['TRANSPORT']);
        $this->Crypt          = \Hyperf\Support\make(Crypt::class);
        $this->logger         = \Hyperf\Support\make(LoggerFactory::class)->get('log', 'AsyncLogisticsSeverProcess');
        $this->logger->info($order_sys_sn . '开始取号', [$order_sys_sn]);
        $OrderDb = $this->ExpressService->getOrderData($order_sys_sn);
        $this->logger->info('$OrderDb', $OrderDb);
        return $OrderDb;
    }

    #[RequestMapping(path: 'xuniwl', methods: 'get,post')]
    public function xuniwl(RequestInterface $request)
    {
        $order_sys_sn = $request->all();
        $order_sys_sn = '712685417737048064';

        $this->ExpressService = \Hyperf\Support\make(ExpressService::class, ['TRANSPORT']);
        $this->Crypt          = \Hyperf\Support\make(Crypt::class);
        //$this->logger         = \Hyperf\Support\make(LoggerFactory::class)->get('log', 'AsyncLogisticsSeverProcess');
        //$this->logger->info($order_sys_sn . '开始取号', [$order_sys_sn]);
        $OrderDb = $this->ExpressService->getOrderData($order_sys_sn);

        $Method        = 'XUNIWL';
        $Job           = 'App\Service\Express\Job\\' . $Method;
        $ContainerJob  = \Hyperf\Support\make($Job, [$OrderDb]);
        $ContainerData = $ContainerJob->OrderCreate($OrderDb);
        $content       = json_encode($ContainerData, JSON_UNESCAPED_UNICODE);
        print_r($content);

    }

    #[RequestMapping(path: 'amount', methods: 'get,post')]
    public function amount(RequestInterface $request)
    {
        $memberWhere['member_uid']       = 113;
        $memberWhere['parent_agent_uid'] = 16;
        //  $result                          = AgentMemberModel::query()->where($memberWhere)->selectRaw('amount+warning_amount as amount');
        $result = AgentMemberModel::query()->where($memberWhere)
            ->selectRaw('(amount + warning_amount) as amount')->first();

        return $result->amount;
    }

    /**
     * @DOC redis分布式锁测试
     * @Name   lock
     * @Author wangfei
     * @date   2023/10/14 2023
     * @return bool|mixed
     */
    #[RequestMapping(path: 'lock', methods: 'get,post')]
    public function lock()
    {
        $order_sys_sn = 'wangfei';
        $lock         = new RedisLock($this->redis, $order_sys_sn, 10);
        $singleResult = $lock->get(callback: function () {
            sleep(10);
            return [123];
        });
        return $singleResult;

    }

    /**
     * @DOC  测试加密
     * @Name   encrypt
     * @Author wangfei
     * @date   2023/10/8 2023
     * @return array
     * @throws \Exception
     */
    #[RequestMapping(path: 'encrypt', methods: 'get,post')]
    public function encrypt()
    {
        $tel                         = "13866618080";
        $Crypt                       = new Crypt();
        $encryptStr                  = $Crypt->encrypt($tel);
        $result['encryptStr']        = $encryptStr;
        $encryptStr_Base64           = base64_encode($encryptStr);
        $result['encryptStr_Base64'] = $encryptStr_Base64;
        $decryptStr_Base64           = base64_decode($encryptStr_Base64);
        $result['decryptStr_Base64'] = $decryptStr_Base64;
        $result['decryptStr']        = $Crypt->decrypt($decryptStr_Base64);
        return $result;
    }

    /**
     * @DOC 补重任务测试
     * @Name   supplement
     * @Author wangfei
     * @date   2023-09-19 2023
     * @param RequestInterface $request
     * @return array
     */
    #[RequestMapping(path: 'supplement', methods: 'get,post')]
    public function supplement(RequestInterface $request)
    {

        $member                  = $request->UserInfo;
        $LibValidation           = \Hyperf\Support\make(LibValidation::class);
        $params                  = $LibValidation->validate($request->all(), [
            // 'order_sys_sn'   => ['required', 'array'],
            'order_sys_sn' => ['required', 'string'],
            'weight'       => ['required'],
        ]);
        $result                  = [];
        $logger                  = $this->container->get(LoggerFactory::class)->get('log', 'AsyncSupplementWeightCalcProcess');
        $parcelWeightCalcService = \Hyperf\Support\make(ParcelWeightCalcService::class, [$logger]);
        $member_uid              = 22;
        /*foreach ($params['order_sys_sn'] as $key => $order_sys_sn) {
            $item     = $parcelWeightCalcService->lPush((string)$order_sys_sn, $member_uid, 9, 9);
            $result[] = $item;
        }*/
        $supplementWeightCalc = $parcelWeightCalcService->supplementWeightCalc($params['order_sys_sn'], $params['weight'], $params['weight']);
        if ($supplementWeightCalc['code'] == 200 && $supplementWeightCalc['memberSupplementFee'] > 0) {
            $supplementWeightCalcToSave = $parcelWeightCalcService->supplementWeightCalcToSave($supplementWeightCalc);
            print_r($supplementWeightCalcToSave);
        }

        return $supplementWeightCalc;
    }

    #[RequestMapping(path: 'parcel/pack/confirm', methods: 'get,post')]
    public function parcelPackConfirm(RequestInterface $request)
    {
        $param      = $request->all();
        $order      = OrderModel::query()->where('order_sys_sn', $param['order_sys_sn'])->first()->toArray();
        $memberCalc = [
            'member_uid'       => $order['member_uid'],
            'uid'              => $order['member_uid'],
            'parent_join_uid'  => $order['parent_join_uid'],
            'parent_agent_uid' => $order['parent_agent_uid'],
            'role_id'          => 3,
        ];
        $logger                  = \Hyperf\Support\make(LoggerFactory::class)->get('log', 'AsyncSupplementWeightCalcProcess');
        $parcelWeightCalcService = \Hyperf\Support\make(ParcelWeightCalcService::class, [$logger, $memberCalc]);
       $v= $parcelWeightCalcService->orderToParcelCalc([$param['order_sys_sn']], $memberCalc);
       print_r($v);
    }

    /**
     * @DOC 账单结算测试
     * @Name   BillSettlement
     * @Author wangfei
     * @date   2023-09-19 2023
     * @param RequestInterface $request
     * @return array
     */
    #[RequestMapping(path: 'BillSettlement', methods: 'get,post')]
    public function BillSettlement(RequestInterface $request)
    {
        $result['code']        = 201;
        $result['msg']         = '操作失败';
        $member                = $request->UserInfo;
        $LibValidation         = \Hyperf\Support\make(LibValidation::class);
        $params                = $LibValidation->validate($request->all(), [
            'order_sys_sn'   => ['required', 'array'],
            'order_sys_sn.*' => ['required', 'string'],
        ]);
        $logger                = \Hyperf\Support\make(LoggerFactory::class)->get('log', 'AsyncBillSettlementProcess');
        $BillSettlementService = \Hyperf\Support\make(BillSettlementService::class, [$member]);
        $BillSettlementService->logger($logger);
        $settlement = $BillSettlementService->settlement($params['order_sys_sn']); //直接计算
        // $settlement = $BillSettlementService->lPush(order_sys_sn: $params['order_sys_sn']);//加入队列 异步计算
        return $settlement;
    }

    /**
     * @DOC  转包检测
     * @Name   orderToParcle
     * @Author wangfei
     * @date   2023-08-12 2023
     * @param RequestInterface $request
     */
    #[RequestMapping(path: 'orderToParcel', methods: 'get,post')]
    public function orderToParcel(RequestInterface $request)
    {
        $result['code']        = 201;
        $result['msg']         = '操作失败';
        $AnalyseChannelService = \Hyperf\Support\make(AnalyseChannelService::class);

        $param = $request->all();
        if ($AnalyseChannelService->lPush($param, 22)) {
            $result['code'] = 200;
            $result['msg']  = '操作成功';
        }
        return $result;
    }

    /**
     * @DOC   渠道数据切换
     * @Name   test
     * @Author wangfei
     * @date   2023-08-09 2023
     * @param RequestInterface $request
     * @return array
     */
    #[RequestMapping(path: 'nodeSwitch', methods: 'get,post')]
    public function nodeSwitch(RequestInterface $request)
    {
        //  $where['transport_sn'] = 16896642890001;
        $where['order_sys_sn'] = 16896642890001;
        $order_sys_sn          = 16896642890001;
        $ParcelDb              = ParcelModel::query()->with(['send'])->where($where)->first()->toArray();
        $channel_content       = json_decode($ParcelDb['channel_content'], true);
        // $channel_content                = [];
        $parcelChannelNodeSwitchService = \Hyperf\Support\make(ParcelChannelNodeSwitchService::class);
        $parcelChannelNodeSwitchService->channelNodeInit(channel_id: $ParcelDb['channel_id'], channelData: $channel_content);

        $nextNode            = $parcelChannelNodeSwitchService->nextNode(5);
        $ExpressData['data'] = json_decode('{"mailno":"7790040903278","tp_waybill_no":"7790040903278","package_wdjc":"青岛-发出","position":"482C","position_no":"V093-00 85","tid":"16896642890001"}', true);

        $this->ExpressService = \Hyperf\Support\make(ExpressService::class, ['TRANSPORT']);
        $OrderDb              = $this->ExpressService->getOrderData($order_sys_sn);
        return [
            // 'channelNodeSort' => $parcelChannelNodeSwitchService->channelNodeSort,
            //  'ParcelDb'        => $ParcelDb,
            //  'channel_content' => $channel_content,
            'nextNode1' => $nextNode,
            //   'nextNodeSend'    => $parcelChannelNodeSwitchService->nextNodeSend(nodeSourceDb: $nextNode['nodeSourceDb'], OrderDb: $OrderDb, ExpressData: $ExpressData)
            // 'nextNodeExport' => $parcelChannelNodeSwitchService->nextNodeTransport(nodeSourceDb: $nextNode['nodeSourceDb'], ParcelDb: $ParcelDb)
        ];
    }


    #[RequestMapping(path: '', methods: 'get,post')]
    public function index(RequestInterface $request)
    {
        $v = 100;
        $v = Format($v, 2);
        return ['v' => $v];
    }

    #[RequestMapping(path: 'push', methods: 'get,post')]
    public function push()
    {
        $data           = '{"bl_sn":"542729200562798593","table":"parcel_export"}';
        $data           = json_decode($data, true);
        $where['bl_sn'] = '542729200562798593';
        $v              = ParcelExportModel::query()->where($where)->select(['order_sys_sn', 'member_uid', 'parent_join_uid', 'parent_agent_uid', 'sort'])
            ->get();
        return [
            'data' => $data,
            'v'    => $v
        ];
    }

    #[PostMapping(path: 'bill')]
    public function bill()
    {
        $redis_key            = 'queues:AsyncCalFinanceBillProcess';
        $data['order_sys_sn'] = '16920003600001';
        $ret                  = $this->redis->lPush($redis_key, json_encode($data)); // 将订单丢入到取号队列中
        var_dump($ret);
    }

}
