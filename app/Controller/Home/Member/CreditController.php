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

namespace App\Controller\Home\Member;


use App\Common\Lib\Arr;
use App\Controller\Home\AbstractController;
use App\Controller\Work\verify;
use App\Exception\HomeException;
use App\Model\FlowCheckItemModel;
use App\Model\PriceTemplateModel;
use App\Model\PriceTemplateVersionModel;
use App\Request\LibValidation;
use App\Service\Cache\BaseCacheService;
use App\Service\CreditService;
use App\Service\FlowService;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;


#[Controller(prefix: "member/credit")]
class CreditController extends AbstractController
{
    #[Inject]
    protected BaseCacheService  $baseCacheService;
    #[Inject]
    protected ResponseInterface $response;

    #[RequestMapping(path: 'apply', methods: 'post')]
    public function apply(RequestInterface $request)
    {
        $result['code'] = 200;
        $result['msg']  = '申请成功：等待审核';
        $params         = $request->all();
        $member         = $request->UserInfo;
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $params         = $LibValidation->validate($params,
            [
                'member_uid'   => 'required|integer',
                'join_uid'     => 'required|integer',
                'apply_amount' => 'required|integer',
                'apply_desc'   => 'string',
            ]);
        switch ($member['role_id']) {
            case 3:
                break;
            default:
                throw new HomeException('非加盟商禁止申请');
                break;
        }
        $CreditService = \Hyperf\Support\make(CreditService::class);
        $result        = $CreditService->apply(params: $params, member: $member);
        return $this->response->json($result);
    }


    /**
     * @DOC 调整额度
     * @Name   adjust
     * @Author wangfei
     * @date   2023/11/10 2023
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'adjust', methods: 'post')]
    public function adjust(RequestInterface $request)
    {
        $result['code'] = 200;
        $result['msg']  = '申请成功：等待审核';
        $params         = $request->all();
        $member         = $request->UserInfo;
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $params         = $LibValidation->validate($params,
            [
                'member_uid'   => 'required|integer',
                'join_uid'     => 'required|integer',
                'apply_amount' => ['required', 'numeric', 'min:0']
            ]);
        switch ($member['role_id']) {
            case 3:
            case 1:
                break;
            default:
                throw new HomeException('非加盟商禁止申请');
                break;
        }
        $CreditService = \Hyperf\Support\make(CreditService::class);
        $result        = $CreditService->adjust(params: $params, member: $member);
        return $this->response->json($result);
    }


    /**
     * @DOC   : 拒绝
     * @Name  : refuse
     * @Author: wangfei
     * @date  : 2022-05-04 2022
     * @param Request $request
     */
    #[RequestMapping(path: 'refuse', methods: 'post')]
    public function refuse(RequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $params        = $request->all();
        $member        = $this->request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params        = $LibValidation->validate($params, rules: [
            'apply_id' => ['required', 'integer'],
            'check_id' => ['required', 'integer'],
            'item_id'  => ['required', 'integer'],
            'info'     => ['string']
        ]);
        $CreditService = \Hyperf\Support\make(CreditService::class);
        $result        = $CreditService->refuse(params: $params, member: $member);
        return $this->response->json($result);
    }


    /**
     * @DOC 同意
     * @Name   agree
     * @Author wangfei
     * @date   2023/11/10 2023
     * @param RequestInterface $request
     */
    #[RequestMapping(path: 'agree', methods: 'post')]
    public function agree(RequestInterface $request)
    {
        $params        = $request->all();
        $member        = $this->request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params        = $LibValidation->validate($params, rules: [
            'apply_id' => ['required', 'integer'],
            'check_id' => ['required', 'integer'],
            'item_id'  => ['required', 'integer'],
            'info'     => ['string']
        ]);
        $CreditService = \Hyperf\Support\make(CreditService::class);
        $result        = $CreditService->agree(params: $params, member: $member);
        return $this->response->json($result);

    }

    /**
     * @DOC 申请详情
     * @Name   applyDetails
     * @Author wangfei
     * @date   2023/11/10 2023
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'apply/details', methods: 'post')]
    public function applyDetails(RequestInterface $request)
    {
        $result['code'] = 200;
        $result['msg']  = '查询成功';
        $params         = $request->all();
        $member         = $request->UserInfo;
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $params         = $LibValidation->validate($params,
            [
                'apply_id' => ['required', 'integer'],
            ]);
        $CreditService  = \Hyperf\Support\make(CreditService::class);
        $result['data'] = $CreditService->applyDetails(params: $params, member: $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 审核列表
     * @Name   check
     * @Author wangfei
     * @date   2023/11/9 2023
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     */

    #[RequestMapping(path: 'check', methods: 'post')]
    public function check(RequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $params         = $request->all();
        $member         = $this->request->UserInfo;
        $LibValidation  = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params         = $LibValidation->validate($params, rules: [
            'page'         => ['required', 'integer'],
            'limit'        => ['required', 'integer'],
            'check_status' => ['array'] //审核状态 0：待审核 1：同意 ,2：拒绝，3：其他人已审 4：撤销，多个值，请用逗号隔开。
        ]);
        $CreditService  = \Hyperf\Support\make(CreditService::class, [$member]);
        $result['code'] = 200;
        $result['msg']  = "查询成功";
        $result['data'] = $CreditService->check(params: $params, member: $member);
        return $this->response->json($result);
    }


    /**
     * @DOC 申请列表
     * @Name   applyLists
     * @Author wangfei
     * @date   2023/11/9 2023
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     */

    #[RequestMapping(path: 'apply/lists', methods: 'post')]
    public function applyLists(RequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $params         = $request->all();
        $member         = $this->request->UserInfo;
        $LibValidation  = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params         = $LibValidation->validate($params, rules: [
            'page'         => ['required', 'integer'],
            'limit'        => ['required', 'integer'],
            'check_status' => ['array'] //审核状态 0：待审核 1：同意 ,2：拒绝，3：其他人已审 4：撤销，多个值，请用逗号隔开。
        ]);
        $CreditService  = \Hyperf\Support\make(CreditService::class, [$member]);
        $result['code'] = 200;
        $result['msg']  = "查询成功";
        $result['data'] = $CreditService->applyLists(params: $params, member: $member);
        return $this->response->json($result);
    }

}
