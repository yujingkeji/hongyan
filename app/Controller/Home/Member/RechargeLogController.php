<?php

namespace App\Controller\Home\Member;

use App\Controller\Home\AbstractController;
use App\Exception\HomeException;
use App\Model\MemberAmountLogModel;
use App\Model\MemberRechargeModel;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: "member/rechargeLog")]
class RechargeLogController extends AbstractController
{
    /**
     * @DOC 查询支付单号状态
     */
    #[RequestMapping(path: "getOrderNo", methods: "get,post")]
    public function getOrderNo(): ResponseInterface
    {
        $param = $this->request->all();
        if (!isset($param['order_no']) || !$param['order_no']) {
            throw new HomeException('请输入支付单号', 201);
        }
        $data = MemberRechargeModel::where('order_no', $param['order_no'])
            ->select(['id', 'order_no', 'status'])->first();
        if (!$data) {
            throw new HomeException('支付单号错误', 201);
        }
        return $this->response->json(
            [
                'code' => 200,
                'msg'  => 'success',
                'data' => $data,
            ]);

    }

    /**
     * @DOC 充值记录
     */
    #[RequestMapping(path: "list", methods: "post")]
    public function list(RequestInterface $request): ResponseInterface
    {

        # 参数校验
        $validationFactory = \Hyperf\Support\make(ValidatorFactoryInterface::class);
        $validator         = $validationFactory->make(
            $request->all(),
            [
                'member_id'        => 'required',
                'parent_join_uid'  => 'required',
                'parent_agent_uid' => 'required',
                'time'             => 'required',
            ],
            [
                'member_id.required'        => '用户必传',
                'parent_join_uid.required'  => '用户必传',
                'parent_agent_uid.required' => '用户必传',
                'time.required'             => '时间必传'
            ]
        );
        if ($validator->fails()) {
            throw new HomeException($validator->errors()->first(), 201);
        }
        $param = $validator->validated();

        $where[] = ['member_uid', '=', $param['member_id']];
        $where[] = ['parent_join_uid', '=', $param['parent_join_uid']];
        $where[] = ['parent_agent_uid', '=', $param['parent_agent_uid']];
        $where[] = ['add_time', '>=', ($param['time'][0] ?? 0)];
        $where[] = ['add_time', '<=', ($param['time'][1] ?? 0)];

        $limit = $param['limit'] ?? 20;

        $data = MemberAmountLogModel::where($where)
            ->with(['config',
                    'recharge' => function ($query) {
                        $query->select(['amount_log_id', 'source_currency_id', 'target_currency_id', 'rate']);
                    },
                    'recharge.source', 'recharge.target'
            ])
            ->orderBy('add_time', 'DESC')
            ->paginate($limit);

        return $this->response->json(
            [
                'code' => 200,
                'msg'  => 'success',
                'data' => [
                    'count' => $data->total(),
                    'list'  => $data->items()
                ],
            ]);
    }


}
