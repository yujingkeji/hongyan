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
    'default'                          => [
        'handler'   => [
            'class'       => Monolog\Handler\StreamHandler::class,
            'constructor' => [
                'stream' => BASE_PATH . '/runtime/logs/' . date("Ymd") . '/hyperf.log',
                'level'  => Monolog\Logger::DEBUG,
            ],
        ],
        'formatter' => [
            'class'       => Monolog\Formatter\LineFormatter::class,
            'constructor' => [
                'format'                => null,
                'dateFormat'            => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
            ],
        ],
    ],
    //运费计算，支付
    'AsyncSupplementWeightCalcProcess' => [
        'handler'   => [
            'class'       => Monolog\Handler\StreamHandler::class,
            'constructor' => [
                'stream' => BASE_PATH . '/runtime/logs/' . date("Ymd") . '/AsyncSupplementWeightCalcProcess.log',
                'level'  => Monolog\Logger::DEBUG,
            ],
        ],
        'formatter' => [
            'class'       => Monolog\Formatter\LineFormatter::class,
            'constructor' => [
                'format'                => null,
                'dateFormat'            => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
            ],
        ],
    ],
    //渠道切换日志
    'ParcelChannelNodeSwitchProcess'   => [
        'handler'   => [
            'class'       => Monolog\Handler\StreamHandler::class,
            'constructor' => [
                'stream' => BASE_PATH . '/runtime/logs/' . date("Ymd") . '/ParcelChannelNodeSwitchProcess.log',
                'level'  => Monolog\Logger::DEBUG,
            ],
        ],
        'formatter' => [
            'class'       => Monolog\Formatter\LineFormatter::class,
            'constructor' => [
                'format'                => null,
                'dateFormat'            => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
            ],
        ],
    ],
    //取号日志
    'AsyncLogisticsSeverProcess'       => [
        'handler'   => [
            'class'       => Monolog\Handler\StreamHandler::class,
            'constructor' => [
                'stream' => BASE_PATH . '/runtime/logs/' . date("Ymd") . '/AsyncLogisticsSeverProcess.log',
                'level'  => Monolog\Logger::DEBUG,
            ],
        ],
        'formatter' => [
            'class'       => Monolog\Formatter\LineFormatter::class,
            'constructor' => [
                'format'                => null,
                'dateFormat'            => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
            ],
        ],
    ],
    //账单结算日志
    'AsyncBillSettlementProcess'       => [
        'handler'   => [
            'class'       => Monolog\Handler\StreamHandler::class,
            'constructor' => [
                'stream' => BASE_PATH . '/runtime/logs/' . date("Ymd") . '/AsyncBillSettlementProcess.log',
                'level'  => Monolog\Logger::DEBUG,
            ],
        ],
        'formatter' => [
            'class'       => Monolog\Formatter\LineFormatter::class,
            'constructor' => [
                'format'                => null,
                'dateFormat'            => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
            ],
        ],
    ],

    //订单转包裹检查
    'AsyncOrderToParcelCheckProcess'   => [
        'handler'   => [
            'class'       => Monolog\Handler\StreamHandler::class,
            'constructor' => [
                'stream' => BASE_PATH . '/runtime/logs/' . date("Ymd") . '/AsyncOrderToParcelCheckProcess.log',
                'level'  => Monolog\Logger::DEBUG,
            ],
        ],
        'formatter' => [
            'class'       => Monolog\Formatter\LineFormatter::class,
            'constructor' => [
                'format'                => null,
                'dateFormat'            => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
            ],
        ],
    ],

    // 平常记录日志
    'text'                             => [
        'handler'   => [
            'class'       => Monolog\Handler\StreamHandler::class,
            'constructor' => [
                'stream' => BASE_PATH . '/runtime/logs/' . date("Ymd") . '/text.log',
                'level'  => Monolog\Logger::DEBUG,
            ],
        ],
        'formatter' => [
            'class'       => Monolog\Formatter\LineFormatter::class,
            'constructor' => [
                'format'                => null,
                'dateFormat'            => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
            ],
        ],
    ],
    // 错误日志
    'error'                            => [
        'handler'   => [
            'class'       => Monolog\Handler\StreamHandler::class,
            'constructor' => [
                'stream' => BASE_PATH . '/runtime/logs/' . date("Ymd") . '/error.log',
                'level'  => Monolog\Logger::DEBUG,
            ],
        ],
        'formatter' => [
            'class'       => Monolog\Formatter\LineFormatter::class,
            'constructor' => [
                'format'                => null,
                'dateFormat'            => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
            ],
        ],
    ],
    // 微信回调支付日志
    'WxPayLogProcess'                  => [
        'handler'   => [
            'class'       => Monolog\Handler\StreamHandler::class,
            'constructor' => [
                'stream' => BASE_PATH . '/runtime/logs/' . date("Ymd") . '/WxPayLogProcess.log',
                'level'  => Monolog\Logger::DEBUG,
            ],
        ],
        'formatter' => [
            'class'       => Monolog\Formatter\LineFormatter::class,
            'constructor' => [
                'format'                => null,
                'dateFormat'            => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
            ],
        ],
    ],

];
