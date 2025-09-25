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


class BlNodeModel extends HomeModel
{

    protected ?string $table      = 'bl_node';
    protected string  $primaryKey = 'node_id';

    /**
     * @var string 主键
     */
    public function getBlSnAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    public function bl()
    {
        return $this->hasOne(BlModel::class, 'bl_sn', 'bl_sn');
    }

    public function parcel_send()
    {
        return $this->hasMany(ParcelSendModel::class, 'bl_sn', 'bl_sn');
    }

    public function parcel_export()
    {
        return $this->hasMany(ParcelExportModel::class, 'bl_sn', 'bl_sn');
    }

    public function parcel_trunk(): \Hyperf\Database\Model\Relations\HasMany
    {
        return $this->hasMany(ParcelTrunkModel::class, 'bl_sn', 'bl_sn');
    }

    public function parcel_import(): \Hyperf\Database\Model\Relations\HasMany
    {
        return $this->hasMany(ParcelImportModel::class, 'bl_sn', 'bl_sn');
    }

    public function parcel_transport()
    {
        return $this->hasMany(ParcelTransportModel::class, 'bl_sn', 'bl_sn');
    }

}
