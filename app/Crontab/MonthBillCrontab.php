<?php
/**
 * Created by PhpStorm.
 * User yfd
 * Date 2023/11/13
 */

/**
 * *****************************************************************
 * 这不是一个自由软件,谢绝修改再发布.
 * @Created by PhpStorm.
 * @Name    :   MonthlyBillCrontab.php
 * @Email   :   28386631@qq.com
 * @Author  :   wangfei
 * @Date    :   2023/11/13 16:08
 * @Link    :   http://ServPHP.LinkUrl.cn
 * *****************************************************************
 */

namespace App\Crontab;

use App\Service\BillMonthService;
use Carbon\Carbon;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Crontab\Annotation\Crontab;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;


#[Crontab(rule: "* * * * *", name: "MonthBill", callback: "execute", memo: "这是一个月账单提交任务", enable: "isEnable")]
class MonthBillCrontab
{

    protected LoggerInterface  $logger;
    protected BillMonthService $billMonthService;

    public function __construct(ContainerInterface $container)
    {
        $this->logger           = $container->get(LoggerFactory::class)->get('crontab', 'text');
        $this->billMonthService = \Hyperf\Support\make(BillMonthService::class);
    }

    public function execute()
    {
//        /var_dump(Carbon::now()->toDateTimeString());
        //  $this->logger->info(date('Y-m-d H:i:s', time()), [ var_dump(Coroutine::inCoroutine())]);
        $needBillMemberDb = $this->billMonthService->needBillMember(year: 2023,month: 11);


    }

    public function isEnable(): bool
    {
        return true;
    }
}