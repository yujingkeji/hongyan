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

class FlowModel extends HomeModel
{
    protected ?string $table = 'flow';

    public function getFlowIdAttribute($value)
    {
        return !empty($value) ? (string)$value : '';
    }

    public function node()
    {
        return $this->hasMany(FlowNodeModel::class, 'flow_id', 'flow_id');
    }

    public function reviewer()
    {
        return $this->hasMany(FlowNodeReviewerModel::class, 'flow_id', 'flow_id');
    }

}
