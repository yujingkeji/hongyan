<?php

namespace App\Controller\App\Member;

use App\Controller\Home\HomeBaseController;
use App\Model\CouponsMemberModel;
use App\Model\CouponsModel;
use App\Request\LibValidation;
use App\Service\CouponsService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

#[Controller(prefix: 'app/member/coupons')]
class CouponsController extends HomeBaseController
{
    /**
     * @DOC 代理所创建的优惠卷
     */
    #[RequestMapping(path: '', methods: 'post')]
    public function index(RequestInterface $request)
    {
        $params = $this->request->all();
        $member = $this->request->UserInfo;
        $result = make(CouponsService::class)->getCoupons($member, $params);
        return $this->response->json($result);
    }

    /**
     * @DOC 领取优惠卷
     */
    #[RequestMapping(path: 'receive', methods: 'post')]
    public function receive(RequestInterface $request)
    {
        $params        = $this->request->all();
        $member        = $this->request->UserInfo;
        $couponService = \Hyperf\Support\make(CouponsService::class);
        $ret           = $couponService->receiveCoupons($member, $params);
        return $this->response->json($ret);
    }



}
