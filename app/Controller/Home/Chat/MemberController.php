<?php

namespace App\Controller\Home\Chat;

use App\Controller\Home\HomeBaseController;
use App\Model\AgentPlatformModel;
use App\Model\MemberModel;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

#[Controller(prefix: "chat/member")]
class MemberController extends HomeBaseController
{

    /**
     * @DOC 获取用户信息
     */
    #[RequestMapping(path: 'info', methods: 'get,post')]
    public function info(RequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $member = MemberModel::query()->where('uid', $request->UserInfo['uid'])->first();
        $code   = AgentPlatformModel::query()
            ->where('agent_platform_uid', $request->UserInfo['parent_agent_uid'])
            ->value('web_code');
        // 拼接数据
        $data['source_login_name']         = $member['user_name'];
        $data['source_member_name']        = $member['nick_name'];
        $data['source_member_uid']         = $member['uid'];
        $data['support_source_member_uid'] = $member['uid'];
        $data['source_child_name']         = '';
        $data['source_child_uid']          = 0;
        $data['code']                      = $code;
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
    }


}
