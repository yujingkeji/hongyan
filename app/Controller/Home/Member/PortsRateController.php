<?php

namespace App\Controller\Home\Member;

use App\Common\Lib\Arr;
use App\Controller\Home\HomeBaseController;
use App\Exception\HomeException;
use App\Model\AgentMemberModel;
use App\Model\MemberPortModel;
use App\Model\PortModel;
use App\Request\LibValidation;
use App\Service\PortsRateService;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;
use Psr\Http\Message\ResponseInterface;

/**
 * 口岸出税率
 */
#[Controller(prefix: "member/ports/rate")]
class PortsRateController extends HomeBaseController
{


    private function isMemberUidRequired($request): bool
    {
        $target = $request->input('target');
        return in_array($target, ['member', 'join'], true);
    }

    /**
     * @DOC   :设置口岸出税率
     * @Name  : set
     * @Author: wangfei
     * @date  : 2025-03 20:53
     * @param RequestInterface $request
     *
     */
    #[RequestMapping(path: "set", methods: "post")]
    public function set(RequestInterface $request): ResponseInterface
    {
        $member = $request->UserInfo;
        $params = \Hyperf\Support\make(LibValidation::class)
            ->validate($request->all(),
                [
                    'target'         => ['required', Rule::in(['agent', 'join', 'member'])],
                    'port_id'        => ['required', 'integer', 'min:1', Rule::exists('port')],
                    'member_uid'     => ['array', Rule::requiredIf(function () use ($request) {
                        return $this->isMemberUidRequired($request);
                    })],
                    'supervision_id' => ['required', 'integer', 'min:1', Rule::exists('customs_supervision')],
                    'min_rate'       => ['required', 'numeric', 'min:0'],
                    'min_tax_amount' => ['required', 'numeric', 'min:0'],
                ],
                [
                    'target.required'       => '出税率设置对象不能为空',
                    'target.in'             => '出税率设置对象错误[agent,join,member]',
                    'port_id.required'      => '口岸不能为空',
                    'port_id.integer'       => '口岸id必须为整数',
                    'port_id.min'           => '口岸id必须大于0',
                    'port_id.exists'        => '口岸不存在',
                    'member_uid.required'   => '会员不能为空',
                    'member_uid.array'      => '会员id必须为数组',
                    'supervision_id.exists' => '监管方式不存在',
                ]
            );

        switch ($member['role_id']) {
            case 1: //平台代理
            case 3://加盟商

                break;
            default:
                throw new HomeException('权限不足');
                break;
        }
        $result = make(PortsRateService::class)->rate(params: $params, member: $member);
        return $this->response->json($result);

        $targetMember = AgentMemberModel::query()
            ->where('parent_agent_uid', $member['parent_agent_uid'])
            ->whereIn('member_uid', $params['member_uid'])->get()->toArray();
        if (!empty($params['port_id'])) {
            $portDb            = PortModel::query()->where('port_id', $params['port_id'])->first()->toArray();
            $data['port_code'] = $portDb['port_code'];
        }

        $InsertData = [];
        foreach ($targetMember as $key => $item) {
            $data['port_id']          = $params['port_id'];
            $data['parent_agent_uid'] = $item['parent_agent_uid'];
            $data['parent_join_uid']  = $item['parent_join_uid'];
            $data['member_uid']       = $item['member_uid'];
            $data['min_rate']         = $params['min_rate'];
            $data['min_tax_amount']   = $params['min_tax_amount'];
            $InsertData[]             = $data;
        }
        //批量处理是更新还是写入member_port_rate
        Db::beginTransaction();
        try {
            switch ($member['role_id']) {
                case 1:
                    Db::table('member_port_rate')->where('parent_agent_uid', $member['parent_agent_uid'])
                        ->whereIn('member_uid', $params['member_uid'])->delete();
                    break;
                case 3:
                    Db::table('member_port_rate')->where('parent_agent_uid', $member['parent_agent_uid'])
                        ->whereIn('member_uid', $params['member_uid'])
                        ->where('parent_join_uid', $member['parent_join_uid'])
                        ->delete();
                    break;
            }
            Db::table('member_port_rate')->insert($InsertData);
            // 提交事务
            Db::commit();
            $result['code'] = 200;
            $result['msg']  = '设置成功';
        } catch (\Exception $e) {
            // 回滚事务
            $result['code'] = 201;
            $result['msg']  = '设置失败' . $e->getMessage();
            Db::rollback();
        }
        return $this->response->json($result);
    }

}
