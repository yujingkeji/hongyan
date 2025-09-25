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

namespace App\Controller\Admin;

use App\Model\MemberAuthRoleModel;
use App\Service\Cache\BaseCacheService;
use App\Service\OrderToParcelService;
use App\Service\ParcelWeightCalcService;
use App\Service\QueueService;
use App\Task\TestTask;
use Hyperf\Context\ApplicationContext;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Redis\Redis;
use Psr\Container\ContainerInterface;

#[Controller(prefix: '/', server: 'httpAdmin')]
class TaskController extends AdminBaseController
{
    #[Inject]
    protected TestTask $task;

    #[Inject]
    protected OrderToParcelService $orderToParcelService;


    #[RequestMapping(path: 'task/index', methods: 'get,post')]
    public function index(RequestInterface $request)
    {
        $member_uid = 21;
        $child_uid  = 0;

        $redis_key   = 'queues:AsyncLogisticsSeverProcess';
        $container   = ApplicationContext::getContainer();
        $this->redis = $container->get(Redis::class);
        //  $result = $this->task->handle(Coroutine::id());
        $data['order_sys_sn'] = 16865514820001;
        $data['member_uid']   = 21;
        $data['child_uid']    = 0;
        $data['batch_sn']     = 0;

        $this->redis->lPush($redis_key, json_encode($data)); // 将订单丢入到取号队列中
        // $this->orderToParcelService->lPush(16865514820001, $member_uid, $child_uid, 100);
        return $this->response->json(['成功']);
    }

    #[RequestMapping(path: 'task/supple', methods: 'get,post')]
    public function supple(RequestInterface $request)
    {
        $this->logger            = $this->container->get(LoggerFactory::class)->get('log', 'AsyncSupplementWeightCalcProcess');
        $parcelWeightCalcService = \Hyperf\Support\make(ParcelWeightCalcService::class, [$this->logger]);
        $member_uid              = 22;
        $parcelWeightCalcService->lPush(16896642890001, $member_uid, 9);
        return $this->response->json(['成功']);
    }

}
