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

namespace App\Middleware\Home;

use App\Common\Lib\JWTAuth;
use App\Common\Lib\Str;
use App\Exception\HomeException;
use App\Service\Cache\BaseCacheService;
use App\Service\LoginService;
use FastRoute\Dispatcher;
use Hyperf\Context\Context;
use Hyperf\Di\Exception\Exception;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\HttpServer\Router\Dispatched;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Hyperf\HttpServer\Router\Router;
use phpseclib3\File\ASN1\Maps\AccessDescription;
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
            // Headers 可以根据实际情况进行改写。
            ->withHeader('Access-Control-Allow-Headers', 'DNT,Keep-Alive,User-Agent,Cache-Control,Content-Type,Authorization');

        Context::set(ResponseInterface::class, $response);
        if ($request->getMethod() == 'OPTIONS') {
            return $response;
        }
        $server = $request->getServerParams();
        $route  = strtolower($server['path_info']);
        //不需要检测Token
        $Controller = $this->handleUrl(url: $route);
        #print_r($Controller);
        #echo PHP_EOL;
        if ($this->notCheckToken($Controller)) {
            return $handler->handle($request);
        }
        $authorization = $request->getHeaderLine('authorization');
        $JWTAuth       = new JWTAuth();
        try {
            $JWT = $JWTAuth->auth($authorization, 'home');
        } catch (\Exception $e) {
            return $this->response->json(['code' => 401, 'msg' => '登录过期']);
        }
        if (empty($JWT)) {
            throw new HomeException("登录验证错误");
        }
        $this->request->UserInfo = $JWT;
        return $handler->handle($request);
        if (!$this->removeController($route)) {
            try {
                $role = $this->baseCache->MemberRoleCache(intval($JWT['role_id']), true);
                $key  = md5(strtolower('/home' . $route));
                #print_r('/home' . $route);
                #print_r($role);
                if (!isset($role[$key])) {
                    throw new HomeException('权限不足', 201);
                }
            } catch (HomeException $e) {
                return $this->response->json(
                    [
                        'code' => 201,
                        'url'  => $route,
                        'msg'  => '权限不足',
                    ]
                );
            }
        }
        //try {
        return $handler->handle($request);
        /* } catch (\Throwable $e) {
             return $this->response->json(['code' => 500, 'msg' => $e->getMessage()]);
         }*/
    }

    //不检查token 直接到控制器，不要到控制下的方法
    protected function notCheckToken($url)
    {
        $url    = Str::trim($url, '/');
        $remove = [
            'member/register', //注册
            'member/login/verify', //注册
            'member/login', //登录
            'member/async', // 图片上传，支付回调
            'member/wechat', // 微信触发事件
            'chat/order', // 客服系统包裹订单
            'app/auth', // 小程序登录
        ];
        $url    = strtolower($url);
        if (in_array($url, $remove)) {
            return true;
        }
        return false;
    }

    //需要检查token、不检查权限
    protected function removeController($url)
    {
        $route = trim($url, '/');
        $route = explode('/', $route);
        //需要移除的验证控制器前段路由
        $removeRoute = [
            'wangfei', 'cache', 'recharge', 'base', 'chat', 'orders'
        ];
        $url         = strtolower(current($route));
        if (in_array($url, $removeRoute)) {
            return true;
        }
        return false;
    }

    /**
     * @DOC   :
     * @Name  : handleUrl
     * @Author: wangfei
     * @date  : 2024-12 14:49
     * @param string $url
     * @return string
     *
     *
     */
    protected function handleUrl(string $url): string
    {
        $url          = trim($url, '\/');
        $ContrllerUrl = explode('/', $url);
        if (count($ContrllerUrl) >= 3) {
            $url = array_shift($ContrllerUrl) . '/' . array_shift($ContrllerUrl);
        }
        return strtolower($url);
    }

}
