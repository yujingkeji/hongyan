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

class ParcelImportModel extends HomeModel
{
    protected ?string $table = 'parcel_import';


    public function exception()
    {
        return $this->hasMany(ParcelExceptionItemModel::class, 'order_sys_sn', 'order_sys_sn');
    }

    /**
     * @DOC 结算校验 当地订单是否走到清关完成
     * @Name   bl_node
     * @Author wangfei
     * @date   2023/10/13 2023
     * @return \Hyperf\Database\Model\Relations\HasOne
     */
    public function bl_node()
    {
        return $this->hasOne(BlNodeModel::class, 'bl_sn', 'bl_sn');
    }

    public function bl()
    {
        return $this->hasOne(BlModel::class, 'bl_sn', 'bl_sn');
    }
}
