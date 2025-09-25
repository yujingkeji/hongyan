<?php

namespace App\Controller\Home\Base;

use App\Controller\Home\HomeBaseController;
use App\Model\CsvExportTemplateModel;
use App\Request\LibValidation;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: "base/export")]
class ExportController extends HomeBaseController
{

    /**
     * @DOC 导出模板列表
     */
    #[RequestMapping(path: 'template', methods: 'get,post')]
    public function template(RequestInterface $request): ResponseInterface
    {
        $data = CsvExportTemplateModel::query()->select()->get();
        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => $data
        ]);
    }

    /**
     * @DOC 模板详情
     * @Name   templateDetails
     * @Author wangfei
     * @date   2023/11/22 2023
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: 'template/details', methods: 'get,post')]
    public function templateDetails(RequestInterface $request): ResponseInterface
    {
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $params        = $LibValidation->validate($request->all(), [
            'template_id' => ['required', 'integer'],
        ], [
            'template_id.required' => '模板ID必须填写',
        ]);
        $data          = CsvExportTemplateModel::query()->with(['item'])->where('template_id', '=', $params['template_id'])->first();
        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => $data
        ]);
    }


}
