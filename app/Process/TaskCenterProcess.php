<?php
/**
 * 任务中心，处理一些小任务
 */
declare(strict_types=1);

namespace App\Process;

use App\Constants\Logger;
use App\Model\XnPoolModel;
use App\Service\TaskCenterPushService;
use App\Service\XnPoolService;
use Hyperf\Process\AbstractProcess;

class TaskCenterProcess extends AbstractProcess
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
    public string $name = 'TaskCenterProcess';

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
    public bool $enableCoroutine = true;
    protected string $redis_key = TaskCenterPushService::TASK_CENTER_PUSH_KEY;

    public function handle(): void
    {
        $redis = \Hyperf\Support\make(\Redis::class);
        while (true) {
            $redis_data = $redis->rPop($this->redis_key);
            if ($redis_data) {
                $redis_data    = json_decode($redis_data, true);
                $job_name      = $redis_data['job_name'];
                $methodService = \Hyperf\Support\make('App\\Process\\JobProcess\\' . $job_name, []);

                $methodService->handle($redis_data['job_data']);
            } else {
                sleep(30); // 不需要频繁查看动态号池（Redis）数量
            }
        }
    }
}
