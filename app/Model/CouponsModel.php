<?php

namespace App\Model;

use Hyperf\Database\Model\SoftDeletes;

class CouponsModel extends HomeModel
{
    protected ?string $table = 'coupons';

    //优惠券收费代码
    const COUPONS_COST_CODE = '13306'; //优惠券收费代码 config表 model=12199 cfg_id =13306
    /**
     * var @var string 平台券
     */
    const COUPONS_TYPE_PLATFORM = '30301';
    /**
     * var @var string 店铺券
     */
    const COUPONS_TYPE_SHOP = '30302';

    //优惠券使用、以及支付状态
    const COUPONS_USE_STATUS = [
        '0' => '未使用',
        '1' => '已使用',
        '2' => '已过期',
        '3' => '已支付',
        '4' => '已退款',
    ];


    use SoftDeletes;

    public function getCouponIdAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    // CouponsModel.php
    public function products()
    {
        return $this->hasMany(CouponsProductsModel::class, 'coupon_id', 'coupon_id');
    }

    /**
     * @DOC   : 已经存在的优惠券
     * @Name  : has_coupons
     * @Author: wangfei
     * @date  : 2025-02 20:56
     * @return \Hyperf\Database\Model\Relations\HasMany
     *
     */
    public function has_coupons()
    {
        return $this->hasMany(CouponsMemberModel::class, 'coupon_id', 'coupon_id');

    }

}
