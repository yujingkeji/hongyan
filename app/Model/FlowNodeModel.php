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

class FlowNodeModel extends HomeModel
{
    protected ?string $table = 'flow_node';


    public function reviewer()
    {
        return $this->hasMany(FlowNodeReviewerModel::class, 'node_id', 'node_id');
    }

}
