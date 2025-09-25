<?php

declare(strict_types=1);

namespace App\Model;

use App\Common\Lib\Crypt;
use App\Common\Lib\UploadAliOssSev;
use App\Controller\Home\Member\UploadController;
use Hyperf\Database\Model\Relations\HasOne;

class MemberInfoModel extends HomeModel
{
    protected ?string $table = 'member_info';

    public function getPhotoPathAttribute($value): array
    {
        if ($value) {
            $data = explode(',', $value);
            foreach ($data as &$v) {
                $v = $this->Image($v);
            }
            return $data;
        }
        return [];
    }

    public function getCoPhotoPathAttribute($value): array
    {
        if ($value) {
            $data = explode(',', $value);
            foreach ($data as &$v) {
                $v = $this->Image($v);
            }
            return $data;
        }
        return [];
    }

    public function getCardNumberAttribute($value): string
    {
        if ($value) {
            return (new Crypt())->decrypt($value);
        }
        return '';
    }

    public function getCardNameAttribute($value): string
    {
        if ($value) {
            return (new Crypt())->decrypt($value);
        }
        return '';
    }

    public function getCoCardNumberAttribute($value): string
    {
        if ($value) {
            return (new Crypt())->decrypt($value);
        }
        return '';
    }


    /**
     * @DOC 获取图片
     */
    public function Image($file_url)
    {
        list($ret, $config) = (new UploadController)->configuration();
        if (!$ret) {
            return $config;
        }
        $Upload  = new UploadAliOssSev($config);
        $img_url = $Upload->config['Host'] . '/' . $file_url;
        return $Upload->signUrl($img_url);
    }

    public function category(): HasOne
    {
        return $this->hasOne(CategoryModel::class, 'cfg_id', 'card_type');
    }

    public function member(): HasOne
    {
        return $this->hasOne(MemberModel::class, 'uid', 'member_uid');
    }

    public function joins()
    {
        return $this->hasOne(MemberModel::class, 'uid', 'parent_join_uid');
    }


}
