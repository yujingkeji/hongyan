<?php

namespace App\Common\Lib;

use Hyperf\Redis\Redis;

/**
 *  使用方法
 *  $config['host']     = env("Redis.host");
 * $config['port']     = env("Redis.port");
 * $config['password'] = env("Redis.password");
 * $config['expire']   = env("Redis.expire");
 *
 * $unique = new Unique($config);
 * $unique->uniqueTime();
 * $item['field']      = 'sku_code';
 * $item['field_name'] = "商品编码";
 * $item['field_type'] = 0;
 * $item['field_text'] = $unique->unique();
 *
 * Class Unique
 * @package app\common\lib
 */
class  Unique
{
    protected mixed $redis;
    protected string $uniqueTime = '';

    public function __construct()
    {
        $this->redis = \Hyperf\Support\make(Redis::class);
        $this->uniqueTime();
    }


    /**
     * @DOC 生成批次号
     */
    public function uniqueTime()
    {
        $uniqueTime       = time();
        $this->uniqueTime = $uniqueTime;
        return $uniqueTime; // 生成批次号;
    }

    /**
     * @DOC 生成有序的唯一单号
     */
    public function unique($dataCenterId = '', $workId = '')
    {
        $reqNoKey = 'unique' . $dataCenterId . '_' . $workId . '_' . $this->uniqueTime;
        $reqNo    = $this->redis->incr($reqNoKey); // 将redis值加1
        $this->redis->expire($reqNoKey, 60); // 设置redis过期时间,避免垃圾数据过多
        $Max   = 9999;
        $reqNo = str_pad($reqNo, 4, '0', STR_PAD_LEFT);
        if (!empty($dataCenterId)) {
            $dataCenterId = str_pad($dataCenterId, 2, '0', STR_PAD_LEFT);
        }
        if (!empty($workId)) {
            $workId = str_pad($dataCenterId, 2, '0', STR_PAD_LEFT);
        }
        $unique = $this->uniqueTime . $dataCenterId . $workId . $reqNo; // 生成订单号
        if ($reqNo >= $Max) {
            if ($this->uniqueTime == $this->uniqueTime()) {
                sleep(1);
            }
            $this->uniqueTime();
        }
        return $unique;
    }

    /**
     * @DOC   : 获取批次号
     * @Name  : batch
     * @Author: wangfei
     * @date  : 2022-11-11 2022
     * @param string $dataCenterId
     * @param string $workId
     * @return string
     */
    public function batch($dataCenterId = '', $workId = "")
    {
        // 设置redis键值，每秒钟的请求次数
        $reqNoKey = 'batch' . '_' . $dataCenterId . '_' . $workId . '_' . $this->uniqueTime;
        $reqNo    = $this->redis->incr($reqNoKey); // 将redis值加1
        $this->redis->expire($reqNoKey, 3); // 设置redis过期时间,避免垃圾数据过多
        $Max   = 9999;
        $reqNo = str_pad($reqNo, 4, '0', STR_PAD_LEFT);
        if (!empty($dataCenterId)) {
            $dataCenterId = str_pad($dataCenterId, 2, '0', STR_PAD_LEFT);
        }
        if (!empty($workId)) {
            $workId = str_pad($dataCenterId, 2, '0', STR_PAD_LEFT);
        }
        if ($reqNo >= $Max) {
            if ($this->uniqueTime == $this->uniqueTime()) {
                sleep(1);
            }
            $this->uniqueTime();
        }
        $batch = $this->uniqueTime . $dataCenterId . $workId . $reqNo; // 生成批次号;
        return $batch; // 生成批次号;
    }

    /**
     * @DOC   :异常号
     * @Name  : exp
     * @Author: wangfei
     * @date  : 2023-04-07 2023
     * @param string $dataCenterId
     * @param string $workId
     * @return string
     */
    public function exception(string $dataCenterId = '', string $workId = '')
    {
        $reqNoKey = 'exception' . $dataCenterId . '_' . $workId . '_' . $this->uniqueTime;
        $reqNo    = $this->redis->incr($reqNoKey); // 将redis值加1
        $this->redis->expire($reqNoKey, 60); // 设置redis过期时间,避免垃圾数据过多
        $Max   = 999;
        $reqNo = str_pad($reqNo, 3, '0', STR_PAD_LEFT);
        if (!empty($dataCenterId)) {
            $dataCenterId = str_pad($dataCenterId, 2, '0', STR_PAD_LEFT);
        }
        if (!empty($workId)) {
            $workId = str_pad($dataCenterId, 2, '0', STR_PAD_LEFT);
        }
        $unique = $this->uniqueTime . $dataCenterId . $workId . $reqNo; // 生成订单号
        if ($reqNo >= $Max) {
            if ($this->uniqueTime == $this->uniqueTime()) {
                sleep(1);
            }
            $this->uniqueTime();
        }
        return $unique;
    }


    /**
     * @DOC   : 支付交易号
     * @Name  : paymentSN
     * @Author: wangfei
     * @date  : 2023-05-25 2023
     * @param string $dataCenterId
     * @param string $workId
     * @return string
     */
    public function paymentSN(string $dataCenterId = '', string $workId = '')
    {
        $reqNoKey = 'paymentSN' . $dataCenterId . '_' . $workId . '_' . $this->uniqueTime;
        $reqNo    = $this->redis->incr($reqNoKey); // 将redis值加1
        $this->redis->expire($reqNoKey, 60); // 设置redis过期时间,避免垃圾数据过多
        $Max   = 999;
        $reqNo = str_pad($reqNo, 3, '0', STR_PAD_LEFT);
        if (!empty($dataCenterId)) {
            $dataCenterId = str_pad($dataCenterId, 2, '0', STR_PAD_LEFT);
        }
        if (!empty($workId)) {
            $workId = str_pad($dataCenterId, 2, '0', STR_PAD_LEFT);
        }
        $unique = $this->uniqueTime . $dataCenterId . $workId . $reqNo; // 生成订单号
        if ($reqNo >= $Max) {
            if ($this->uniqueTime == $this->uniqueTime()) {
                sleep(1);
            }
            $this->uniqueTime();
        }
        return $unique;
    }
}


