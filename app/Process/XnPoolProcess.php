<?php
/**
 * 监听虚拟物流号进程
 */
declare(strict_types=1);

namespace App\Process;

use App\Model\XnPoolModel;
use App\Service\XnPoolService;
use Hyperf\Process\AbstractProcess;

class XnPoolProcess extends AbstractProcess
{
    /**
     * 进程数量
     * @var int
     */
    public int $nums = 1;

    /**
     * 进程名称
     * @var string
     */
    public string $name = 'process-create-no-pool';

    /**
     * 重定向自定义进程的标准输入和输出
     * @var bool
     */
    public bool $redirectStdinStdout = false;

    /**
     * 管道类型
     * @var int
     */
    public int $pipeType = 2;

    /**
     * 是否启用协程
     * @var bool
     */
    public bool      $enableCoroutine = true;
    protected string $redis_key       = 'XN_POOL_WAYBILL_NO';

    public function handle(): void
    {

        $limit  = 200; // 动态号池中的最大数量
        $length = 10000; // 静态号池一次补充的数量

        $redis          = \Hyperf\Support\make(\Redis::class);
        $WaybillService = \Hyperf\Support\make(XnPoolService::class);

        while (true) {
            $poolLen = $redis->lLen($this->redis_key);
            $sign    = $redis->get($this->redis_key . '_SIGN');
            // 当动态号池（Redis）中的剩余条数不足 20 时，需要从静态号池（MySQL）中补充进来
            if ($poolLen < 80 || $sign) {
                // 删除标记
                $limit = $sign ?: $limit; // 这是为了可以方便控制增加动态号池的数量
                $redis->del($this->redis_key . '_SIGN');
                // 查找静态号池（MySQL）中未使用的前 100 条数据补充到动态号池（Redis）中
                $column = XnPoolModel::where('status', 0)->limit($limit)->pluck('waybill_no', 'id')->toArray();
                $ids    = array_keys($column);
                $newNos = array_values($column);
                if (count($newNos) < $limit) {
                    // 说明静态号池中数据不充裕，需要补充静态号池

                    $WaybillService->setWaybillNo($length); // 补充号池
                } else {
                    // 说明静态号池中数据暂充裕，直接讲取到的数据补充到动态号池中即可。
                    $redis->lPush($this->redis_key, ...$newNos);
                    XnPoolModel::whereIn('id', $ids)->update(['status' => 2]); // 将数据改为待使用状态
                }
                unset($column, $ids, $newNos);
            } else {
                sleep(2); // 不需要频繁查看动态号池（Redis）数量
            }
        }
    }
}