<?php

namespace App\Model;

class CouponsMemberModel extends HomeModel
{
    protected ?string $table = 'coupons_member';

    const UPDATED_AT = null;

    public function getCouponIdAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    public function coupon()
    {
        return $this->hasOne(CouponsModel::class, 'coupon_id', 'coupon_id');
    }



}
