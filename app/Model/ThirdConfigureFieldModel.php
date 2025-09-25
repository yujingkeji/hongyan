<?php

declare(strict_types=1);

namespace App\Model;


class ThirdConfigureFieldModel extends HomeModel
{
    protected ?string $table = 'third_configure_field';

    public function fieldValue()
    {
        return $this->hasOne(MemberThirdConfigureItemModel::class, 'field', 'field');
    }

}
