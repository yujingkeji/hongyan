<?php

namespace App\Controller\Home\Member;

use App\Common\Lib\Crypt;
use App\Controller\Home\AbstractController;
use App\Exception\HomeException;
use App\Model\AgentMemberModel;
use App\Model\MemberAmountLogModel;
use App\Model\MemberChildModel;
use App\Model\MemberRechargeModel;
use App\Request\RechargeRequest;
use App\Service\RechargeService;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: "member/recharge")]
class RechargeController extends AbstractController
{
    /**
     * @DOC 余额转账
     */
    #[RequestMapping(path: "balance", methods: "get,post")]
    public function balance(RequestInterface $request): ResponseInterface
    {
        # 参数校验
        $validationFactory = \Hyperf\Support\make(ValidatorFactoryInterface::class);
        $validator         = $validationFactory->make(
            $request->all(),
            [
                'child_uid'   => 'required',
                'desc'        => 'required',
                'amount'      => 'required',
                'subordinate' => 'required',
            ],
            [
                'child_uid.required'   => '用户必传',
                'subordinate.required' => '请选择要转账的账号',
                'desc.required'        => '请输入备注信息',
                'amount.required'      => '请输入转账金额',
            ]
        );
        if ($validator->fails()) {
            throw new HomeException($validator->errors()->first(), 201);
        }
        $param      = $validator->validated();
        $memberInfo = $request->UserInfo;
        if ($param['amount'] < 0) {
            throw new HomeException('转账金额不小于0');
        }

        # 子账号转账
        if ($param['child_uid']) {
            $child_uid               = MemberChildModel::where('child_uid', $param['child_uid'])->first();
            $memberInfo['user_name'] = $child_uid['child_name'];
        }
        # 获取当前用户个人信息
        $memberInfo['member'] = [];
        if ($memberInfo['role_id'] != 1) {
            $memberInfo['member'] = AgentMemberModel::where('member_uid', $memberInfo['uid'])
                ->where('parent_join_uid', $memberInfo['parent_join_uid'])
                ->where('parent_agent_uid', $memberInfo['parent_agent_uid'])->first();
            if ($memberInfo['member']['amount'] - $param['amount'] <= 0) {
                throw new HomeException('转账余额不足');
            }
        }

        # 收账人的用户信息
        $member = AgentMemberModel::where('member_uid', $param['subordinate'])
            ->where('parent_agent_uid', $memberInfo['parent_agent_uid'])
            ->with('member')
            ->first();
        if (!$member) {
            throw new HomeException('未查询到转账用户');
        }
        $member = $member->toArray();
        # 获取余额变动日志
        $log = $this->rechargeLog($memberInfo, $member, $param);

        Db::transaction(function () use ($memberInfo, $param, $member, $log) {
            # 代理转账
            if ($memberInfo['role_id'] != 1) {
                # 非代理进行修改金额
                $pay_member = AgentMemberModel::where('member_uid', $memberInfo['uid'])
                    ->where('parent_join_uid', $memberInfo['parent_join_uid'])
                    ->where('parent_agent_uid', $memberInfo['parent_agent_uid'])->first();
                $pay_member->update(['amount' => $pay_member->amount - $param['amount'], 'amount_encryption' => $log['amount_log'][0]['surplus_amount_encryption']]);
            }
            AgentMemberModel::where('member_uid', $param['subordinate'])
                ->where('parent_agent_uid', $memberInfo['parent_agent_uid'])
                ->update(['amount' => $member['amount'] + $param['amount'], 'amount_encryption' => $log['amount_log'][1]['surplus_amount_encryption']]);
            MemberRechargeModel::insert($log['log']);
            MemberAmountLogModel::insert($log['amount_log']);
        });
        return $this->response->json(['code' => 200, 'msg' => '转账成功', 'data' => []]);
    }

