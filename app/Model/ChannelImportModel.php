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


class ChannelImportModel extends HomeModel
{
    protected ?string $table = 'channel_import';
    /**
     * @var string 主键
     */
    protected string $primaryKey = 'item_id';

    const UPDATED_AT = null;

    public function channelSupervision()
    {
        return $this->hasOne(CustomsSupervisionModel::class, 'supervision_id', 'supervision_id');
    }

    /**
     * @DOC   :
     * @Name  : channelSev
     * @Author: wangfei
     * @date  : 2023-06-13 2023
     * @return \Hyperf\Database\Model\Relations\HasOne
     */
    public function channelSev()
    {
        return $this->hasOne(SevModel::class, 'sev_id', 'sev_id');
    }

    public function template()
    {
        return $this->hasOne(PriceTemplateModel::class, 'template_id', 'price_template_id');
    }

    public function port()
    {
        return $this->hasOne(PortModel::class, 'port_id', 'port_id');
    }

}
