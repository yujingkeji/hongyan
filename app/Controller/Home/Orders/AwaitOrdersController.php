<?php

declare(strict_types=1);
/**
 * 待整理的订单、目前主要解决：未备案的订单、这个主要功能是：
 * 1、全球邮寄到中国大陆、需要个物备案的订单
 * 2、订单商品数据在未备案的情况下，可以优先制单、后补充数据，这里是解决补充的问题。
 * This file is part of Hyperf.
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Controller\Home\Orders;

use App\Exception\HomeException;
use App\Service\AwaitOrdersService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

#[Controller(prefix: 'orders/await')]
class AwaitOrdersController extends OrderBaseController
{
    #[RequestMapping(path: 'goods', methods: 'get,post')]
    public function goods(RequestInterface $request)
    {
        $params             = $request->all();
        $member             = $request->UserInfo;
        $awaitOrdersService = make(AwaitOrdersService::class);
        switch ($member['role_id']) {
            case 1:
                $result = $awaitOrdersService->exceptionGoods(params: $params, parent_agent_uid: $member['parent_agent_uid']);
                break;

            case 3: //加盟商
                $result = $awaitOrdersService->exceptionGoods(params: $params, parent_agent_uid: $member['parent_agent_uid'], parent_join_uid: $member['uid']);
                break;
            // 客户
            case 4:
            case 5:
                $result = $awaitOrdersService->exceptionGoods(params: $params, parent_agent_uid: $member['parent_agent_uid'], parent_join_uid: $member['parent_join_uid'], member_uid: $member['uid']);
                break;
            default:
                throw new \Exception('Unexpected value');
        }
        return $this->response->json($result);
    }

    /**
     * @DOC   : 待备订单
     * @Name  : orders
     * @Author: wangfei
     * @date  : 2025-01 15:14
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     * * @throws \Exception
     */
    #[RequestMapping(path: 'orders', methods: 'get,post')]
    public function orders(RequestInterface $request)
    {
        $params             = $request->all();
        $member             = $request->UserInfo;
        $awaitOrdersService = make(AwaitOrdersService::class);
        switch ($member['role_id']) {
            case 1:
                $result = $awaitOrdersService->exceptionOrders(params: $params, parent_agent_uid: $member['parent_agent_uid']);
                break;

            case 3: //加盟商
                $result = $awaitOrdersService->exceptionOrders(params: $params, parent_agent_uid: $member['parent_agent_uid'], parent_join_uid: $member['uid']);
                break;
            // 客户
            case 4:
            case 5:
                $result = $awaitOrdersService->exceptionOrders(params: $params, parent_agent_uid: $member['parent_agent_uid'], parent_join_uid: $member['parent_join_uid'], member_uid: $member['uid']);
                break;
            default:
                throw new \Exception('Unexpected value');
        }
        return $this->response->json($result);
    }

    /**
     * @DOC   : 保存备案
     * @Name  : record
     * @Author: wangfei
     * @date  : 2024-12 11:50
     * @param RequestInterface $request
     * @return void
     *
     */
    #[RequestMapping(path: 'goods/record', methods: 'get,post')]
    public function record(RequestInterface $request)
    {
        $params             = $request->all();
        $member             = $request->UserInfo;
        $awaitOrdersService = make(AwaitOrdersService::class);
        switch ($member['role_id']) {
            case 1:
                $result = $awaitOrdersService->record(params: $params, member: $member, parent_agent_uid: $member['parent_agent_uid']);
                break;

            case 3: //加盟商
                $result = $awaitOrdersService->record(params: $params, member: $member, parent_agent_uid: $member['parent_agent_uid'], parent_join_uid: $member['uid']);
                break;
            // 客户
            case 4:
            case 5:
                $result = $awaitOrdersService->record(params: $params, member: $member, parent_agent_uid: $member['parent_agent_uid'], parent_join_uid: $member['parent_join_uid'], member_uid: $member['uid']);
                break;
            default:
                throw new HomeException('Unexpected value');
        }
        return $this->response->json($result);

    }

    #备案保存并且直接通过
    #[RequestMapping(path: 'goods/record/pass', methods: 'get,post')]
    public function recordToPass(RequestInterface $request)
    {
        $params             = $request->all();
        $member             = $request->UserInfo;
        $awaitOrdersService = make(AwaitOrdersService::class);
        switch ($member['role_id']) {
            case 1:
                $result = $awaitOrdersService->recordToPass(params: $params, member: $member, parent_agent_uid: $member['parent_agent_uid']);
                break;

            case 3: //加盟商
                $result = $awaitOrdersService->recordToPass(params: $params, member: $member, parent_agent_uid: $member['parent_agent_uid'], parent_join_uid: $member['uid']);
                break;
            // 客户
            case 4:
            case 5:
                $result = $awaitOrdersService->recordToPass(params: $params, member: $member, parent_agent_uid: $member['parent_agent_uid'], parent_join_uid: $member['parent_join_uid'], member_uid: $member['uid']);
                break;
            default:
                throw new HomeException('Unexpected value');
        }
        return $this->response->json($result);

    }

    #[RequestMapping(path: 'relevance', methods: 'get,post')]
    public function relevance(RequestInterface $request)
    {
        $params             = $request->all();
        $member             = $request->UserInfo;
        $awaitOrdersService = make(AwaitOrdersService::class);
        switch ($member['role_id']) {
            case 1:
                $result = $awaitOrdersService->recordToRelevance(params: $params, member: $member, parent_agent_uid: $member['parent_agent_uid']);
                break;
            case 3: //加盟商
                $result = $awaitOrdersService->recordToRelevance(params: $params, member: $member, parent_agent_uid: $member['parent_agent_uid'], parent_join_uid: $member['uid']);
                break;
            // 客户
            case 4:
            case 5:
                $result = $awaitOrdersService->recordToRelevance(params: $params, member: $member, parent_agent_uid: $member['parent_agent_uid'], parent_join_uid: $member['parent_join_uid'], member_uid: $member['uid']);
                break;
            default:
                throw new HomeException('备案关联报错');
        }
        return $this->response->json($result);
    }

}
