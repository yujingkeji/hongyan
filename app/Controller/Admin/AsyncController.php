<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Common\Lib\AdminOpenAliOss;
use App\Model\UploadFileModel;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: '/', server: 'httpAdmin')]
class AsyncController extends AdminBaseController
{
    /**
     * @DOC 后台图片上传回调接口
     */
    #[RequestMapping(path: 'async/upload/callback', methods: 'post')]
    public function read(): ResponseInterface
    {
        // 获取请求头
        $authorizationBase64 = $this->request->getHeaderLine('authorization');
        $pubKeyUrlBase64     = $this->request->getHeaderLine('x-oss-pub-key-url');

        if ($authorizationBase64 == '' || $pubKeyUrlBase64 == '') {
            return $this->response->withStatus(403); // 返回 403 禁止访问响应
        }

        // 获取 OSS 的签名和公钥
        $authorization = base64_decode($authorizationBase64);
        $pubKeyUrl     = base64_decode($pubKeyUrlBase64);

        // 获取回调请求体
        $body = $this->request->getBody()->getContents();

        // 拼接待签名字符串
        $path = $this->request->getUri()->getPath();

        $authStr = '';
        $pos     = strpos($path, '?');
        if ($pos === false) {
            $authStr = urldecode($path) . "\n" . $body;
        } else {
            $authStr = urldecode(substr($path, 0, $pos)) . substr($path, $pos, strlen($path) - $pos) . "\n" . $body;
        }
        // 获取公钥
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $pubKeyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $pubKey = curl_exec($ch);

        if ($pubKey == "") {
            return $this->response->withStatus(403); // 返回 403 禁止访问响应
        }

        // 验证签名
        $ok = openssl_verify($authStr, $authorization, $pubKey, OPENSSL_ALGO_MD5);
        if ($ok != 1) {
            return $this->response->withStatus(403); // 返回 403 禁止访问响应
        }

        $param = $this->request->all();
        // 保存到数据库
        $where = [];
        try {
            $UploadFileModel        = new UploadFileModel();
            $where['file_id']       = $param['file_id'] ?? 0;
            $imageArr['image_size'] = $param['size'] ?? 0;
            $imageArr['image_w']    = $param['width'] ?? 0;
            $imageArr['image_h']    = $param['height'] ?? 0;
            $imageArr['file_url']   = $param['filename'] ?? '';
            $imageArr['suffix']     = $param['imageInfo_format'] ?? '';
            $UploadFileModel->where($where)->update($imageArr);
            $path = $param['path'];
            # 获取上传用户的代理信息

            $aliOss              = new AdminOpenAliOss();
            $aliOss->dir         = $path;
            $param['accessHost'] = $aliOss->config['OpenHost'];
            $param['code']       = 200;
            $param['msg']        = '上传成功';
            return $this->response->json($param);
        } catch (\Exception $e) {
            if (!empty($where) && $where['file_id'] != 0) {
                UploadFileModel::where($where)->delete();
            }
            return $this->response->json([
                'code' => 201,
                'msg'  => '上传失败',
                'data' => $e->getMessage(),
            ]);
        }
    }


}
