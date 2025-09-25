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

namespace App\Controller\Home;

use App\Common\Lib\UserDefinedIdGenerator;
use App\Exception\HomeException;
use App\Model\JoinMemberProductTemplateModel;
use App\Service\Cache\BaseCacheService;
use App\Service\MembersService;
use App\Service\OrderParcelLogService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: 'cache')]
class CacheController extends HomeBaseController
{
    #[Inject]
    protected BaseCacheService $baseCacheService;

    #[RequestMapping(path: 'parcelException', methods: 'get,post')]
    public function ConfigParcelExceptionCache(RequestInterface $request)
    {
        $result['code'] = 200;
        $result['data'] = $this->baseCacheService->ConfigParcelExceptionCache();
        return $this->response->json($result);
    }

    /**
     * @DOC   箱型箱量
     * @Name   ConfigBoxCache
     * @Author wangfei
     * @date   2023-07-21 2023
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'box', methods: 'get,post')]
    public function ConfigBoxCache(RequestInterface $request)
    {
        $result['code'] = 200;
        $result['data'] = $this->baseCacheService->ConfigBoxCache();
        return $this->response->json($result);
    }


    /**
     * @DOC   获取单个编号
     * @param RequestInterface $request
     * @return ResponseInterface
     * @Author wangfei
     * @date   2023-07-21 2023
     */
    #[RequestMapping(path: 'singeNumber', methods: 'get,post')]
    public function singeNumber(RequestInterface $request)
    {
        $generator      = \Hyperf\Support\make(UserDefinedIdGenerator::class);
        $singeNumber    = $generator->generate(0);
        $result['code'] = 200;
        $result['data'] = (string)$singeNumber;
        return $this->response->json($result);
    }

    #[RequestMapping(path: 'batchNumber', methods: 'get,post')]
    public function batchNumber(RequestInterface $request)
    {
        $param             = $request->all();
        $validationFactory = \Hyperf\Support\make(ValidatorFactoryInterface::class);
        $validator         = $validationFactory->make($param,
            [
                'total' => ['required', 'numeric'],
            ],
            [
                'total.required' => 'line_id  must be required',
                'total.numeric'  => 'line_id must be numeric'
            ]
        );
        if ($validator->fails()) {
            throw new HomeException($validator->errors()->first(), 201);
        }
        $generator = \Hyperf\Support\make(UserDefinedIdGenerator::class);
        $data      = [];
        for ($i = 0; $i < $param['total']; $i++) {
            $singeNumber = $generator->generate(0);
            $data[]      = (string)$singeNumber;
        }
        $result['code'] = 200;
        $result['data'] = $data;
        return $this->response->json($result);
    }

    /**
     * @DOC 获取消息通知类型
     */
    #[RequestMapping(path: 'notifyType', methods: 'get,post')]
    public function ConfigNotifyType(RequestInterface $request)
    {
        $result['code'] = 200;
        $result['data'] = $this->baseCacheService->ConfigNotifyTypeCache();
        return $this->response->json($result);
    }
}
