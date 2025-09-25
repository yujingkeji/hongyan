<?php

namespace App\Common\Lib;

use App\Controller\Home\Member\UploadController;
use OSS\Core\OssException;
use OSS\OssClient;

class UploadAliOssSev
{
    public array $config = [];
    public string $dir = 'yfd';          // 用户上传文件时指定的前缀。
    protected array $callback =
        [
            'callbackUrl'      => 'http://order.rpc.********.cn/member/async/memberAuthCallback',
            'callbackBody'     => 'filename=${object}&size=${size}&mimeType=${mimeType}&height=${imageInfo.height}&width=${imageInfo.width}&imageInfo.format=${imageInfo.format}&member_uid=${x:member_uid}&join_uid=${x:join_uid}&agent_uid=${x:agent_uid}&upload_file_id=${x:upload_file_id}&dir=${x:dir}&path=${x:path}',
            'callbackBodyType' => "application/x-www-form-urlencoded"
        ];

    protected $base64_policy = '';
    protected $signature = '';
    protected $expire = '';
    protected $expiration = '';
    protected $base64_callback_body = '';

    public function __construct(array $config = [])
    {
        $this->config                  = !empty($config) ? array_merge($this->config, $config) : $this->config;
        $this->callback['callbackUrl'] = \Hyperf\Support\env('ali_oss_notify_url');
        $this->expiration              = $this->expiration($this->config['Expire']);
        $this->base64_policy           = $this->policy();
        $this->signature               = base64_encode(hash_hmac('sha1', $this->base64_policy, $this->config['AccessKeySecret'], true));
        $this->base64_callback_body    = base64_encode(json_encode($this->callback));

    }

    public function init()
    {
        $response               = [];
        $response['accessid']   = $this->config['AccessKeyId'];
        $response['accessHost'] = $this->config['Host'];
        $response['policy']     = $this->base64_policy;
        $response['signature']  = $this->signature;
        $response['expire']     = $this->expire;
        $response['callback']   = $this->base64_callback_body;
        $response['dir']        = $this->dir;  // 这个参数是设置用户上传文件时指定的前缀。
        return $response;
    }

    public function policy()
    {
        //最大文件大小.用户可以自己设置
        $condition    = array(0 => 'content-length-range', 1 => 0, 2 => 1048576000);
        $conditions[] = $condition;
        // 表示用户上传的数据，必须是以$dir开始，不然上传会失败，这一步不是必须项，只是为了安全起见，防止用户通过policy上传到别人的目录。
        $start        = array(0 => 'starts-with', 1 => '$key', 2 => $this->dir);
        $conditions[] = $start;
        $arr          = array('expiration' => $this->expiration, 'conditions' => $conditions);
        $policy       = json_encode($arr);
        return base64_encode($policy);
    }


    /**
     * @DOC   :获取有效期
     */
    public function expiration($expire = 30)
    {
        $now = time();
        //设置该policy超时时间是10s. 即这个policy过了这个有效时间，将不能访问。
        $end          = $now + $expire;
        $this->expire = $end;
        return $this->gmt_iso8601($end);
    }

    /**
     * @DOC   : 获取到期时间
     */
    public function gmt_iso8601($endTime)
    {
        $dtStr      = date("c", $endTime);
        $mydatetime = new \DateTime($dtStr);
        $expiration = $mydatetime->format($mydatetime::ISO8601);
        $pos        = strpos($expiration, '+');
        $expiration = substr($expiration, 0, $pos);
        return $expiration . "Z";
    }

    /**
     * $url      = $Oss->uploadFile('notify/sfz/$result['src'], runtime_path() . '/sfz/' . $result['src']);
     * @DOC   :
     * @Name  : uploadFile
     * @Author: wangfei
     * @date  : 2021-12-10 2021
     * @param $remoteFilePath //Oss远程地址
     * @param $localFilePath //本地存储地址
     * @return string
     */
    public function uploadFile($remoteFilePath, $localFilePath)
    {
        try {
            $ossClient = new OssClient($this->config['AccessKeyId'], $this->config['AccessKeySecret'], $this->config['Endpoint']);
            $result    = $ossClient->uploadFile($this->config['bucket'], $remoteFilePath, $localFilePath);
            if (isset($result['info']) && !empty($result['info'])) {
                $url = $result['info']['url'];
            } else {
                $url = '';
            }
            return $url;
        } catch (OssException $e) {
            return $e->getMessage();
        }

    }

    /**
     * $v=$Oss->downFile(runtime_path().'110101192311260050_王春年.pdf','notify/sfz/110101192311260050_%E7%8E%8B%E6%98%A5%E5%B9%B4.pdf');
     * @DOC   :
     * @Name  : downFile
     * @Author: wangfei
     * @date  : 2021-12-10 2021
     * @param $localfile 本地存储地址
     * @param $path 远程文件地址
     * @return false|string
     */
    public function downFile($localfile, $path)
    {
        //解析传过来的地址，解析出objectName
        $options = array(
            OssClient::OSS_FILE_DOWNLOAD => $localfile
        );
        try {
            $ossClient = new OssClient($this->config['AccessKeyId'], $this->config['AccessKeySecret'], $this->config['Endpoint']);
            $result    = $ossClient->getObject($this->config['Bucket'], $path, $options);
            return $result;
        } catch (OssException $e) {
            print_r($e->getMessage());
            return false;
        }
    }

    /**
     * @DOC 加入签名
     */
    public function signUrl($url)
    {
        //判断是否是oss地址
        if (strpos($url, 'oss-') === false) return $url;
        //处理地址，获取基础部分
        $url         = parse_url($url);
        $url['path'] = trim($url['path'], '/');
        $ossClient   = new OssClient($this->config['AccessKeyId'], $this->config['AccessKeySecret'], $this->config['Endpoint']);
        return $ossClient->signUrl($this->config['Bucket'], $url['path'], '10800');
    }


}
