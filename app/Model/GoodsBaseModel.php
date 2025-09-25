<?php

declare(strict_types=1);

namespace App\Model;

class GoodsBaseModel extends HomeModel
{
    protected ?string $table = 'goods_base';


    /**
     * @var string 主键
     */
    protected string $primaryKey = 'base_id';


    // record_status  '备案状态\r\n0:待维护\r\n1:已维护\r\n2:审核中\r\n3:已通过\r\n4:已拒绝',

    const STATUS_RECORD_CHECKING = 2;
    const STATUS_RECORD_PASS = 3; // 已通过
    const STATUS_RECORD_FAIL = 4;

    #goods_base_id 转成字符串输出
    public function getGoodsBaseIdAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    public function category()
    {
        return $this->hasOne(RecordCategoryGoodsModel::class, 'id', 'category_item_id');
    }

    public function sku()
    {
        return $this->hasMany(GoodsSkuModel::class, 'goods_base_id', 'goods_base_id');
    }

    public function cc()
    {
        return $this->hasOne(GoodsCcModel::class, 'goods_base_id', 'goods_base_id');
    }

    public function bc()
    {
        return $this->hasOne(GoodsBcModel::class, 'goods_base_id', 'goods_base_id');
    }

    public function member()
    {
        return $this->hasOne(MemberModel::class, 'uid', 'member_uid');
    }

}
