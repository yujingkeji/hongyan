<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 *
 * 取号任务
 */

namespace App\Process;

use App\Common\Lib\Arr;
use App\Common\Lib\Crypt;
use App\Common\Lib\Str;
use App\Exception\HomeException;
use App\Model\DeliveryStationModel;
use App\Service\Express\ExpressService;
use App\Service\OrderToParcelService;
use App\Service\ParcelChannelNodeSwitchService;
use Hyperf\DbConnection\Db;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Process\AbstractProcess;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class AsyncLogisticsProcess extends AbstractProcess
{
    /**
     * 进程数量.
     */
    public int $nums = 1;

    /**
     * 进程名称.
     */
    public string $name = 'AsyncLogisticsSeverProcess';

    /**
     * 重定向自定义进程的标准输入和输出.
     */
    public bool $redirectStdinStdout = false;

    /**
     * 管道类型.
     */
    public int $pipeType = 2;

    /**
     * 是否启用协程.
     */
    public bool $enableCoroutine = true;

    protected ExpressService $ExpressService;
    protected Crypt $Crypt;
    protected LoggerInterface $logger;

    public function handle(): void
    {
        $redis_key            = 'queues:AsyncLogisticsSeverProcess';
        $redis                = $this->container->get(\Redis::class);
        $this->ExpressService = \Hyperf\Support\make(ExpressService::class, ['TRANSPORT']);
        $this->Crypt          = \Hyperf\Support\make(Crypt::class);
        $this->logger         = \Hyperf\Support\make(LoggerFactory::class)->get('log', 'AsyncLogisticsSeverProcess');
        while (true) {
            $redis_data = $redis->rPop($redis_key);
            // $order_sys_sn = true;
            if ($redis_data) {
                $redis_data = json_decode($redis_data, true);
                $this->handleOrderToParcel($redis_data, 2);
            }
            if (empty($redis_data)) {
                sleep(1);
            }
        }
    }

    /**
     * @DOC 订单转包 操作提取
     */
    public function handleOrderToParcel(array $redis_data, $type)
    {
        try {
            // 1 ： 同步处理  2：异步处理
            if ($type == 1) {
                $this->ExpressService = \Hyperf\Support\make(ExpressService::class, ['TRANSPORT']);
                $this->Crypt          = \Hyperf\Support\make(Crypt::class);
                $this->logger         = \Hyperf\Support\make(LoggerFactory::class)->get('log', 'AsyncLogisticsSeverProcess');
            }
            $order_sys_sn = $redis_data['order_sys_sn'];
            $this->logger->info($order_sys_sn . '开始取号', $redis_data);
            $OrderDb = $this->ExpressService->getOrderData($order_sys_sn);

//            if (isset($OrderDb) && $OrderDb['order_status'] == 28) {
//                throw new HomeException('订单未支付，请先进行支付');
//            }
//            //支付完成的才取号
//            if (isset($OrderDb) && $OrderDb['order_status'] == 29) {}
            $orderParcelLog['order_sys_sn']     = $order_sys_sn;
            $orderParcelLog['member_uid']       = $OrderDb['member_uid'];
            $orderParcelLog['parent_join_uid']  = $OrderDb['parent_join_uid'];
            $orderParcelLog['parent_agent_uid'] = $OrderDb['parent_agent_uid'];
            $orderParcelLog['op_member_uid']    = Arr::has($redis_data, 'member_uid') ? $redis_data['member_uid'] : 0;
            $orderParcelLog['op_child_uid']     = Arr::has($redis_data, 'child_uid') ? $redis_data['child_uid'] : 0;
            $orderParcelLog['add_time']         = time();
            $orderParcelLog['create_year']      = date("Y");
            $OrderDb['sender']                  = $this->handleDecrypt($OrderDb['sender']);
            $OrderDb['receiver']                = $this->handleDecrypt($OrderDb['receiver']);
            $Method                             = Str::upper($OrderDb['tp']['platform_code']);
            $this->logger->info($order_sys_sn . '开始取号-TP信息', $OrderDb['tp']);
            try {
                $Job          = 'App\Service\Express\Job\\' . $Method;
                $ContainerJob = \Hyperf\Support\make($Job, [$OrderDb]);

            } catch (\Throwable $e) {
                $err         = [];
                $err['msg']  = $e->getMessage();
                $err['line'] = $e->getLine();
                $err['file'] = $e->getFile();
                $this->logger->info($order_sys_sn . '取号报错', $err);
                $orderParcelLog['msg']     = '当前Job：' . $Job . ' 可能不存在、请检查->' . $e->getMessage() . 'line:' . $e->getLine();
                $orderParcelLog['content'] = '';
            }


            if (isset($ContainerJob)) {
                $ContainerData             = $ContainerJob->OrderCreate($OrderDb);
                $orderParcelLog['content'] = json_encode($ContainerData, JSON_UNESCAPED_UNICODE);
                $orderParcelLog['msg']     = ($ContainerData['status'] == 200) ? '已取号' . '(' . ($OrderDb['tp']['platform_name'] ?? '') . '：' . $ContainerData['data']['tp_waybill_no'] . ')' : '取号失败：' . $ContainerData['msg'];
                // 虚拟物流
                if ($OrderDb['tp']['platform_id'] == 9) {
                    $orderParcelLog['msg'] = ($ContainerData['status'] == 200) ? '支付成功，等待送往集货仓' : '取号失败：' . $ContainerData['msg'];
                }
                $this->logger->info($order_sys_sn . '取号完成：', $ContainerData);
                $orderParcelLog['code'] = '29'; // 操作代码
                if ($ContainerData['status'] == 200) {
                    $orderParcelLog['code']       = '42'; // 操作代码
                    $orderUpdate['order_status']  = 30;//转包完成
                    $orderUpdate['transfer_time'] = time();
                    //包裹信息
                    $parcelDate['order_sys_sn']            = $redis_data['order_sys_sn'];
                    $parcelDate['transport_sn']            = $ContainerData['data']['tp_waybill_no']; //落地运单号
                    $parcelDate['member_uid']              = $OrderDb['member_uid'];
                    $parcelDate['parent_join_uid']         = $OrderDb['parent_join_uid'];
                    $parcelDate['parent_agent_uid']        = $OrderDb['parent_agent_uid'];
                    $parcelDate['batch_sn']                = Arr::has($redis_data, 'batch_sn') ? $redis_data['batch_sn'] : 0;
                    $parcelDate['line_id']                 = $OrderDb['line_id'];
                    $parcelDate['ware_id']                 = $OrderDb['ware_id'];
                    $parcelDate['parcel_status']           = 42; //取号成功 TODO 请查看接口：{{host}}/base.cfg/state
                    $parcelDate['parcel_logistics']        = json_encode($ContainerData['data'], JSON_UNESCAPED_UNICODE);
                    $OrderTpDb                             = Arr::has($OrderDb, 'tp') ? $OrderDb['tp'] : [];
                    $parcelDate['logistics_platform_id']   = Arr::has($OrderTpDb, 'platform_id') ? $OrderTpDb['platform_id'] : 0;
                    $parcelDate['logistics_platform_name'] = Arr::has($OrderTpDb, 'platform_name') ? $OrderTpDb['platform_name'] : "";
                    $parcelDate['logistics_platform_code'] = Arr::has($OrderTpDb, 'platform_code') ? $OrderTpDb['platform_code'] : "";
                    $parcelDate['product_id']              = $OrderDb['pro_id'];
                    $parcelDate['channel_id']              = $OrderDb['channel_id'];
                    $parcelDate['add_time']                = time();

                    //send数据
                    $parcelChannelNodeSwitchService = \Hyperf\Support\make(ParcelChannelNodeSwitchService::class);
                    $parcelChannelNodeSwitchService->channelNodeInit(channel_id: $OrderDb['channel_id']);
                    $nextNode                      = $parcelChannelNodeSwitchService->nextNode(0);
                    $parcelDate['channel_content'] = Arr::has($nextNode, 'channelDb') ? json_encode($nextNode['channelDb'], JSON_UNESCAPED_UNICODE) : '';
                    if (Arr::has($nextNode, 'nodeSourceDb')) {
                        $parcelSend             = $parcelChannelNodeSwitchService->nextNodeSend(nodeSourceDb: $nextNode['nodeSourceDb'], OrderDb: $OrderDb, ExpressData: $ContainerData);
                        $channelSend            = $nextNode['nodeSourceDb'];
                        $parcelDate['idx_sort'] = Arr::has($channelSend, 'sort') ? $channelSend['sort'] : 0; //当前业务操作节点
                    }
                }
            }

            //不管实物如何。先将日志写入
            if (isset($orderParcelLog)) {
                Db::table('order_parcel_log')->insert($orderParcelLog);
            }
            if (isset($OrderDb)) {
                Db::beginTransaction();
                try {
                    if (!empty($parcelDate)) {
                        // 查询包裹 直邮\集运
                        $parcelType = Db::table('delivery_station')->where('order_sys_sn', $OrderDb['order_sys_sn'])->value('parcel_type');
                        if ($parcelType == DeliveryStationModel::TYPE_COLLECT) {
                            $parcelDate['parcel_status'] = 55; // 集运作业
                        }
                        Db::table('parcel')->updateOrInsert(['order_sys_sn' => $parcelDate['order_sys_sn']], $parcelDate);
                    }

                    if (!empty($parcelSend)) {
                        Db::table('parcel_send')->updateOrInsert(['order_sys_sn' => $parcelSend['order_sys_sn']], $parcelSend);
                    }
                    if (!empty($orderUpdate)) {
                        Db::table('order')->where('order_sys_sn', '=', $OrderDb['order_sys_sn'])->update($orderUpdate);
                    }

                    Db::commit();
                } catch (\Throwable $e) {
                    Db::rollBack();
                    $orderParcelLog['add_time'] = time();
                    $orderParcelLog['content']  = $e->getMessage();
                    $orderParcelLog['msg']      = "取号成功-保存报错";
                    $orderParcelLog['log_code'] = '10001';
                    Db::table('order_parcel_log')->insert($orderParcelLog);
                    $this->logger->info($order_sys_sn . '取号完成->保存报错：', $orderParcelLog);
                    throw new HomeException('订单转包裹保存错误：' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            // 当同步处理时，抛出异常
            if ($type == 1) {
                throw new HomeException('取号异常' . $e->getMessage() . $e->getLine());
            }
            $this->logger->info('取号异常' . $e->getMessage() . ';File:' . $e->getFile() . ';Line:' . $e->getLine(), [$redis_data,]);
        }
    }


    /**
     * @DOC 解密
     * @Name   handleDecrypt
     * @Author wangfei
     * @date   2023-06-13 2023
     * @param array $Address
     * @param false $Star
     * @return array
     */
    public function handleDecrypt(array $Address, bool $Star = false)
    {

        $name = '';
        if (Arr::hasArr($Address, 'name')) {
            $name = base64_decode($Address["name"]);
            $name = $this->Crypt->decrypt($name);
            $name = ($Star) ? Str::centerStar($name) : $name;
        }
        $Address['name'] = $name;
        $phone           = '';
        if (Arr::hasArr($Address, 'phone')) {
            $phone = base64_decode($Address["phone"], true);
            $phone = $this->Crypt->decrypt($phone);
            $phone = ($Star) ? Str::centerStar($phone) : $phone;
        }
        $Address['phone'] = $phone;
        $mobile           = '';
        if (Arr::hasArr($Address, 'mobile')) {
            $mobile = base64_decode($Address["mobile"]);
            $mobile = $this->Crypt->decrypt($mobile);
            $mobile = ($Star) ? Str::centerStar($mobile) : $mobile;
        }
        $Address['mobile'] = $mobile;
        return $Address;
    }
}
