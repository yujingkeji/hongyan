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

namespace App\Controller\Home\Orders;

use Hyperf\Di\Annotation\Inject;
use App\Common\Lib\Arr;
use App\Common\Lib\Crypt;
use App\Common\Lib\Str;
use App\Controller\Home\HomeBaseController;
use Hyperf\HttpServer\Contract\RequestInterface;


class OrderBaseController extends HomeBaseController
{
    #[Inject]
    protected Crypt $crypt;
    public int   $deliveryStationStatus = 50;//发往到集货站的状态
    protected int   $importAuth            = 22102;//40001=>22102; //清关需要认证
    protected int   $importRecord          = 22002;//40002=>22002; //清关需要备案


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
}
