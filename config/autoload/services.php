<?php


$services = [
    'BaseService'   => App\JsonRpc\BaseServiceInterface::class,
    'RecordService' => App\JsonRpc\RecordServiceInterface::class,
    'RouteService'  => App\JsonRpc\RouteServiceInterface::class,
    'CryptService'  => App\JsonRpc\CryptServiceInterface::class,
];

foreach ($services as $name => $interface) {
    $consumers[] = [
        'name'     => $name,
        'service'  => $interface,
        'registry' => [
            'protocol' => 'nacos',
            //  'address'  => 'Enter the address of service registry',
            'address'  => 'http://' . env('NACOS_HOST') . ':' . env('NACOS_PORT'),
        ],
        'nodes'    => [
            // Provide the host and port of the service provider.
            // ['host' => 'The host of the service provider', 'port' => 9502]
            // ['host' => '172.16.64.151', 'port' => 8870],
            // ['host' => '172.16.64.151', 'port' => 8880],
            //['host' => '172.16.64.151', 'port' => 8890],
        ],
        'options'  => [
            'connect_timeout' => 5.0,
            'recv_timeout'    => 5.0,
            'settings'        => [
                // 根据协议不同，区分配置
                //'open_eof_split' => true,
                'open_eof_check' => true,
                'package_eof'    => "\r\n",
                /*'open_length_check' => true,
                'package_length_type' => 'N',
                'package_length_offset' => 0,
                'package_body_offset' => 4,
                'package_max_length' => 1024 * 1024 * 20,*/
            ],
            // 重试次数，默认值为 2，收包超时不进行重试。暂只支持 JsonRpcPoolTransporter
            'retry_count'     => 2,
            // 重试间隔，毫秒
            'retry_interval'  => 100,
            // 使用多路复用 RPC 时的心跳间隔，null 为不触发心跳
            'heartbeat'       => 30,
            // 当使用 JsonRpcPoolTransporter 时会用到以下配置
            'pool'            => [
                'min_connections' => 1,
                'max_connections' => 32,
                'connect_timeout' => 10.0,
                'wait_timeout'    => 3.0,
                'heartbeat'       => -1,
                'max_idle_time'   => 60.0,
            ],
        ],
    ];
}

return [
    'consumers' => $consumers,
    'drivers'   => [
        'nacos' => [
            // nacos server url like https://nacos.hyperf.io, Priority is higher than host:port
            // 'url' => '',
            // The nacos host info
            'host'       => env('NACOS_HOST'),
            'port'       => env('NACOS_PORT'),
            // The nacos account info
            'username'   => env('NACOS_USERNAME'),
            'password'   => env('NACOS_PASSWORD'),
            'guzzle'       => [
                'config' => null,
            ],
            'group_name' => 'DEFAULT_GROUP',
            'namespace_id' => 'public',
            'heartbeat'  => 10,
            'ephemeral'    => true, // 是否注册临时实例
        ],
    ],
];

