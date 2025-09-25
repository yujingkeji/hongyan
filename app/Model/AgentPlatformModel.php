<?php

namespace App\Model;

use App\Common\Lib\UploadAliOssSev;
use App\Controller\Home\Member\UploadController;
use Hyperf\Database\Model\Relations\HasOne;

class AgentPlatformModel extends HomeModel
{
    protected ?string $table = 'agent_platform';

    protected array $casts = [
        'add_time'    => 'datetime:Y-m-d H:i:s',
        'update_time' => 'datetime:Y-m-d H:i:s'
    ];

    const UPDATED_AT = 'update_time';

    public function getWebLogoAttribute($value)
    {
        if ($value) {
            return $this->Image($value);
        }
        return '';
    }

    protected function Image($file_url)
    {
        list($ret, $config) = (new UploadController)->configuration();
        if (!$ret) {
            return '';
        }
        $Upload  = new UploadAliOssSev($config);
        $img_url = $Upload->config['Host'] . '/' . $file_url;
        return $Upload->signUrl($img_url);
    }


}
