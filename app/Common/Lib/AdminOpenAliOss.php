<?php

namespace App\Common\Lib;

class AdminOpenAliOss
{
    public array $config = [
        //设置endpoint 默认公网，同区ECS可使用内网地址流量免费
        'endpoint'        => '',
        'host'            => '',
        'bucket'          => '',
        //推荐使用子用户access key
        'accessKeyId'     => '',
        'accessKeySecret' => '',
        'expire'          => 300 // s
    ];
    public string $dir = 'yfd';          // 用户上传文件时指定的前缀。
    protected array $callback =
        [
            'callbackUrl'      => '',
            'callbackBody'     => 'filename=${object}&size=${size}&mimeType=${mimeType}&height=${imageInfo.height}&width=${imageInfo.width}&imageInfo.format=${imageInfo.format}&uid=${x:uid}&join_uid=${x:join_uid}&agent_uid=${x:agent_uid}&file_id=${x:file_id}&dir=${x:dir}&path=${x:path}',
            'callbackBodyType' => "application/x-www-form-urlencoded"
        ];

    protected $base64_policy = '';
    protected $signature = '';
    protected $expire = '';
    protected $expiration = '';
    protected $base64_callback_body = '';

    public function __construct(array $config = [])
    {
        $this->config = !empty($config) ? array_merge($this->config, $config) : $this->config;

        $this->expiration           = $this->expiration($this->config['Expire']);
        $this->base64_policy        = $this->policy();
        $this->signature            = base64_encode(hash_hmac('sha1', $this->base64_policy, $this->config['AccessKeySecret'], true));
        $this->base64_callback_body = base64_encode(json_encode($this->callback));
    }

    public function init()
    {
        $response               = [];
        $response['accessid']   = $this->config['accessKeyId'];
        $response['accessHost'] = $this->config['host'];
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

}
