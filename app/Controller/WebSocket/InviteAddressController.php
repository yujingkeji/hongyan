<?php

namespace App\Controller\WebSocket;

use Hyperf\Contract\OnCloseInterface;
use Hyperf\Contract\OnMessageInterface;
use Hyperf\Contract\OnOpenInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Redis\Redis;
use Hyperf\WebSocketServer\Sender;

class InviteAddressController implements OnMessageInterface, OnOpenInterface, OnCloseInterface
{
    #[Inject]
    protected Sender $sender;

    public function onMessage($server, $frame): void
    {
        $redis = \Hyperf\Support\make(Redis::class);
        # 存储用户：fd
        $data = $frame->data;
        if (!empty($data)) {
            $data = json_decode($data, true);
            if (!empty($data['key']) && !empty($data['type'])) {
                switch ($data['type']) {
                    // 邀请人存储订单数据
                    case 'invite':
                        if ($redis->get('order_member_key:' . $data['key'])) {
                            // 本人点击邀请
                            $time = $redis->ttl('order_address:' . $data['key']);
                            $redis->setex('order_member_key:' . $data['key'], $time, $frame->fd);
                            break;
                        }
                        $redis->setex('order_member_key:' . $data['key'], 86400, $frame->fd);
                        $redisData                    = $data['data'];
                        $redisData['send_id']         = $data['send_id'] ?? 0;
                        $redisData['expiration_date'] = time() + 86400;
                        $redisData['is_complete']     = false;
                        $redisData['status']          = true;
                        $redis->setex('order_address:' . $data['key'], 86400, json_encode($redisData, true));
                        $this->sender->push($frame->fd, '已邀请');
                        break;
                    // 被邀请人提交订单数据
                    case 'invited':
                        $redisData = $redis->get('order_address:' . $data['key']);
                        if (!empty($redisData)) {
                            $invite_fd = $redis->get('order_member_key:' . $data['key']); // 发起邀请人
                            $this->sender->push((int)$invite_fd, $redisData);
                        }
                        break;
                    // 被邀请人获取订单数据
                    case 'get':
                        $data = $redis->get('order_address:' . $data['key']);
                        if (!empty($data)) {
                            $orderData = json_decode($data, true);
                            // 已经提交过
                            if (!empty($orderData['invited'])) {
                                $orderData['status'] = false;
                                $return              = json_encode($orderData, true);
                                $this->sender->push($frame->fd, $return);
                                break;
                            }
                            $orderData['status'] = true;
                        } else {
                            // 已提交返回状态
                            $orderData['data']   = [];
                            $orderData['status'] = false;
                        }
                        $return = json_encode($orderData, true);
                        $this->sender->push($frame->fd, $return);
                        break;
                    // 完成订单
                    case 'complete':
                        $redisData = $redis->get('order_address:' . $data['key']);
                        if (!empty($redisData)) {
                            $orderData                = json_decode($redisData, true);
                            $orderData['is_complete'] = true;
                            $time                     = $redis->ttl('order_address:' . $data['key']);
                            $redis->setex('order_address:' . $data['key'], $time, json_encode($orderData, true));
                        }
                        break;
                    case 'ping':
                        $redisData = $redis->get('order_address:' . $data['key']);
                        if (!empty($redisData)) {
                            $this->sender->push($frame->fd, $redisData);
                        }
                        break;
                }
            }

            $this->sender->push($frame->fd, '');
        }
    }

    public function onClose($server, int $fd, int $reactorId): void
    {
//        echo "会话 {$fd} 已断开连接" . PHP_EOL;
    }

    public function onOpen($server, $request): void
    {
        if ($server->isEstablished($request->fd)) {
            // 会话处于连接状态，可以发送消息
            $server->push($request->fd, '已经连接上了');
        } else {
            // 会话已断开，无法发送消息
//            echo "会话 {$request->fd} 已断开连接";
        }
    }
}

