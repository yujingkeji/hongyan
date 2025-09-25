<?php

namespace App\Controller\Home\Member;

use App\Controller\Home\HomeBaseController;
use App\Service\CouponsService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

#[Controller(prefix: "member/coupons")]
class CouponsController extends HomeBaseController
{

    /**
     * @DOC 优惠券列表
     */
    #[RequestMapping(path: "", methods: "post")]
    public function index()
    {
        $params = $this->request->all();
        $member = $this->request->UserInfo;
        $result = make(CouponsService::class)->getCoupons($member, $params);
        return $this->response->json($result);
    }

    /**
     * @DOC   : 修改状态
     * @Name  : status
     * @Author: wangfei
     * @date  : 2025-02 17:17
     * @return \Psr\Http\Message\ResponseInterface
     *
     */
    #[RequestMapping(path: "status", methods: "post")]
    public function status()
    {
        $params = $this->request->all();
        $member = $this->request->UserInfo;
        $result = make(CouponsService::class)->updateCoupons(member: $member, params: $params);
        return $this->response->json($result);
    }

    /**
     * @DOC   : 是否显示
     * @Name  : show
     * @Author: wangfei
     * @date  : 2025-02 17:17
     * @return \Psr\Http\Message\ResponseInterface
     *
     */
    #[RequestMapping(path: "show", methods: "post")]
    public function show()
    {
        $params = $this->request->all();
        $member = $this->request->UserInfo;
        $result = make(CouponsService::class)->updateCoupons(member: $member, params: $params);
        return $this->response->json($result);
    }

    /**
     * @DOC 创建优惠券
     */
    #[RequestMapping(path: "add", methods: "post")]
    public function add()
    {
        $params = $this->request->all();
        $member = $this->request->UserInfo;
        $result = make(CouponsService::class)->addCoupon($member, $params);
        return $this->response->json($result);
    }

    /**
     * @DOC   :删除优惠券
     * @Name  : delete
     * @Author: wangfei
     * @date  : 2025-02 17:20
     * @return \Psr\Http\Message\ResponseInterface
     *
     */
    #[RequestMapping(path: "delete", methods: "post")]
    public function delete()
    {
        $params = $this->request->all();
        $member = $this->request->UserInfo;
        $result = make(CouponsService::class)->deleteCoupons(member: $member, params: $params);
        return $this->response->json($result);
    }


    /**
     * @DOC 优惠券领取
     */
    #[RequestMapping(path: "receive", methods: "post,get")]
    public function receive()
    {
        $params         = $this->request->all();
        $member         = $this->request->UserInfo;
        $couponsService = \Hyperf\Support\make(CouponsService::class);
        $ret            = $couponsService->receiveCoupons($member, $params);
        return $this->response->json($ret);
    }




}
