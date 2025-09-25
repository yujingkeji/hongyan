<?php

namespace App\Model;

class AdminLogModel extends HomeModel
{
    protected ?string $table = 'admin_log';

    public function getAddTimeAttribute($value)
    {
        if (!empty($value)) {
            return date("Y-m-d H:i:s", $value);
        }
        return '';
    }

}
