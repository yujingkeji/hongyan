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


class CsvExportTemplateModel extends HomeModel
{

    protected ?string $table = 'csv_export_template';

    protected string $primaryKey = 'template_id';

    // 时间
    public function getAddTimeAttribute($value)
    {
        if (!empty($value)) {
            return date("Y-m-d H:i:s", $value);
        }
        return '';
    }


    public function item()
    {
        return $this->hasMany(CsvExportTemplateItemModel::class, 'template_id', 'template_id')->orderBy('sort', 'asc');
    }


}
