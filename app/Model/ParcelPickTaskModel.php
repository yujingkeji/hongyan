<?php

namespace App\Model;

class ParcelPickTaskModel extends HomeModel
{
    protected ?string $table = 'parcel_pick_task';

    /**
     * @var int 验货拣货任务
     */
    const Inspection = 28501; // 验货拣货任务
    /**
     * @var int 待出库拣货任务
     */
    const  waitWare = 28502; // 待出库拣货任务
    /**
     * @var int 可出库拣货任务
     */
    const  canWare = 28503; // 可出库拣货任务

    //query_table_parcel //查询包裹表
    /**
     * string $query_table_parcel 查询包裹表
     */
    const query_table_parcel = 'parcel';
    /**
     * string 集运包裹验货表
     */
    const query_table_delivery = 'station_check';


    public function getAddTimeAttribute($value)
    {
        if (!empty($value)) {
            return date("Y-m-d H:i:s", $value);
        }
        return '';
    }

    public function getPickNoAttribute($value)
    {
        return (string)$value;
    }

    public function member()
    {
        return $this->hasOne(MemberModel::class, 'uid', 'member_uid');
    }

    public function member_child()
    {
        return $this->hasOne(MemberChildModel::class, 'child_uid', 'member_child_uid');
    }


}
