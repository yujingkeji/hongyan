<?php

namespace App\Controller\Home\Member;


use App\Common\Lib\Arr;
use App\Controller\Home\HomeBaseController;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use App\Service\Cache\BaseCacheService;


#[Controller(prefix: "member/payment")]
class PaymentController extends HomeBaseController
{

    #[Inject]
    protected BaseCacheService $baseCacheService;

    /**
     * @DOC 支付方式 平台代理
     */
    #[RequestMapping(path: "opened", methods: "get,post")]
    public function opened(RequestInterface $request): ResponseInterface
    {
        $member                   = $this->request->UserInfo;
        $memberPaymentMethodCache = $this->baseCacheService->memberPaymentMethodCache($member['parent_agent_uid']);
        $memberPaymentMethodCache = array_column($memberPaymentMethodCache, null, 'third_code');
        switch ($member['role_id']) {
            case 3:
            case 4:
            case 5:
                if (Arr::hasArr($memberPaymentMethodCache, 'balance')) {
                    $key                                      = 'balance';
                    $where[]                                  = ['member_uid', '=', $member['uid']];
                    $where[]                                  = ['parent_agent_uid', '=', $member['parent_agent_uid']];
                    $amount                                   = Db::table("agent_member")->where($where)->value('amount');
                    $memberPaymentMethodCache[$key]['amount'] = $amount;
                }
                break;
        }
        unset($member, $where);
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $memberPaymentMethodCache]);
    }


}
