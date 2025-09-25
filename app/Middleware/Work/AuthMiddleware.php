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

namespace App\Middleware\Work;

use App\Common\Lib\Arr;
use App\Common\Lib\JWTAuth;
use App\Exception\HomeException;
use App\Service\Cache\BaseCacheService;
use Hyperf\Context\Context;
use Hyperf\Di\Exception\Exception;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\HttpServer\Router\Dispatched;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Hyperf\HttpServer\Router\Router;
use function PHPUnit\Framework\isFalse;

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

        $response = $response->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            // Headers 可以根据实际情况进行改写。
            ->withHeader('Access-Control-Allow-Headers', 'DNT,Keep-Alive,User-Agent,Cache-Control,Content-Type,Authorization');

        Context::set(ResponseInterface::class, $response);
        if ($request->getMethod() == 'OPTIONS') {
            return $response;
        }
        $server = $request->getServerParams();
        $path   = strtolower($server['path_info']);
        $method = strtoupper($server['request_method']);
        //获取当前路由
        $route = $this->handleUrl(url: $path);
        //不需要检测Token
        if ($this->notCheckToken(Controller: $route)) {
            return $handler->handle($request);
        }
        $authorization = $request->getHeaderLine('authorization');
        if (empty($authorization)) {
            throw new HomeException("authorization 不存在 ");
        }
        $JWTAuth = new JWTAuth();
        try {
            $JWT = $JWTAuth->auth($authorization, 'home');
        } catch (\Exception $e) {
            return $this->response->json(['code' => 401, 'msg' => '登录过期']);
        }

        //非加盟商禁止访问
        switch ($JWT['role_id']) {
            case 1:
            case 2:
            case 3:
            case 10:
                break;
            default:
                throw new HomeException('非平台代理，加盟商禁止访问');
                break;
        }
        $this->request->UserInfo = $JWT;
        if (!$this->removeController($route)) {
            $role = $this->baseCache->MemberWorkRoleCache(intval($JWT['role_id']), intval($JWT['child_role_id']));
            $path = trim($path, '/');
            $key  = md5(strtolower('/work/' . $path));
            if (!isset($role[$key])) {
               // throw new HomeException('权限不足', 201);
            }
        }
        try {
            return $handler->handle($request);
        } catch (\Throwable $e) {
            throw new HomeException($e->getMessage(), 201);
        }
    }

    protected function removeController($url)
    {
        $route = trim($url, '/');
        $route = explode('/', $route);
        //需要移除的验证控制器前段路由
        $removeRoute = [
            'wangfei', 'cache', 'chat'
        ];
        $url         = strtolower(current($route));
        if (in_array($url, $removeRoute)) {
            return true;
        }
        return false;
    }

    /**
     * @DOC
     * @Name   handleController
     * @Author wangfei
     * @date   2023-09-18 2023
     * @param array $callback
     * @return array|false|string|string[]
     */
    protected function handleController(array $callback)
    {
        $ContrllerUrl  = explode('\\', $callback[0]);
        $endController = end($ContrllerUrl);
        return str_replace('Controller', '', $endController);
    }

    /**
     * @DOC
     * @Name   notCheckToken
     * @Author wangfei
     * @date   2023-09-18 2023
     * @param string $Controller
     * @return bool
     */
    protected function notCheckToken(string $Controller)
    {
        $removeRoute =
            [
                'login/login',
                'login/verify',
            ];
        if (in_array($Controller, $removeRoute)) {
            return true;
        }
        return false;
    }

    protected function handleUrl(string $url)
    {
        $url           = trim($url, '\/');
        $ControllerUrl = explode('/', $url);
        if (count($ControllerUrl) >= 3) {
            $url = array_shift($ControllerUrl) . '/' . array_shift($ControllerUrl);
        }
        return strtolower($url);
    }

}
