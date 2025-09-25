<?php

namespace App\Controller\Home\Base;

use App\Controller\Home\HomeBaseController;
use App\Service\Cache\BaseCacheService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: "base/channel")]
class ChannelController extends HomeBaseController
{
    /**
     * @DOC 渠道节点
     */
    #[RequestMapping(path: 'node', methods: 'post')]
    public function node(): ResponseInterface
    {
        $data = (new BaseCacheService())->ChannelNodeCache();
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
    }

}
