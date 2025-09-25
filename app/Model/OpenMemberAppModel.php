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

namespace App\Model;

use Hyperf\ModelCache\CacheableInterface;

/**
 * 开放平台用户APP
 */
class OpenMemberAppModel extends Model implements CacheableInterface
{
    protected ?string $table = 'open_member_app';
    /**
     * @var string 主键
     */
    protected string $primaryKey = 'app_key';
    #0：应用下线，1：创建成功 2：待审核 4：拒绝 99:上线',
    const  STATUS_DELETE  = 0;  #下线
    const  STATUS_OFFLINE = 1;  #创建成功
    const  STATUS_WAIT    = 2;  #待审核
    const  STATUS_REFUSE  = 4;  #拒绝
    const  STATUS_ONLINE  = 99; #上线


    public bool $timestamps = false;


}
