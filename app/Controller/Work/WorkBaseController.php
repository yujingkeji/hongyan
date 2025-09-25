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

namespace App\Controller\Work;


use App\Common\Lib\Arr;
use App\Common\Lib\Crypt;
use App\Common\Lib\Str;
use App\Controller\BaseController;
use App\Service\Express\OrderToParcelService;
use Hyperf\Di\Annotation\Inject;

class WorkBaseController extends BaseController
{
    #[Inject]
    protected Crypt $crypt;

    /**
     * @DOC 解密
     * @Name   handleDecrypt
     * @Author wangfei
     * @date   2023-06-13 2023
     * @param array $Address
     * @param false $Star
     * @return array
     */
    public function handleDecrypt(array $Address, $Star = false)
    {
        $name = '';
        if (Arr::hasArr($Address, 'name')) {
            $name = base64_decode($Address["name"]);
            $name = $this->crypt->decrypt($name);
            $name = ($Star) ? Str::centerStar($name) : $name;
        }
        $Address['name'] = $name;
        $phone           = '';
        if (Arr::hasArr($Address, 'phone')) {
            $phone = base64_decode($Address["phone"], true);
            $phone = $this->crypt->decrypt($phone);
            $phone = ($Star) ? Str::centerStar($phone) : $phone;
        }
        $Address['phone'] = $phone;
        $mobile           = '';
        if (Arr::hasArr($Address, 'mobile')) {
            $mobile = base64_decode($Address["mobile"]);
            $mobile = $this->crypt->decrypt($mobile);
            $mobile = ($Star) ? Str::centerStar($mobile) : $mobile;
        }
        $Address['mobile'] = $mobile;
        return $Address;
    }

    public function memberDecrypt(array $member, $Star = false)
    {
        if (Arr::hasArr($member, 'tel')) {
            $tel           = base64_decode($member["tel"]);
            $tel           = $this->crypt->decrypt($tel);
            $tel           = ($Star) ? Str::centerStar($tel) : $tel;
            $member['tel'] = $tel;
        }
        return $member;
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
}
