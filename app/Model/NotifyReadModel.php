<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\Database\Model\Relations\HasOne;

class NotifyReadModel extends HomeModel
{

    protected ?string $table = 'notify_read';

    public function getReadTimeAttribute($value): string
    {
        if ($value) {
            return date('Y-m-d H:i:s', $value);
        }
        return '';
    }

    public function notify(): HasOne
    {
        return $this->hasOne(NotifyModel::class, 'id', 'nid');
    }

}
