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


class CustomsSupervisionModel extends HomeModel
{
    protected ?string $table = 'customs_supervision';
    /**
     * @var string 主键
     */
    protected string $primaryKey = 'supervision_id';

    const UPDATED_AT = null;

    // 处理一般贸易时的CODE
    const GeneralTrading = '0110';

    public function getAddTimeAttribute($value)
    {
        if (!empty($value)) {
            return date("Y-m-d H:i:s", $value);
        }
        return '';
    }

    public function country()
    {
        return $this->hasOne(CountryCodeModel::class, 'country_id', 'country_id');
    }

    public function cfg()
    {
        return $this->hasOne(CategoryModel::class, 'cfg_id', 'export_cfg_id');
    }


}
