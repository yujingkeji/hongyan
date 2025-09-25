<?php
declare(strict_types=1);
/**
 * 倒仓扫描
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Model;

class WarehouseScanModel extends Model
{
    protected ?string $table = 'warehouse_scan';

    const SCAN_STATUS_OUT = 0; //已取出
    const SCAN_STATUS_IN = 1; //已入库
    #已存在
    const SCAN_STATUS_EXIST = 2;
    /**
     * @var string 主键
     */
    protected string $primaryKey = 'send_station_sn';


}
