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


class MemberAmountLogModel extends HomeModel
{
    protected ?string $table = 'member_amount_log';
    /**
     * @var string 主键
     */
    protected string $primaryKey = 'log_id';

    const UPDATED_AT = null;

    public function config()
    {
        return $this->hasOne(ConfigModel::class,'cfg_id','cfg_id');
    }

    public function recharge()
    {
        return $this->hasOne(MemberRechargeModel::class,'amount_log_id','log_id');
    }

}
