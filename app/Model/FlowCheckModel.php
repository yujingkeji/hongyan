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

class FlowCheckModel extends HomeModel
{
    protected ?string $table = 'flow_check';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'check_id';

    public function item()
    {
        return $this->hasMany(FlowCheckItemModel::class, 'check_id', 'check_id');
    }

    public function version()
    {
        return $this->hasOne(PriceTemplateVersionModel::class, 'check_id', 'check_id');
    }

    public function getFlowAttribute($value)
    {
        return !empty($value) ? json_decode($value, true) : $value;
    }


    public function getFlowIdAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    public function getNodeIdAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }


}
