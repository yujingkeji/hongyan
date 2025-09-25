<?php

namespace App\Model;

use Hyperf\Database\Model\SoftDeletes;

class CouponsProductsModel extends HomeModel
{
    protected ?string $table = 'coupons_products';


    public function getCouponIdAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    public function getProductIdAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    public function product()
    {
        return $this->hasOne(ProductModel::class, 'pro_id', 'product_id');
    }
}
