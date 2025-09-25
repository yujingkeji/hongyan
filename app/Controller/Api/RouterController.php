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

namespace App\Controller\Api;

use App\Common\Lib\Str;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Contract\RequestInterface;

#[Controller(prefix: 'router', server: 'api')]
class RouterController extends ApiBaseController
{
    public function rest(RequestInterface $request)
    {
        $sysParams    = $request->getQueryParams();
        $contents     = $request->getParsedBody();
        $method       = $sysParams['method'];
        $methodArr    = explode('.', $method);
        $methodString = '';
        foreach ($methodArr as $key => $value) {
            $methodString .= '\\' . Str::ucfirst($value);
        }

        $methodString  .= 'Service';
        $methodService = \Hyperf\Support\make('App\\Service\\Api\\' . $this->checkVersion($sysParams['v']) . $methodString, [$this->request->appCache, $sysParams, $contents]);
        $data          = $methodService->handle();
        return $this->response->json($data);
    }

    /**
     * @DOC 检查接口版本
     * @Name   checkVersion
     * @Author wangfei
     * @date   2024/1/24 2024
     * @param string $version
     * @return string
     */
    protected function checkVersion(string $version)
    {
        switch ($version) {
            default:
            case '1.0':
                return 'V1';
                break;
            case '2.0':
                return 'V2';
                break;
        }
    }


}
