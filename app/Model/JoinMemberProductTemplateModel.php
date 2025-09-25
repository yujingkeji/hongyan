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

class JoinMemberProductTemplateModel extends HomeModel
{
    protected ?string $table = 'join_member_product_template';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'product_template_item_id';


    /**
     * @DOC   : 关联产品信息
     * @Name  : product
     * @Author: wangfei
     * @date  : 2023-04-20 2023
     * @return \think\model\relation\HasOne
     */
    public function product()
    {
        return $this->hasOne(ProductModel::class, 'pro_id', 'product_id');
    }

    /**
     * @DOC   : 会员信息
     * @Name  : member
     * @Author: wangfei
     * @date  : 2023-04-20 2023
     * @return \think\model\relation\HasOne
     */
    public function member()
    {
        return $this->hasOne(MemberModel::class, 'uid', 'use_member_uid');
    }

    /**
     * @DOC   : 价格模板
     * @Name  : priceTemplate
     * @Author: wangfei
     * @date  : 2023-04-20 2023
     * @return \think\model\relation\HasOne
     */
    public function price_template()
    {
        return $this->hasOne(PriceTemplateModel::class, 'template_id', 'price_template_id');
    }
}
