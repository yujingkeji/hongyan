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


class BlModel extends HomeModel
{


    protected ?string $table = 'bl';

    /**
     * @var string 主键
     */
    public function getBlSnAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    public function getBoxItemAttribute($value)
    {
        return !empty($value) ? json_decode($value, true) : $value;
    }

    public function getPartItemSnAttribute($value)
    {
        $data = !empty($value) ? json_decode($value, true) : $value;
        return array_values($data);
    }


    public function node()
    {

        return $this->hasMany(BlNodeModel::class, 'bl_sn', 'bl_sn');
    }

    public function send()
    {
        return $this->hasMany(ParcelSendModel::class, 'bl_sn', 'bl_sn');
    }


}