    /**
     * @DOC 转账日志记录
     */
    public function rechargeLog($memberInfo, $subordinate, $param): array
    {
        $log                 = [
            [
                'uid'              => $memberInfo['uid'],
                'parent_join_uid'  => $memberInfo['parent_join_uid'],
                'parent_agent_uid' => $memberInfo['parent_agent_uid'],
                'child_uid'        => $param['child_uid'],
                'amount'           => -$param['amount'],
                'type'             => '余额转账',
                'desc'             => $param['desc'],
                'add_time'         => time(),
                'status'           => 1,
            ],
            [
                'uid'              => $subordinate['member_uid'],
                'parent_join_uid'  => $subordinate['parent_join_uid'],
                'parent_agent_uid' => $subordinate['parent_agent_uid'],
                'child_uid'        => 0,
                'amount'           => +$param['amount'],
                'type'             => '余额转账',
                'desc'             => $param['desc'],
                'add_time'         => time(),
                'status'           => 1,
            ]
        ];
        $crypt               = \Hyperf\Support\make(Crypt::class);
        $amount_encryption_t = $crypt->balanceEncrypt($subordinate['amount'] + $param['amount']);

        $surplus_amount_o    = 0;
        $amount_encryption_o = '';
        if ($memberInfo['role_id'] != 1) {
            $surplus_amount_o    = $memberInfo['member']['amount'] - $param['amount'];
            $amount_encryption_o = $crypt->balanceEncrypt($surplus_amount_o);
        }

        # 账户改变日志
        $amount_log = [
            [
                'member_uid'                => $memberInfo['uid'],
                'child_uid'                 => $param['child_uid'],
                'parent_join_uid'           => $memberInfo['parent_join_uid'],
                'parent_agent_uid'          => $memberInfo['parent_agent_uid'],
                'amount'                    => -$param['amount'],
                'currency'                  => 'CNY',
                'surplus_amount'            => $surplus_amount_o,
                'desc'                      => '给' . $subordinate['member']['user_name'] . '转账',
                'add_time'                  => time(),
                'reason'                    => $param['desc'],
                'cfg_id'                    => 24001,
                'surplus_amount_encryption' => $amount_encryption_o,
            ],
            [
                'member_uid'                => $subordinate['member_uid'],
                'child_uid'                 => 0,
                'parent_join_uid'           => $subordinate['parent_join_uid'],
                'parent_agent_uid'          => $subordinate['parent_agent_uid'],
                'amount'                    => +$param['amount'],
                'currency'                  => 'CNY',
                'surplus_amount'            => $subordinate['amount'] + $param['amount'],
                'add_time'                  => time(),
                'desc'                      => '来自' . $memberInfo['user_name'] . '充值',
                'reason'                    => '',
                'cfg_id'                    => 24005,
                'surplus_amount_encryption' => $amount_encryption_t,
            ],
        ];
        return ['log' => $log, 'amount_log' => $amount_log];
    }


    /**
     * @DOC 获取二维码
     */
    #[RequestMapping(path: "getWxChatPay", methods: "post")]
    public function getWxChatPay(RequestInterface $request): ResponseInterface
    {
        $param    = $request->all();
        $userInfo = $this->request->UserInfo;
        $result   = (new RechargeService())->getWxChatPay($param, $userInfo);
        return $this->response->json($result);
    }

    /**
     * @DOC 获取order_no单号
     */
    #[RequestMapping(path: "does", methods: "post")]
    public function does(RequestInterface $request): ResponseInterface
    {
        $param    = $request->all();
        $userInfo = $this->request->UserInfo;
        $result   = (new RechargeService())->getOrderNo($param, $userInfo);
        return $this->response->json($result);
    }

    /**
     * @DOC 生成支付宝的支付二维码
     */
    #[RequestMapping(path: "index", methods: "post")]
    public function index(RequestInterface $request)
    {
        $param    = $request->all();
        $userInfo = $this->request->UserInfo;
        $result   = (new RechargeService())->getAliPay($param, $userInfo);
        return $this->response->json($result);
    }

}
