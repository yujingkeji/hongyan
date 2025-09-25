<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Psr\Http\Message\ResponseInterface;

class AdminBaseController extends \App\Controller\AbstractController
{
    protected bool $syn = true;
    protected int $adminSysUID = 1;//超级系统管理员。

    /**
     * @DOC 基础数据维护，true 为从基础平台同步。
     */
    protected function baseSyn(): ResponseInterface
    {
        $result['code'] = 201;
        $result['msg']  = '基数数据不提供维护功能、请移步到基数数据服务';
        return $this->response->json($result);
    }

}
