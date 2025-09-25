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


class ChannelTrunkModel extends HomeModel
{
    protected ?string $table = 'channel_trunk';
    /**
     * @var string 主键
     */
    protected string $primaryKey = 'item_id';

    const UPDATED_AT = null;

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
}
