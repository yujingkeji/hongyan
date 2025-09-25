<?php

declare(strict_types=1);

namespace App\Model;

class MemberLineModel extends HomeModel
{
    protected ?string $table = 'member_line';
    /**
     * @var string 主键
     */
    protected string $primaryKey = 'member_line_id';

    public function getStartTimeAttr($value)
    {
        return !empty($value) ? date("Y-m-d H:i:s", $value) : '';
    }

    public function getEndTimeAttr($value)
    {
        return !empty($value) ? date("Y-m-d H:i:s", $value) : '';
    }

    public function member()
    {
        return $this->hasOne(MemberModel::class, 'uid', 'uid');
    }

    public function line()
    {
        return $this->hasOne(LineModel::class, 'line_id', 'line_id');
    }

}
