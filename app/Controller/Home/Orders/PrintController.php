<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Controller\Home\Orders;

use App\Common\Lib\Str;
use App\Controller\Home\AbstractController;
use App\Exception\HomeException;
use App\Request\LibValidation;
use App\Service\Express\ExpressService;
use App\Service\PrintService;
use App\Service\QueueService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use PharIo\Version\Exception;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

#[Controller(prefix: 'orders/print')]
class PrintController extends OrderBaseController
{
    #[Inject]
    protected PrintService $PrintService;

    /**
     * @DOC   : 请求面单打印
     * @Name  : getPrintInfo
     * @Author: wangfei
     * @date  : 2025-04 09:35
     * @param RequestInterface $request
     * @return PsrResponseInterface
     *
     */
    #[PostMapping(path: 'getPrintInfo')]
    public function getPrintInfo(RequestInterface $request): PsrResponseInterface
    {
        $params = make(LibValidation::class)->validate($request->all(),
            [
                'order_sys_sn' => ['required', 'array']
            ]);
        //去重
        $params['order_sys_sn'] = array_unique($params['order_sys_sn']);
        $result                 = $this->PrintService->handlePrintInfo($params);
        return $this->response->json($result);
    }

    #[PostMapping(path: "getTemplate")]
    public function getTemplate(RequestInterface $request): PsrResponseInterface
    {
        $content        = $this->request->getBody()->getContents();
        $bodyArray      = json_decode($content, true);
        $userInfo       = $request->UserInfo;
        $printTemplate  = $this->PrintService->getUserTemplate($bodyArray, $userInfo);
        $result['code'] = 200;
        $result['data'] = $printTemplate;
        return $this->response->json($result);
    }


    #[PostMapping(path: 'getTemplateDetail')]
    public function getTemplateDetail(RequestInterface $request): PsrResponseInterface
    {
        $content                    = $this->request->getBody()->getContents();
        $bodyArray                  = json_decode($content, true);
        $printTemplate              = $this->PrintService->getTemplateDetail($bodyArray);
        $printTemplate['tempItems'] = json_decode($printTemplate['tempItems'], true);
        $printTemplate['default']   = true;
        $result['code']             = 200;
        $result['data']             = $printTemplate;
        return $this->response->json($result);


    }

    #[PostMapping(path: 'success')]
    public function changePrintSuccess(RequestInterface $request): PsrResponseInterface
    {
        $userInfo = $request->UserInfo;
        $result   = $this->PrintService->printSuccess(params: $request->all(), userInfo: $userInfo);
        return $this->response->json($result);
    }


}
