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

namespace App\Controller\Home\Parcels;

use App\Common\Lib\Arr;
use App\Common\Lib\UserDefinedIdGenerator;
use App\Exception\HomeException;
use App\Request\LibValidation;
use App\Service\BillSettlementService;
use App\Service\Cache\BaseCacheService;
use App\Service\ParcelPaymentService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\Rule;


#[Controller(prefix: 'parcels/payment')]
class PaymentController extends ParcelBaseController
{

    #[Inject]
    protected ValidatorFactoryInterface $validationFactory;
    #[Inject]
    protected ParcelPaymentService $parcelPaymentService;

    #[Inject]
    protected BaseCacheService $baseCacheService;

    #[RequestMapping(path: 'calc', methods: 'post')]
    public function calc(RequestInterface $request): \Psr\Http\Message\ResponseInterface
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
        $params         = $validator->validated();
        $member         = $request->UserInfo;
        $result['code'] = 200;
        $result['msg']  = "查询成功";
        $result['data'] = $this->parcelPaymentService->clac(member: $member, order_sys_sn: $params['order_sys_sn']);;
        return $this->response->json($result);

    }

    //包裹支付-立即支付（批量）补重批量支付
    #[RequestMapping(path: 'pay', methods: 'post')]
    public function pay(RequestInterface $request, BaseCacheService $baseCacheService): \Psr\Http\Message\ResponseInterface
    {
        $result['code']     = 201;
        $result['msg']      = '操作失败';
        $member             = $request->UserInfo;
        $paymentMethodCache = $this->baseCacheService->memberPaymentMethodCache($member['parent_agent_uid']);
        $paymentMethodCode  = array_column($paymentMethodCache, 'third_code');
        $params             = $request->all();
        $LibValidation      = \Hyperf\Support\make(LibValidation::class, [$member]);
        $rules              =
            [
                'order_sys_sn' => 'required|array|bail',
                'payment_code' => ['required', 'string', Rule::in($paymentMethodCode)],
                'coupons_code' => ['string']
            ];
        $messages           = [
            'order_sys_sn.required' => '系统单号必填',
            'order_sys_sn.array'    => 'order_sys_sn is array',
            'payment_code.required' => '请选择支付方式',
            'payment_code.in'       => 'payment_code  must in ' . implode(',', $paymentMethodCode),
        ];
        $params             = $LibValidation->validate(params: $params, rules: $rules, messages: $messages);


        switch ($member['role_id']) {
            default:
                throw new HomeException('非订单单直属客户、禁止访问');
                break;
            case 3:
            case 4:
            case 5:
                $paymentMethodCache = array_column($paymentMethodCache, null, 'third_code');
                array_change_key_case($paymentMethodCache);
                $payment_code = strtolower($params['payment_code']);
                if (isset($paymentMethodCache[$payment_code])) {
                    $payment_method = $paymentMethodCache[$payment_code];
                } else {
                    throw new HomeException('支付方式不存在、或者未开通、禁止访问');
                }
                $result['code'] = 200;
                $msg            = '操作成功';
                $paymentResult  = $this->parcelPaymentService->pay(member: $member, order_sys_sn: $params['order_sys_sn'], payment_method: $payment_method, coupons_code: $params['coupons_code'] ?? '');
                $result['data'] = $paymentResult;
                if (Arr::hasArr($paymentResult, 'success')) {
                    $msg = '支付成功';
                    //加入到异步任务中：统计支付结果
                    $logger                = \Hyperf\Support\make(LoggerFactory::class)->get('log', 'AsyncBillSettlementProcess');
                    $BillSettlementService = \Hyperf\Support\make(BillSettlementService::class, [$member]);
                    $BillSettlementService->logger($logger);
                    $orders_sys_sn = array_column($paymentResult['success'], 'order_sys_sn');
                    $BillSettlementService->lPush(order_sys_sn: $orders_sys_sn);
                }
                if (Arr::hasArr($paymentResult, 'failed') && !Arr::hasArr($paymentResult, 'success')) {
                    $result['code'] = 201;
                    $msg            = '支付失败';
                }
                $result['msg'] = $msg;
                break;
        }
        return $this->response->json($result);

    }
}
