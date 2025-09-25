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

namespace App\Controller\Home\Orders;

use App\Common\Lib\Arr;
use App\Common\Lib\Str;
use App\Common\Lib\UserDefinedIdGenerator;
use App\Controller\Home\AbstractController;
use App\Exception\HomeException;
use App\Model\OrderPaymentModel;
use App\Model\ParcelModel;
use App\Model\PriceTemplateModel;
use App\Request\LibValidation;
use App\Request\OrdersRequest;
use App\Service\Express\ExpressService;
use App\Service\OrderToParcelService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\Rule;
use PharIo\Version\Exception;
use phpseclib3\Math\BigInteger\Engines\PHP;

#[Controller(prefix: 'orders/payment')]
class PaymentController extends OrderBaseController
{
    #[Inject]
    protected OrderToParcelService $orderToParcelService;

    #[Inject]
    protected ValidatorFactoryInterface $validationFactory;

    /**
     * @DOC  【hyperf】重新取号-支付完成
     * @Name   afresh
     * @Author wangfei
     * @date   2023-09-15 2023
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'afresh', methods: 'post')]
    public function afresh(RequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $result['code'] = 201;
        $result['msg']  = '操作失败';
        $validator      = $this->validationFactory->make(
            $request->all(), ['order_sys_sn' => 'required|array|bail'],
            [
                'order_sys_sn.required' => '系统单号必填',
                'order_sys_sn.array'    => 'order_sys_sn is array',
            ]
        );
        if ($validator->fails()) {
            throw new HomeException($validator->errors()->first(), 201);
        }
        $params       = $request->all();
        $order_sys_sn = $params['order_sys_sn'];
        $member       = $request->UserInfo;
        $OrderDb      = $this->orderToParcelService->afreshToParcel($member, $order_sys_sn);

        if (empty($OrderDb) || (isset($OrderDb['parcel']) && Arr::hasArr($OrderDb['parcel'], 'transport_sn'))) {
            throw new HomeException('当前订单不存在、或者已取号', 201);
        }
        $order_status = array_unique(array_column($OrderDb, 'order_status'));
        if (count($order_status) != 1 /*|| current($order_status) != 29*/) {
            throw new HomeException('存在不同状态的订单、请确认后重新操作', 201);
        }
        $Err         = [];
        $generator   = \Hyperf\Support\make(UserDefinedIdGenerator::class);
        $singeNumber = $generator->generate($member['uid']);
        $msg         = '操作成功';
        foreach ($OrderDb as $key => $val) {
            try {
                //取号转包
                $order_sys_sn = (string)$val['order_sys_sn'];
                $this->orderToParcelService->lPush($order_sys_sn, $member['uid'], $member['child_uid'], $singeNumber);
            } catch (\Throwable $e) {
                $Err[] = ['order_sys_sn' => $val['order_sys_sn'], 'msg' => $e->getMessage()];
            }
        }
        if (!empty($Err)) {
            $msg .= json_encode($Err, JSON_UNESCAPED_UNICODE);
        }
        $result['code'] = 200;
        $result['msg']  = $msg;
        return $this->response->json($result);
    }

    //支付中心
    #[RequestMapping(path: 'center', methods: 'post')]
    public function center(RequestInterface $request)
    {
        $params         = $this->request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $rules          = [
            'page'           => ['required', 'numeric', 'integer'],
            'limit'          => ['required', 'numeric', 'integer'],
            'payment_sn'     => ['string', 'integer'],
            'order_sys_sn'   => ['string', 'integer'],
            'payment_status' => ['integer', Rule::in([0, 1, 2, 3, 4])],
        ];
        $params         = $LibValidation->validate($params, $rules);
        $member         = $request->UserInfo;
        $perPage        = Arr::hasArr($params, 'limit') ? $params['limit'] : 15;
        $result['code'] = 201;
        $result['msg']  = '查询失败';

        if (Arr::hasArr($params, 'payment_sn')) {
            $where['payment_sn'] = $params['payment_sn'];
        }
        if (Arr::hasArr($params, 'order_sys_sn')) {
            $where['order_sys_sn'] = $params['order_sys_sn'];
        }
        $where['member_uid']       = $member['uid'];
        $where['parent_agent_uid'] = $member['parent_agent_uid'];
        if (Arr::hasArr($params, 'payment_status', true)) {
            $where['payment_status'] = $params['payment_status'];
        }
        $painter         = OrderPaymentModel::query()->where($where)->paginate($perPage)->toArray();
        $painter['code'] = 200;
        $painter['msg']  = '加载完成';
        return $this->response->json($painter);
    }
}
