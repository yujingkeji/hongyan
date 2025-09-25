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

namespace App\Controller\Home\Orders;

use App\Common\Lib\Arr;
use App\Common\Lib\Str;
use App\Common\Lib\UserDefinedIdGenerator;
use App\Controller\Home\AbstractController;
use App\Exception\HomeException;
use App\Model\BlModel;
use App\Model\ParcelModel;
use App\Model\ParcelSendModel;
use App\Model\PriceTemplateModel;
use App\Request\BlRequest;
use App\Service\BlService;
use App\Request\OrdersRequest;
use App\Service\Cache\BaseCacheService;
use App\Service\Express\ExpressService;
use App\Service\ParcelService;
use App\Service\QueueService;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\Rule;
use PharIo\Version\Exception;
use phpseclib3\Math\BigInteger\Engines\PHP;

#[Controller(prefix: 'orders/bl')]
class BlController extends OrderBaseController
{
    #[Inject]
    protected BaseCacheService $baseCacheService;
    #[Inject]
    protected ValidatorFactoryInterface $validationFactory;


    #[RequestMapping(path: 'lists', methods: 'get,post')]
    public function lists(RequestInterface $request)
    {
        $param     = $this->request->all();
        $member    = $request->UserInfo;
        $blService = \Hyperf\Support\make(BlService::class);
        $result    = $blService->blLists($param, $member);

        return $this->response->json($result);
    }


    /**
     * @DOC   创建
     * @Name   add
     * @Author wangfei
     * @date   2023-07-24 2023
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    #[RequestMapping(path: 'add', methods: 'get,post')]
    public function add(RequestInterface $request)
    {
        $param     = $this->request->all();
        $member    = $request->UserInfo;
        $useWhere  = $this->useWhere();
        $where     = $useWhere['where'];
        $blService = \Hyperf\Support\make(BlService::class);
        $result    = $blService->addBl($param, $member, $where);
        return $this->response->json($result);
    }

    //编辑
    #[RequestMapping(path: 'edit', methods: 'get,post')]
    public function edit(RequestInterface $request)
    {
        $param     = $this->request->all();
        $member    = $request->UserInfo;
        $blService = \Hyperf\Support\make(BlService::class);
        $result    = $blService->editBl($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 提单废弃
     */
    #[RequestMapping(path: 'del', methods: 'get,post')]
    public function delBl(RequestInterface $request)
    {
        $param     = $this->request->all();
        $member    = $request->UserInfo;
        $blService = \Hyperf\Support\make(BlService::class);
        $result    = $blService->delBl($param, $member);
        return $this->response->json($result);
    }


}
