#!/bin/bash

basepath=$(cd `dirname $0`; pwd)



cd $basepath
ps -aux | grep skeleton |grep -v grep
ps -aux | grep study |grep -v grep|cut -c 0-6

ps -aux | grep order_hjd |grep -v grep|cut -c 0-6|xargs kill -9

#查看端口
netstat -anp | grep 9503
netstat -anp | grep 9504

#php ./bin/hyperf.php server:watch
php ./bin/hyperf.php start


 php bin/hyperf.php vendor:publish hyperf/crontab
 php bin/hyperf.php vendor:publish hyperf/crontab

  $logger = \Hyperf\Support\make(LoggerFactory::class)->get('log', 'text');
        $logger->info('Url:', [$Url]);
        $logger->info('body:', [$body]);


