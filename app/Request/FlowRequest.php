<?php

declare(strict_types=1);

namespace App\Request;


use Hyperf\Validation\Rule;

class FlowRequest
{

    public function rules(string $scenes, array $params, array $member = []): array
    {
        switch ($scenes) {
            case 'add':
                return [
                    'rules'    => $this->addAndEditRules(params: $params, member: $member),
                    'messages' => $this->addAndEditMessages(params: $params),
                ];
                break;
            case 'edit':
                $rules              = $this->addAndEditRules(params: $params, member: $member);
                $rules['flow_id']   = ['required', 'string', Rule::exists('flow')->where(function ($query) use ($params, $member) {
                    $query->where('uid', '=', $member['uid'])->where('flow_id', '=', $params['flow_id']);
                })];
                $rules['flow_name'] = ['required', 'string', 'min:4', Rule::unique('flow')->where(function ($query) use ($params, $member) {
                    $query->where('uid', '=', $member['uid'])
                        ->where('flow_name', '=', $params['flow_name'])
                        ->where('flow_id', '<>', $params['flow_id']);
                })];
                return [
                    'rules'    => $rules,
                    'messages' => $this->addAndEditMessages(params: $params),
                ];
                break;
            case 'status':
                $rules =
                    [
                        'flow_id' => ['required', 'string', Rule::exists('flow')->where(function ($query) use ($params, $member) {
                            $query->where('uid', '=', $member['uid'])->where('flow_id', '=', $params['flow_id']);
                        })],
                        'status'  => ['integer', Rule::in([0, 1])],//0:禁用，1：启用
                    ];
                return ['rules' => $rules, 'messages' => $this->addAndEditMessages(params: $params)];
                break;
            case 'lock':
                $rules =
                    [
                        'flow_id' => ['required', 'string', Rule::exists('flow')->where(function ($query) use ($params, $member) {
                            $query->where('uid', '=', $member['uid'])->where('flow_id', '=', $params['flow_id']);
                        })],
                        'lock'    => ['integer', Rule::in([0, 1])],//0:正常 1：锁定，锁定状态禁止任何修改
                    ];
                return ['rules' => $rules, 'messages' => $this->addAndEditMessages(params: $params)];
                break;
            case 'del':
                $rules =
                    [
                        'flow_id' => ['required', 'string', Rule::exists('flow')->where(function ($query) use ($params, $member) {
                            $query->where('uid', '=', $member['uid'])->where('flow_id', '=', $params['flow_id'])
                                ->where('lock', '=', 0);
                        })],
                    ];
                return ['rules' => $rules, 'messages' => $this->addAndEditMessages(params: $params)];
                break;
        }
    }


    protected function addAndEditRules(array $params, array $member)
    {
        $rules =
            [
                'flow_name'               => ['required', 'string', 'min:4', Rule::unique('flow')->where(function ($query) use ($params, $member) {
                    $query->where('uid', '=', $member['uid'])
                        ->where('flow_name', '=', $params['flow_name']);
                })],
                'info'                    => ['string'],
                'status'                  => ['integer', Rule::in([0, 1])],//0:禁用，1：启用
                'lock'                    => ['integer', Rule::in([0, 1])],//0:正常 1：锁定，锁定状态禁止任何修改
                'flow_node'               => ['array'],
                'flow_node.*.node_name'   => ['required', 'string'],//审核的节点名称
                'flow_node.*.role_id'     => ['required', 'integer'],//审核人的权限id
                'flow_node.*.node_status' => ['required', 'integer', Rule::in([0, 1, 2])],//0：指定审核人（一个人审核），1：依次审核，2：或审（一个人审核就可以）
                'flow_node.*.reviewer'    => ['required', 'string'],//具体的审核人员
                'flow_node.*.must_reply'  => ['required', 'integer', Rule::in([0, 1])],//审核的时候：1:必须填写理由
                'flow_node.*.layer'       => ['required', 'integer'],//审核的层级
            ];
        return $rules;
    }

    protected function addAndEditMessages(array $params = [])
    {
        $messages =
            [
                'flow_id.required'   => '流程ID必填',
                'flow_id.exists'     => '当前流程不存在、或流程已经锁定禁止修改',
                'flow_name.required' => '流程名称必填',
                'flow_name.min'      => '流程名称不能少于4个字符',
                'flow_name.unique'   => '当前名称已存在、禁止重复',
            ];
        return $messages;
    }


}
