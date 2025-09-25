<?php
/**淘宝SDK配置文件
 * *****************************************************************
 * 这不是一个自由软件,谢绝修改再发布.
 * @Created by PhpStorm
 * @Name    :   api.php
 * @Email   :   28386631@qq.com
 * @Author  :  wangfei
 * @Date    :   2017-05-15 9:06
 * @Link    :   http://ServPHP.LinkUrl.cn
 * *****************************************************************
 */
return [

    'Alipay'           =>
        [
            //应用ID,您的APPID。
            'app_id'               => "", //商户私钥
            'merchant_private_key' => "",
            //异步通知地址
            //'notify_url'           => "",//测试
            'notify_url'           => "",//正式
            //同步跳转
            'return_url'           => "",//正式
            //编码格式
            'charset'              => "UTF-8",
            //签名方式
            'sign_type'            => "RSA2",
            //支付宝网关
            'gatewayUrl'           => "",
            //支付宝公钥,查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥。
            'alipay_public_key'    => "",
            // 支付充值的回到接口
            'recharge_url'         => ''
        ],
    'notify_url'       => env('notify_url'), // 余额充值微信回调地址
    'order_notify_url' => env('order_notify_url'), // 订单支付微信回调地址
];
