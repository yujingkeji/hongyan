<?php

declare(strict_types=1);

namespace App\Controller\Work;

use App\Service\OrderNoteService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

#[Controller(prefix: "/", server: 'httpWork')]
class OrderNoteController extends WorkBaseController
{
    #[Inject]
    protected OrderNoteService $noteService;

    /**
     * @DOC 挂单列表
     */
    #[RequestMapping(path: 'order/note/lists', methods: 'post')]
    public function lists(RequestInterface $request)
    {
        $param  = $request->all();
        $result = $this->noteService->lists($param, $this->request->UserInfo);
        return $this->response->json($result);
    }

    /**
     * @DOC 挂单新增
     */
    #[RequestMapping(path: 'order/note/add', methods: 'post')]
    public function add(RequestInterface $request)
    {
        $param = $request->all();
        // 限制代理挂单
        if (in_array($this->request->UserInfo['role_id'], [1, 2])) {
            return $this->response->json(['code' => 201, 'msg' => '仅加盟商可挂单']);
        }
        $result = $this->noteService->add($param, $this->request->UserInfo);
        return $this->response->json($result);
    }

    /**
     * @DOC 挂单删除
     */
    #[RequestMapping(path: 'order/note/del', methods: 'post')]
    public function del(RequestInterface $request)
    {
        $param  = $request->all();
        $result = $this->noteService->del($param, $this->request->UserInfo);
        return $this->response->json($result);
    }

    /**
     * @DOC 挂单详情
     */
    #[RequestMapping(path: 'order/note/info', methods: 'post')]
    public function info(RequestInterface $request)
    {
        $param  = $request->all();
        $result = $this->noteService->info($param, $this->request->UserInfo);
        return $this->response->json($result);
    }

    /**
     * @DOC 产品获取计算
     */
    #[RequestMapping(path: 'order/note/call', methods: 'post')]
    public function call(RequestInterface $request)
    {
        $param  = $request->all();
        $result = $this->noteService->call($param, $this->request->UserInfo);
        return $this->response->json($result);
    }


}
