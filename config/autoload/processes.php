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
return [
    #Hyperf\AsyncQueue\Process\ConsumerProcess::class,
    App\Process\AsyncLogisticsProcess::class,
    App\Process\AsyncSupplementWeightCalcProcess::class,//发货补重结算。
    App\Process\AsyncParcelChannelNodeSwitchProcess::class,//包裹切换节点日志
    App\Process\AsyncBlDoneParcelHandleProcess::class,//提单节点结单 批量提单下的订单切换节点
    App\Process\AsyncOrderToParcelCheckProcess::class,//批量订单转包检查进程
    App\Process\AsyncBillSettlementProcess::class,//账单核算进程
    App\Process\XnPoolProcess::class,//虚拟号池
    App\Process\TaskCenterProcess::class,//任务中心
    #Hyperf\Crontab\Process\CrontabDispatcherProcess::class,//定时任务调度

];
