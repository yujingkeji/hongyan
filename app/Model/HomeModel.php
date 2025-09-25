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

class HomeModel extends Model
{
    public bool $timestamps = false;

    public function getOrderSysSnAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    public function getBlSnAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    public function getPaymentSnAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    public function getBatchSnAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    public function getAreaAttribute($value)
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


    public function getPriceItemAttribute($value)
    {
        return !empty($value) ? json_decode($value, true) : $value;
    }


}
