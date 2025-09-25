<?php

declare(strict_types=1);

namespace App\Model;


class WarehouseParcelLocationModel extends HomeModel
{
    protected ?string $table = 'warehouse_parcel_location';


    const STATUS_OUT = 0; #已取出
    const STATUS_IN = 1; #未取出 在货架
    const STATUS_MERGE = 2; #集运合并
    const STATUS_SEND = 3; #出库发货

    /**
     * @DOC   : 关联货位
     * @Name  : storage_location
     * @Author: wangfei
     * @date  : 2025-02 16:21
     * @return \Hyperf\Database\Model\Relations\HasOne
     *
     */
    public function storage_location()
    {
        return $this->hasOne(WarehouseStorageLocationModel::class, 'storage_location_id', 'storage_location_id');
    }
}
