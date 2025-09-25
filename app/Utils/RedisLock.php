<?php
/*
created by PhpStorm
* USER CHENQINGPENG
* DATE 2025 / 2 / 18 13:24
*/

namespace App\Utils;


use Hyperf\Redis\Redis;

class RedisLock
{
    protected Redis $redis;
    protected string $key;
    protected int $ttl;

    /**
     * 构造函数，接受实现了 RedisClientInterface 接口的对象
     *
     * @param RedisClientInterface $redis
     */
    public function __construct()
    {
        $this->redis = make(Redis::class);
    }

    /**
     * 获取锁
     *
     * @param string $key 锁的键名
     * @param int $ttl 锁的过期时间（秒）
     * @return bool 是否成功获取锁
     */
    public function lock(string $key, int $ttl): bool
    {
        $this->key = $key;
        $this->ttl = $ttl;

        // 使用 SETNX 命令尝试获取锁
        $result = $this->redis->set($this->key, uniqid(), ['nx', 'ex' => $this->ttl]);

        return $result === true;
    }

    /**
     * 释放锁
     *
     * @return bool 是否成功释放锁
     */
    public function unlock(string $key): bool
    {
        if (empty($key)) {
            return false;
        }

        // 使用 Lua 脚本确保只有当前持有锁的客户端可以释放锁
        $luaScript = "
            if redis.call('get', KEYS[1]) == ARGV[1] then
                return redis.call('del', KEYS[1])
            else
                return 0
            end
        ";

        $value  = $this->redis->get($key);
        $result = $this->redis->eval($luaScript, [$key, $value], 1);

        return $result === 1;
    }
}
