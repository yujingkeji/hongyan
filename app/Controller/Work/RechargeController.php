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

namespace App\Controller\Work;

use App\Service\RechargeService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: "/", server: 'httpWork')]
class RechargeController extends WorkBaseController
{
    #[Inject]
    protected RechargeService $service;

    /**
     * @DOC 获取order_no单号
     */
    #[RequestMapping(path: "recharge/does", methods: "get,post")]
    public function does(RequestInterface $request): ResponseInterface
    {
        $param    = $request->all();
        $userInfo = $this->request->UserInfo;
        $result   = $this->service->getOrderNo($param, $userInfo);
        return $this->response->json($result);
    }

    /**
     * @DOC 获取微信支付二维码
     */
    #[RequestMapping(path: "recharge/wx/pay", methods: "get,post")]
    public function pay(RequestInterface $request): ResponseInterface
    {
        $param    = $request->all();
        $userInfo = $this->request->UserInfo;
        $result   = $this->service->getWxChatPay($param, $userInfo);
        return $this->response->json($result);
    }

    /**
     * @DOC 支付宝支付二维码
     */
    #[RequestMapping(path: "recharge/ali/pay", methods: "get,post")]
    public function aliPay(RequestInterface $request): ResponseInterface
    {
        $param    = $request->all();
        $userInfo = $this->request->UserInfo;
        $result   = $this->service->getAliPay($param, $userInfo);
        return $this->response->json($result);
    }


}
