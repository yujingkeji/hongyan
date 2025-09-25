<?php

namespace App\Common\Cache\Driver;


class FileSystemDriver extends \Hyperf\Cache\Driver\FileSystemDriver
{
    protected $storePath = BASE_PATH . '/runtime/route/cache';

}