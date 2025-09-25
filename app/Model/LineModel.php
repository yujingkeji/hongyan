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

class LineModel extends HomeModel
{
    protected ?string $table = 'line';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'line_id';

    public function getAddTimeAttribute($value)
    {
        if (!empty($value)) {
            return date("Y-m-d H:i:s", $value);
        }
        return '';
    }

    public function send()
    {
        return $this->hasOne(CountryCodeModel::class, 'country_id', 'send_country_id');
    }

    public function target()
    {
        return $this->hasOne(CountryCodeModel::class, 'country_id', 'target_country_id');
    }

    public function target_area()
    {
        return $this->hasOne(CountryAreaModel::class, 'country_id', 'target_country_id');
    }

    public function flow()
    {
        return $this->hasOne(FlowModel::class, 'flow_id', 'flow_id');
    }

    public function member()
    {
        return $this->hasOne(MemberModel::class, 'uid', 'uid');
    }

}
