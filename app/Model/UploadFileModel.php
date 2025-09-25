<?php

declare(strict_types=1);

namespace App\Model;


class UploadFileModel extends HomeModel
{
    protected ?string $table = 'upload_file';

    /**
     * @var string 主键
     */
    protected string $key = 'file_id';

    public function single($where, $file = ['file_id', 'file_url']): array
    {
        $data = $this->where($where)->select($file)->first();
        if (!empty($data)) {
            return $data->toArray();
        }
        return [];
    }

}
