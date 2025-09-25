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

namespace App\Controller\Work;


use App\Service\Express\ExpressService;

use App\Service\QueueService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

#[Controller(prefix: "/", server: 'httpWork')]
class IndexController extends AbstractController
{

    #[RequestMapping(path: 'index/test', methods: 'get,post')]
    public function index(RequestInterface $request)
    {
        $user   = $this->request->input('user', 'Hyperf');
        $method = $this->request->getMethod();
        $data   = [
            'method'  => $method,
            'message' => "Hello rpc {$user}.",
          //  'member'  => $request->UserInfo,
          //  'uid'     => $this->member_uid,
        ];
        return $this->response->json($data);
    }

}
