#!/bin/bash

basepath=$(cd `dirname $0`; pwd)
cd $basepath || { echo "Error: Unable to change directory."; exit 1; }

# 终止与"order_hjd"相关的进程
echo "删除 'order_hjd' 相关进程..."
ps -aux | grep '[o]rder_hjd' | grep -v grep | awk '{print $2}' | xargs -I{} sh -c 'kill -9 {}'


# 检查并处理hyperf.pid文件
if [ -f "../runtime/hyperf.pid" ]; then
    echo "删除 runtime 缓存..."
    # 获取PID并终止进程
    cat ../runtime/hyperf.pid | xargs -I{} sh -c 'kill -9 {}'

    # 删除pid文件和container目录
    rm -f ../runtime/hyperf.pid
    rm -rf ../runtime/container
    echo "删除完成."
fi
echo "正在启动..."
php hyperf.php server:watch
echo "启动成功"
