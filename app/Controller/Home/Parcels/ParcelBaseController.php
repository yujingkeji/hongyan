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

namespace App\Controller\Home\Parcels;

use Hyperf\Di\Annotation\Inject;
use App\Common\Lib\Arr;
use App\Common\Lib\Crypt;
use App\Common\Lib\Str;
use App\Controller\Home\HomeBaseController;


class ParcelBaseController extends HomeBaseController
{

    protected array $node_cfg              = [
        'send'      => 1619, //发出集货
        'export'    => 1620, //干线报关
        'trunk'     => 1621, //干线运输
        'import'    => 1622, //干线清关
        'transport' => 1623, //落地转运
    ];
    #[Inject]
    protected Crypt $crypt;
    protected int   $deliveryStationStatus = 50;//发往到集货站的状态

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
