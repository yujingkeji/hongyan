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

class ChannelModel extends HomeModel
{
    protected ?string $table = 'channel';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'channel_id';

    public function getAddTimeAttribute($value)
    {
        return !empty($value) ? date('Y-m-d H:i:s', $value) : '';
    }


    public function send()
    {

        return $this->hasOne(ChannelSendModel::class, 'channel_id', 'channel_id');
    }

    public function export()
    {
        return $this->hasOne(ChannelExportModel::class, 'channel_id', 'channel_id');
    }

    public function import()
    {
        return $this->hasOne(ChannelImportModel::class, 'channel_id', 'channel_id');
    }

    public function transport()
    {
        return $this->hasOne(ChannelTransportModel::class, 'channel_id', 'channel_id');
    }

    public function trunk()
    {
        return $this->hasOne(ChannelTrunkModel::class, 'channel_id', 'channel_id');
    }

    public function port()
    {
        return $this->hasOne(PortModel::class, 'port_id', 'port_id');
    }

    public function line()
    {
        return $this->hasOne(LineModel::class, 'line_id', 'line_id');
    }


}
