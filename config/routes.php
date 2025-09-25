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

use Hyperf\HttpServer\Router\Router;

Router::addRoute(['GET', 'POST', 'HEAD'], '/', 'App\Controller\IndexController@index');


Router::get('/favicon.ico', function () {
    return '';
});

# WebSocket 连接
Router::addServer('webSocket', function () {
    // 支付提示
    Router::get('/webSocket/payPrompt', 'App\Controller\WebSocket\PayPromptController');
    // 邀请收件人填写地址
    Router::get('/webSocket/invite/address', 'App\Controller\WebSocket\InviteAddressController');
});

Router::addServer('api', function () {
    Router::addRoute(['GET', 'POST', 'HEAD'], '/router/rest', 'App\\Controller\\Api\\RouterController@rest');
});
