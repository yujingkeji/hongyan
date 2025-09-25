<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\Database\Model\Relations\HasOne;

class NotifyModel extends HomeModel
{

    protected ?string $table = 'notify';

    public function getAddTimeAttribute($value): string
    {
        if ($value) {
            return date('Y-m-d H:i:s', $value);
        }
        return '';
    }

    public function getMessageAttribute($value): string
    {
        if ($value) {
            return json_decode($value, true);
        }
        return '';
    }

    public function member(): HasOne
    {
        return $this->hasOne(MemberModel::class, 'uid', 'member_uid');
    }

    public function read(): hasOne
    {
        return $this->hasOne(NotifyReadModel::class, 'notify_id', 'notify_id');
    }

    public function type(): HasOne
    {
        return $this->hasOne(ConfigModel::class, 'cfg_id', 'type');
    }
}
