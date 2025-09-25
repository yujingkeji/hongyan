<?php

namespace App\Controller\Home\Prediction;

use App\Controller\Home\HomeBaseController;
use App\Service\PredictionParcelService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

/**
 * 集运订单详情
 */
#[Controller(prefix: 'prediction/parcel')]
class ParcelController extends HomeBaseController
{
    /**
     * @DOC 集运包裹列表
     */
    #[RequestMapping(path: 'lists', methods: 'post')]
    public function parcelLists(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->consolidationList($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 集运包裹详情
     */
    #[RequestMapping(path: 'detail', methods: 'post')]
    public function parcelDetail(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->parcelDetail($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC  预报下单
     */
    #[RequestMapping(path: 'forecast', methods: 'post')]
    public function forecast(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->forecast($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 填补单号
     */
    #[RequestMapping(path: 'edit/logistics', methods: 'post')]
    public function editLogistics(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->editLogistics($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 废弃订单
     */
    #[RequestMapping(path: 'abandon/logistics', methods: 'post')]
    public function abandonLogistics(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->abandonLogistics($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 删除订单
     */
    #[RequestMapping(path: 'del', methods: 'post')]
    public function parcelDel(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->parcelDel($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 申请验货
     */
    #[RequestMapping(path: 'verify/goods', methods: 'post')]
    public function verifyGoods(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->verifyGoods($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 申请出库
     */
    #[RequestMapping(path: 'outbound', methods: 'post')]
    public function outbound(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->outbound($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 待认领包裹列表
     */
    #[RequestMapping(path: 'claim/lists', methods: 'post')]
    public function claimLists(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->claimLists($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 用户领取认领包裹
     */
    #[RequestMapping(path: 'claim/collect', methods: 'post')]
    public function claimCollect(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->claimCollect($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 取消出库
     */
    #[RequestMapping(path: 'cancel/outbound', methods: 'post')]
    public function cancelOutbound(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->cancelOutbound($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 单个商品修改
     */
    #[RequestMapping(path: 'edit/goods', methods: 'post')]
    public function editGoods(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->editGoods($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 同步仓库验收数据
     */
    #[RequestMapping(path: 'edit/all/goods', methods: 'post')]
    public function editAllGoods(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->editAllGoods($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 领取包裹后补充商品信息
     */
    #[RequestMapping(path: 'supplement/goods', methods: 'post')]
    public function supplementGoods(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->supplementGoods($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 包裹验货列表
     */
    #[RequestMapping(path: 'verify/lists', methods: 'post')]
    public function verifyLists(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->verifyLists($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 包裹取消验货
     */
    #[RequestMapping(path: 'verify/cancel', methods: 'post')]
    public function verifyCancel(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->verifyCancel($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 修改预报包裹转成订单后的包裹改成正常状态
     */
    #[RequestMapping(path: 'correct/status', methods: 'post')]
    public function parcelCorrectStatus(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->parcelCorrectStatus($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 批量预报
     */
    #[RequestMapping(path: 'batch/forecast', methods: 'post')]
    public function batchForecast(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PredictionParcelService::class)->batchForecast($params, $member);
        return $this->response->json($result);
    }


}
