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
use App\Exception\HomeException;
use App\Model\ParcelExceptionItemModel;
use App\Request\BlRequest;
use App\Request\LibValidation;
use App\Service\BlService;
use App\Request\OrdersRequest;
use App\Service\Cache\BaseCacheService;
use App\Service\ParcelService;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\Rule;


#[Controller(prefix: 'parcels/export')]
class ParcelExportController extends ParcelBaseController
{
    #[Inject]
    protected BaseCacheService          $baseCacheService;
    #[Inject]
    protected ValidatorFactoryInterface $validationFactory;


    #[RequestMapping(path: 'bl', methods: 'get,post')]
    public function bl(RequestInterface $request)
    {
        $param         = $this->request->all();
        $MemberRequest = $this->container->get(BlRequest::class);
        $vData         = $MemberRequest->scene('nodeList')->validated();
        $useWhere      = $this->useWhere();
        switch ($this->request->UserInfo['role_id']) {
            default:
                throw new HomeException('禁止非平台代理访问', 201);
                break;
            case 1:
            case 2:
                break;
        }
        $result['code']  = 201;
        $result['msg']   = '查询失败';
        $member          = $request->UserInfo;
        $blSerice        = \Hyperf\Support\make(BlService::class);
        $painter         = $blSerice->BlNodeExportLists(member: $member, vData: $vData);
        $painter['code'] = 200;
        $painter['msg']  = '加载完成';
        return $this->response->json($painter);
    }

    //包裹异常标记
    #[RequestMapping(path: 'exception', methods: 'get,post')]
    public function exception(RequestInterface $request)
    {
        //  $exceptionCode  = [15081, 15082, 15083]; //config model =15000
        $exceptionCode  = $this->baseCacheService->ExportExceptionCache();
        $exceptionCode  = array_column($exceptionCode, 'cfg_id');
        $rules          = [
            'orders'                => 'required|array|bail',
            'orders.*.order_sys_sn' => "min:10",
            "orders.*.code"         => ['required', "array"],
            "orders.*.code.*"       => ['required', "numeric", Rule::in($exceptionCode)],
            "orders.*.desc"         => 'min:2'
        ];
        $messages       = [
            'orders.*.code.*.in' => 'code must in ' . implode(',', $exceptionCode)
        ];
        $member         = $this->request->UserInfo;
        $LibValidation  = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params         = $LibValidation->validate(params: $request->all(), rules: $rules, messages: $messages);
        $result['code'] = 200;
        $result['msg']  = '标记失败、或重复标记';
        $parcelService  = \Hyperf\Support\make(ParcelService::class);
        $result         = $parcelService->bindException(params: $params, member: $member, node: 'export', code: $exceptionCode);
        return $this->response->json($result);
    }


    /**
     * @DOC  移除异常
     * @Name   removeException
     * @Author wangfei
     * @date   2023-08-23 2023
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'removeException', methods: 'get,post')]
    public function removeException(RequestInterface $request)
    {
        $exceptionCode = [15081, 15082, 15083]; //config model =15000
        $validator     = $this->validationFactory->make(
            $request->all(),
            [
                'orders'                => 'required|array|bail',
                'orders.*.order_sys_sn' => "min:10",
                "orders.*.code"         => ['required', "array"],
                "orders.*.code.*"       => ['required', "numeric", Rule::in($exceptionCode)],
                "orders.*.desc"         => 'min:2'
            ],
            [
                'orders.required'           => 'orders must be required',
                'orders.*.order_sys_sn.min' => 'order_sys_sn size of :attribute must be :rule',
                'orders.*.code.numeric'     => 'code must be numeric',
                'orders.*.code.*.in'        => 'code must in ' . implode(',', $exceptionCode),
                'orders.*.desc.min'         => 'desc size of :attribute must be :min',
            ]
        );
        if ($validator->fails()) {
            throw new HomeException($validator->errors()->first(), 201);
        }
        $params         = $validator->validated();
        $member         = $request->UserInfo;
        $result['code'] = 200;
        $result['msg']  = '解除异常失败';
        $parcelService  = \Hyperf\Support\make(ParcelService::class);
        $bool           = $parcelService->removeException(params: $params, member: $member, node: 'export', code: $exceptionCode);
        if ($bool) {
            $result['code'] = 200;
            $result['msg']  = '解除异常成功';
        }
        return $this->response->json($result);
    }

    #[RequestMapping(path: 'parcel', methods: 'get,post')]
    public function parcel(RequestInterface $request)
    {
        $MemberRequest   = $this->container->get(OrdersRequest::class);
        $vData           = $MemberRequest->scene('parcelSendList')->validated();
        $useWhere        = $this->useWhere();
        $where           = $useWhere['where'];
        $member          = $request->UserInfo;
        $parcelService   = \Hyperf\Support\make(ParcelService::class);
        $painter['code'] = 200;
        $painter['msg']  = '加载完成';
        $painter['data'] = $parcelService->parcelNodeListAnalyse($member, $vData, 'export');
        return $this->response->json($painter);
    }

    //报关结单
    #[RequestMapping(path: 'bl/done', methods: 'get,post')]
    public function done(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '操作失败';
        $param          = $this->request->all();
        $MemberRequest  = $this->container->get(BlRequest::class);
        $doneData       = $MemberRequest->scene('done')->validated();
        $useWhere       = $this->useWhere();
        $member         = $request->UserInfo;
        if (!Arr::hasArr($doneData, 'bl_sn')) {
            throw new HomeException('请输入提单号', 201);
        }
        switch ($this->request->UserInfo['role_id']) {
            default:
                throw new HomeException('禁止非平台代理访问、创建提单等', 201);
                break;
            case 1:
            case 2:
                $blService = \Hyperf\Support\make(BlService::class);
                $result    = $blService->NodeDone(blSn: $doneData['bl_sn'], op_member_uid: $member['uid'], node: 'export');
                if ($result['code'] == 200) {
                    $blService->lPushBlDone(bl_sn: $doneData['bl_sn'], table: 'parcel_export');
                    $result['code'] = 200;
                    $result['msg']  = '操作成功';
                }


                break;
        }
        return $this->response->json($result);

    }

}
