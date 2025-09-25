<?php

namespace App\Controller\App\Orders;

use App\Controller\Home\HomeBaseController;
use App\Service\ParcelService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

#[Controller(prefix: 'app/orders/parcel')]
class ParcelController extends HomeBaseController
{
    /**
     * @DOC 发往货站
     */
    #[RequestMapping(path: 'send', methods: 'post')]
    public function deliverStation(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(ParcelService::class)->parcelSend($param, $member, false);
        return $this->response->json($result);
    }

}
