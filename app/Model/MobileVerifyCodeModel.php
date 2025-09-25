<?php

declare (strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class MobileVerifyCodeModel extends Model
{

    protected ?string $table = 'mobile_verify_code';

    protected ?string $dateFormat = 'U';

    /**
     * @DOC  验证码标签 power=>false-无需登录可以请求发送 true-必须先登录
     */
    public const CODE_FLAG = [
        1 => ['name' => '绑定手机', 'power' => true],
        2 => ['name' => '换绑手机-第一步', 'power' => true],
        3 => ['name' => '换绑手机-第二步', 'power' => true],
        4 => ['name' => '注册账号', 'power' => false],
    ];
}
