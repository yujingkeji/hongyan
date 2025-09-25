<?php

declare(strict_types=1);

namespace App\Controller\Admin\Auth;

use App\Common\Lib\AdminOpenAliOss;
use App\Controller\Admin\AdminBaseController;
use App\Model\UploadFileModel;
use App\Request\LibValidation;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: '/', server: 'httpAdmin')]
class UploadController extends AdminBaseController
{

    /**
     * @DOC 认证方式列表查询
     */
    #[RequestMapping(path: 'auth/upload/oss', methods: 'post')]
    public function lists(RequestInterface $request): ResponseInterface
    {
        $params = $request->all();

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'image_md5' => ['required'],
                'pic_name'  => ['required'],
            ], [
                'image_md5.required' => '文件加密错误',
                'pic_name.required'  => '文件名称错误',
            ]);

        $OpenAliOss = new AdminOpenAliOss();

        $OpenAliOss->dir = 'yfd/admin';
        $UploadFileModel = new UploadFileModel();

        $where['admin_uid'] = $this->request->UserInfo['uid'];
        $where['image_md5'] = $params['image_md5'];

        $data = $UploadFileModel->single($where, ['*']);
        $time = time();

        if (!empty($data)) {
            $data['accessHost'] = $OpenAliOss->config['host'];
            $data['filename']   = $data['file_url'];
            $file_id            = $data['file_id'];
            if (!empty($data['file_url'])) {
                return $this->response->json(['code' => 200, 'msg' => '该图片已经上传', 'data' => $data]);
            }
        } else {
            $data['image_md5']   = $param['image_md5'];
            $data['pic_name']    = $param['pic_name'];
            $data['admin_uid']   = $this->request->UserInfo['uid'];
            $data['create_time'] = $time;
            $data['update_time'] = $time;
            $file_id             = UploadFileModel::insertGetId($data);
        }
        $data = $OpenAliOss->init();

        $data['path']    = $data['dir'];
        $data['dir']     = $data['dir'] . '/' . $this->request->UserInfo['uid'];
        $data['uid']     = $this->request->UserInfo['uid'];
        $data['file_id'] = $file_id;

        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => $data
        ]);

    }

}
