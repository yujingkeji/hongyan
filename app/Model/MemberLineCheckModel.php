<?php

namespace App\Model;

class MemberLineCheckModel extends HomeModel
{
    protected ?string $table = 'member_line_check';

    public function getAddTimeAttribute($value)
    {
        if (!empty($value)) {
            return date("Y-m-d H:i:s", $value);
        }
        return '';
    }

    public function getCheckTimeAttribute($value)
    {
        if (!empty($value)) {
            return date("Y-m-d H:i:s", $value);
        }
        return '';
    }

    public function user()
    {
        return $this->hasOne(AdminUserModel::class, 'uid', 'check_uid');
    }

    public function memberLine()
    {
        return $this->hasOne(MemberLineModel::class, 'member_line_id', 'member_line_id');
    }

    public function line()
    {
        return $this->hasOne(LineModel::class, 'line_id', 'line_id');
    }

}
