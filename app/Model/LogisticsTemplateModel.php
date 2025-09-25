<?php

declare(strict_types=1);

namespace App\Model;


class LogisticsTemplateModel extends HomeModel
{
    protected ?string $table = 'logistics_template';

    public function platform()
    {
        return $this->hasOne(ApiPlatformModel::class, 'platform_id', 'platform_id');
    }


}
