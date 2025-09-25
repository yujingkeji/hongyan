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

namespace App\Controller\Home;

use App\Common\Lib\Arr;
use App\Common\Lib\Crypt;
use App\Common\Lib\Str;


class HomeBaseController extends AbstractController
{

    /**
     * @DOC    根据当前用户的角色，来判断当前数据的所属查询权限
     * @Name   useWhere
     * @Author wangfei
     * @date   2022-11-21 2022
     */
    public function useWhere()
    {
        $member                   = $this->request->UserInfo;
        $base                     = $where = [];
        $base['parent_agent_uid'] = $member['parent_agent_uid'];
        $base['member_uid']       = $member['uid'];
        $where[]                  = ['parent_agent_uid', '=', $member['parent_agent_uid']];
        switch ($member['role_id']) {
            case 1:
            case 2:
            case 10: // 仓库管理
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

    /**
     * @DOC 手机号解密
     */
    public function memberDecrypt(array $member, $Star = false)
    {
        if (Arr::hasArr($member, 'tel')) {
            $tel           = base64_decode($member["tel"]);
            $tel           = (new Crypt())->decrypt($tel);
            $tel           = ($Star) ? Str::centerStar($tel) : $tel;
            $member['tel'] = $tel;
        }
        return $member;
    }

    /**
     * @DOC 地址解密
     */
    protected function addressDecrypt($Address, bool $Star = false)
    {
        $name  = '';
        $crypt = (new Crypt());
        if (Arr::hasArr($Address, 'name')) {
            $name = base64_decode($Address["name"]);
            $name = $crypt->decrypt($name);
//            $name = ($Star) ? Str::centerStar($name) : $name;
        }
        $Address['name'] = $name;
        $phone           = '';
        if (Arr::hasArr($Address, 'phone')) {
            $phone = base64_decode($Address["phone"], true);
            $phone = $crypt->decrypt($phone);
            $phone = ($Star) ? Str::centerStar($phone) : $phone;
        }
        $Address['phone'] = $phone;
        $mobile           = '';
        if (Arr::hasArr($Address, 'mobile')) {
            $mobile = base64_decode($Address["mobile"]);
            $mobile = $crypt->decrypt($mobile);
            $mobile = ($Star) ? Str::centerStar($mobile) : $mobile;
        }
        $Address['mobile'] = $mobile;
        return $Address;
    }

    /**
     * @DOC 处理寄件人、收件人信息加密
     */
    protected function addressEncrypt($Address)
    {
        $crypt             = (new Crypt());
        $Address['name']   = Arr::hasArr($Address, 'name') ? base64_encode($crypt->encrypt($Address["name"])) : "";
        $Address['phone']  = Arr::hasArr($Address, 'phone') ? base64_encode($crypt->encrypt($Address["phone"])) : "";
        $Address['mobile'] = Arr::hasArr($Address, 'mobile') ? base64_encode($crypt->encrypt($Address["mobile"])) : "";
        return $Address;
    }

    /**
     * @DOC 地址md5 判断地址重复
     */
    protected function md5Address($address, $keys = [
        'country', 'name', 'province', 'city', 'district',
        'street', 'address', 'phone', 'mobile', 'member_uid', 'parent_join_uid', 'parent_agent_uid']): string
    {

        foreach ($address as $key => $val) {
            if (!in_array($key, $keys)) {
                unset($address[$key]);
            }
        }
        $Str = Arr::hasSortString($address);
        unset($address);
        return md5($Str);
    }
}
