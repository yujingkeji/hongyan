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
return [
    'default' =>
        [
            'driver' => Hyperf\Cache\Driver\RedisDriver::class,
            'packer' => Hyperf\Codec\Packer\IgbinarySerializerPacker::class,
            'prefix' => 'c:',
        ],
    'file'    =>
        [
            'driver' => Hyperf\Cache\Driver\FileSystemDriver::class,
            //'packer' => Hyperf\Codec\Packer\PhpSerializerPacker::class,
            //'packer' => Hyperf\Codec\Packer\JsonPacker::class,
            'packer' => Hyperf\Codec\Packer\IgbinarySerializerPacker::class,
            'prefix' => 'c:',
        ],
    //路由缓存、可以随时删除
    'route'   =>
        [
            'driver' => App\Common\Cache\Driver\FileSystemDriver::class,
            'packer' => Hyperf\Codec\Packer\IgbinarySerializerPacker::class,
            'prefix' => 'c:',
        ],
];
