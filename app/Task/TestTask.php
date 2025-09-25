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

namespace App\Task;

use Hyperf\Coroutine\Coroutine;
use Hyperf\Task\Annotation\Task;


class TestTask
{
    #[Task]
    public function handle($cid)
    {
        return [
            'worker.cid' => $cid,
            'parentId'   => Coroutine::parentId(),
            'status'     => Coroutine::stats(),
            // task_enable_coroutine 为 false 时返回 -1，反之 返回对应的协程 ID
            'task.cid'   => Coroutine::id(),
        ];
    }


}
