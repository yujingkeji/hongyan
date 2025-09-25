<?php

namespace App\Controller\Home\Member;

use App\Common\Lib\UploadAliOssSev;
use App\Controller\Home\HomeBaseController;
use App\Exception\HomeException;
use App\Model\MemberThirdConfigureItemModel;
use App\Model\MemberThirdConfigureModel;
use App\Model\ThirdConfigureFieldModel;
use App\Model\UploadFileModel;
use App\Request\LibValidation;
use App\Request\UploadFileRequest;
use App\Service\ConfigService;
use App\Service\Ocr\Client;
use App\Service\Ocr\interfaces\ToolOcrIdCard;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;


#[Controller(prefix: "member/upload")]
class UploadController extends HomeBaseController
{
    protected array $array = [
        'logo'   => '/logo',
        'auth'   => '/auth',
        'goods'  => '/goods',
        'member' => '/member',
        'agent'  => '/agent',
    ];

    /**
     * @DOC 图片上传签名 （加密）
     */
    #[RequestMapping(path: "uploadSign", methods: "post")]
    public function uploadSign(RequestInterface $request): ResponseInterface
    {
        $param             = $request->all();
        $member_uid        = $request->UserInfo['uid'];
        $parent_join_uid   = $request->UserInfo['parent_join_uid'];
        $parent_agent_uid  = $request->UserInfo['parent_agent_uid'];
        $uploadFileRequest = $this->container->get(UploadFileRequest::class);
        $uploadFileRequest->scene('uploadFile')->validated();

        # 接口获取存储信息
        list($ret, $config) = $this->configuration();
        if (!$ret) {
            throw new HomeException($config);
        }

        $UploadAliOssSev = new UploadAliOssSev($config);

        # 获取默认 文件夹存储路径
        $dir      = ThirdConfigureFieldModel::where('third_id', '=', 71)
            ->where('field', '=', 'Dir')->value('default_value');
        $inputDir = $request->input('dir', 'auth');
        $inputDir = $this->array[$inputDir] ?? '/auth';

        $UploadAliOssSev->dir = $dir . $inputDir;
        $UploadFileModel      = new UploadFileModel();
        $where['member_uid']  = $member_uid;
        $where['image_md5']   = $param['image_md5'];
        $data                 = $UploadFileModel->single($where, ['*']);
        $time                 = time();
        if (!empty($data)) {

            $data['accessHost'] = $UploadAliOssSev->config['Host'];
            $data['filename']   = $data['pic_name'];
            $img_url            = $UploadAliOssSev->config['Host'] . '/' . $data['file_url'];
            $data['img_url']    = $UploadAliOssSev->signUrl($img_url);
            return $this->response->json(['code' => 200, 'msg' => '该图片已经上传', 'data' => $data]);
        } else {
            $insert['image_md5']   = $param['image_md5'];
            $insert['pic_name']    = $param['pic_name'];
            $insert['member_uid']  = $member_uid;
            $insert['create_time'] = $time;
            $insert['update_time'] = $time;
            $upload_file_id        = UploadFileModel::insertGetId($insert);
        }

        $data                   = $UploadAliOssSev->init();
        $data['path']           = $data['dir'];
        $data['dir']            = $data['dir'] . '/' . $parent_agent_uid . '/' . $member_uid;
        $data['member_uid']     = $member_uid;
        $data['join_uid']       = $parent_join_uid;
        $data['agent_uid']      = $parent_agent_uid;
        $data['upload_file_id'] = $upload_file_id;
        return $this->response->json(['code' => 200, 'msg' => '签名成功', 'data' => $data]);
    }

    /**
     * @DOC 获取OSS存储设置
     */
    public function configuration($parent_agent_uid = 0): array
    {
        $parent_agent_uid = $this->request->UserInfo['parent_agent_uid'] ?? $parent_agent_uid;
        $cfgWhere         = [
            ['third_id', '=', 71],
            ['status', '=', 1],
            ['member_uid', '=', $parent_agent_uid],
        ];
        $cfg              = MemberThirdConfigureModel::where($cfgWhere)->first();
        if (!$cfg) {
            return [false, '代理未开启图片存储'];
        }

        $where = [
            ['member_third_id', '=', $cfg['member_third_id']],
            ['member_uid', '=', $parent_agent_uid],
        ];

        $oss = MemberThirdConfigureItemModel::where($where)->get()->toArray();
        if (empty($oss)) {
            return [false, '代理未配置图片上传'];
        }
        $ossData = [];
        foreach ($oss as $v) {
            $ossData[$v['field']] = $v['field_value'];
        }
        if (empty($ossData)) {
            return [false, '未查询到配置信息'];
        }
        return [true, $ossData];
    }

    /**
     * @DOC 图片上传签名（开放）
     */
    #[RequestMapping(path: "open", methods: "post")]
    public function uploadOpen(RequestInterface $request)
    {
        $param    = $request->all();
        $userInfo = $this->request->UserInfo;
        $result   = (new ConfigService())->upload($param, $userInfo);
        return $this->response->json($result);
    }


    /**
     * @DOC 图片识别身份证信息
     */
    #[RequestMapping(path: "ocr", methods: "post")]
    public function Ocr(RequestInterface $request)
    {
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($request->all(), [
            'image_src'  => ['required'],
            'image_type' => ['required'],
        ]);
        $configService = \Hyperf\Support\make(ConfigService::class);
        $result        = $configService->ocr($param);
        return $this->response->json($result);
    }

}
