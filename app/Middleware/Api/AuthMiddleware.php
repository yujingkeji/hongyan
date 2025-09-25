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

namespace App\Middleware\Api;


use App\Exception\ApiException;
use App\Exception\ErrorException;
use App\Exception\HomeException;
use App\Service\ApiService;
use Hyperf\Context\Context;
use Hyperf\HttpMessage\Stream\SwooleStream;
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


    public function __construct(ContainerInterface $container, HttpResponse $response, RequestInterface $request)
    {
        $this->container = $container;
        $this->response  = $response;
        $this->request   = $request;

    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = Context::get(ResponseInterface::class);
        $response = $response->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            // Headers 可以根据实际情况进行改写。
            ->withHeader('Access-Control-Allow-Headers', 'DNT,Keep-Alive,User-Agent,Cache-Control,Content-Type,Authorization,websocket_token');
        Context::set(ResponseInterface::class, $response);

        if ($request->getMethod() == 'OPTIONS') {
            return $response;
        }

        $sysParam = $this->request->query();
        if (empty($sysParam)) {
            Context::set(ResponseInterface::class, $response->withStatus(404)->withBody(new SwooleStream('data not found')));
            return Context::get(ResponseInterface::class);
        }
        $contents                = $request->getBody()->getContents();
        $this->request->appCache = \Hyperf\Support\make(ApiService::class)->check($this->request, $contents);
        try {
            return $handler->handle($request);
        } catch (\Throwable $e) {

            $exception['File']    = $e->getFile();
            $exception['Line']    = $e->getLine();
            $exception['Message'] = $e->getMessage();
            throw new HomeException(json_encode($exception, JSON_UNESCAPED_UNICODE), 201);
        }
    }


}
