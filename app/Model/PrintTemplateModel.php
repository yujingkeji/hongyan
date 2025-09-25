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


class PrintTemplateModel extends HomeModel
{
    protected ?string $table = 'print_template';

    /**
     * @var string 主键
     */
    protected string $primaryKey = 'template_id';
    // 设置常量 28001:标签,28005:面单,28006:其它,28010:出库交接单,28011:入库交接单,28012:拣货单

    const TEMPLATE_TYPE_LABEL = 28001;//标签
    const TEMPLATE_Parcel_Waybill = 28005;//面单
    const TEMPLATE_PICK_UP = 28006;//拣货单
    const TEMPLATE_OUT_HANDOVER = 28010;//出库交接单
    const TEMPLATE_IN_HANDOVER = 28011;//入库交接单
    const TEMPLATE_OTHERS = 28050;//其他


    public function platform()
    {
        return $this->hasOne(ApiPlatformModel::class, 'platform_id', 'platform_id');
    }

    public function config()
    {
        return $this->hasOne(ConfigModel::class, 'cfg_id', 'cfg_id');
    }


}
