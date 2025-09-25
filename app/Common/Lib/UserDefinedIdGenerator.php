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


namespace App\Common\Lib;

use Hyperf\Snowflake\IdGenerator\SnowflakeIdGenerator;

class UserDefinedIdGenerator
{

    protected SnowflakeIdGenerator $idGenerator;

    public function __construct(SnowflakeIdGenerator $idGenerator)
    {
        $this->idGenerator = $idGenerator;
    }

    public function generate(int $userId, int $centerId = 0)
    {
        $meta = $this->idGenerator->getMetaGenerator()->generate();

        // 获取主机名并生成 workerId
        #$hostname = gethostname();
        #$workerId = crc32($hostname) % 1024; // 确保 workerId 在 0-1023 范围内
        $workerId = $userId % 1024;          // 确保 workerId 在 0-1023 范围内
        // 设置 dataCenterId
        $dataCenterId = $centerId % 32;      // 确保 dataCenterId 在 0-31 范围内
        #return $this->idGenerator->generate($meta->setWorkerId($workerId));
        return $this->idGenerator->generate($meta->setWorkerId($workerId)->setDataCenterId($dataCenterId));
    }


    public function degenerate(int $id)
    {
        return $this->idGenerator->degenerate($id);
    }

}
