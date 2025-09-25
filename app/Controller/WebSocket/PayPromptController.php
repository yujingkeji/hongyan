<?php

namespace App\Controller\WebSocket;

use Hyperf\Contract\OnCloseInterface;
use Hyperf\Contract\OnMessageInterface;
use Hyperf\Contract\OnOpenInterface;
use Hyperf\Redis\Redis;
use Hyperf\Utils\ApplicationContext;

class PayPromptController implements OnMessageInterface, OnOpenInterface, OnCloseInterface
{
    public function onMessage($server, $frame): void
    {
        $redis = \Hyperf\Support\make(Redis::class);
        # 存储用户：fd
        $redis->lPush('member_pay:' . $frame->data, $frame->fd);
        $redis->expire('member_pay:' . $frame->data, 600);
    }

    public function onClose($server, int $fd, int $reactorId): void
    {

    }

    public function onOpen($server, $request): void
    {
        if ($server->isEstablished($request->fd)) {
            // 会话处于连接状态，可以发送消息
            $server->push($request->fd, '已经连接上了');
        } else {
            // 会话已断开，无法发送消息
            echo "会话 {$request->fd} 已断开连接";
        }
    }
}

