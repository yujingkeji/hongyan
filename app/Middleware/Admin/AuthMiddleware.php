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

namespace App\Middleware\Admin;

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
    protected RequestInterface   $request;
    protected HttpResponse       $response;
    protected BaseCacheService   $baseCache;

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
        $server   = $request->getServerParams();
        $path     = strtolower($server['path_info']);
        $method   = strtoupper($server['request_method']);

        $response = $response->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            // Headers 可以根据实际情况进行改写。
            ->withHeader('Access-Control-Allow-Headers', 'DNT,Keep-Alive,User-Agent,Cache-Control,Content-Type,Authorization,websocket_token,authorization');
        Context::set(ResponseInterface::class, $response);

        if ($request->getMethod() == 'OPTIONS') {
            return $response;
        }
        $url = $this->request->getRequestUri();
        //获取当前路由
        $route = $this->handleUrl(url: $url);
        if ($this->notCheckToken($route)) {
            return $handler->handle($request);
        }
        $authorization = $request->getHeaderLine('authorization');
        if (empty($authorization)) {
            throw new HomeException('未登录用户', 404);
        }
        try {
            $JWTAuth = new JWTAuth();
            $JWT     = $JWTAuth->auth($authorization, 'admin');
        } catch (HomeException $e) {
            return $this->response->json(
                [
                    'code' => 201,
                    'url'  => '/#/user/login',
                    'msg'  => '未登录用户',
                ]
            );
        }
        $route = $this->request->getRequestUri();

        if ($JWT['uid'] != 1) {
            if (!$this->remove($route)) {
                $role = $this->baseCache->AdminLoginRoleCache(intval($JWT['role_id']));
                $path = trim($path, '/');
                $key  = md5(strtolower('/admin/' . $path));
                if (!isset($role[$key])) {
                    throw new HomeException('权限不足', 201);
                }
            }
        }
        $this->request->UserInfo = $JWT;
        return $handler->handle($request);
    }

    /**
     * @DOC 不检查token
     */
    protected function notCheckToken($url)
    {
        $remove = [
            'login/login', //登录
            'login/verify', // 验证码
        ];
        $url    = strtolower($url);
        if (in_array($url, $remove)) {
            return true;
        }
        return false;
    }

    /**
     * @DOC 排除需要验证的接口
     */
    protected function remove($url): bool
    {
        $remove =
            [
                'member',//登录后的会员信息
                'base.channel',//服务节点
                '/admin/base/config',//category表一些常用数据
            ];
        $url    = strtolower($url);
        return (in_array($url, $remove));
    }


    /**
     * @DOC 接口
     */
    protected function handleUrl(string $url)
    {
        $url          = trim($url, '\/');
        $ContrllerUrl = explode('/', $url);
        if (count($ContrllerUrl) >= 3) {
            $url = array_shift($ContrllerUrl) . '/' . array_shift($ContrllerUrl);
        }
        return strtolower($url);
    }

}
