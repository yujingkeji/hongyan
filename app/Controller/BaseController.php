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

namespace App\Controller;


use Hyperf\HttpServer\Contract\RequestInterface;


class BaseController extends AbstractController
{
    public array $address_status = [111, 201];//正确的收件人地址状态
    public int   $child_uid;
    public int   $member_uid;
    public int   $parent_join_uid;
    public int   $parent_agent_uid;

    public function __construct(RequestInterface $request)
    {
        $this->member_uid       = $request->UserInfo['uid'];
        $this->child_uid        = $request->UserInfo['child_uid'];
        $this->parent_join_uid  = $request->UserInfo['parent_join_uid'];
        $this->parent_agent_uid = $request->UserInfo['parent_agent_uid'];
    }

    /**
     * @DOC    根据当前用户的角色，来判断当前数据的所属查询权限
     * @Name   useWhere
     * @Author wangfei
     * @date   2022-11-21 2022
     */
    protected function useWhere()
    {
        $member                   = $this->request->UserInfo;
        $base                     = $where = [];
        $base['parent_agent_uid'] = $member['parent_agent_uid'];
        $base['member_uid']       = $member['uid'];
        $base['member_uid']       = $member['uid'];
        $where[]                  = ['parent_agent_uid', '=', $member['parent_agent_uid']];
        switch ($member['role_id']) {
            case 1:
            case 2:
                break;
            case 3: //加盟商
                $base['parent_join_uid'] = $member['uid'];
                $where[]                 = ['parent_join_uid', '=', $member['uid']];
                break;
            default:
                $base['parent_join_uid'] = $member['parent_join_uid'];
                $where[]                 = ['member_uid', '=', $member['uid']];
                break;
        }
        return ['base' => $base, 'where' => $where];
    }
}
