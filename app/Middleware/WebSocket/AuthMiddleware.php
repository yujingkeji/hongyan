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

namespace App\Middleware\WebSocket;

use App\Common\Lib\JWTAuth;
use App\Exception\HomeException;
use App\Service\Cache\BaseCacheService;
use Hyperf\Context\Context;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthMiddleware implements MiddlewareInterface
{
    protected ContainerInterface $container;
    protected RequestInterface $request;
    protected HttpResponse $response;
    protected BaseCacheService $baseCache;

    public function __construct(ContainerInterface $container, HttpResponse $response, RequestInterface $request, BaseCacheService $baseCache)
    {
        $this->container = $container;
        $this->response  = $response;
        $this->request   = $request;
        $this->baseCache = $baseCache;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = Context::get(ResponseInterface::class);
        $response = $response->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Headers', 'DNT,Keep-Alive,User-Agent,Cache-Control,Content-Type,Authorization');
        Context::set(ResponseInterface::class, $response);
        if ($request->getMethod() == 'OPTIONS') {
            return $response;
        }
        // 获取token
        $authorization     = $request->getHeaderLine('authorization');
        $homeAuthorization = $request->getHeaderLine('sec-websocket-protocol');
        if (empty($authorization) && empty($homeAuthorization)) {
            throw new HomeException("请登录");
        }
        try {
            if (!empty($homeAuthorization)) {
                $authorization = urldecode($homeAuthorization);
            }
            $JWT = \Hyperf\Support\make(JWTAuth::class)->auth($authorization, 'home');
        } catch (\Exception $e) {
            return $this->response->json(['code' => 401, 'msg' => '登录过期']);
        }
        if (empty($JWT)) {
            throw new HomeException("登录验证错误");
        }
        $this->request->UserInfo = $JWT;
        return $handler->handle($request);
    }
}

