<?php

namespace App\Common\Lib;

class Message
{
    public string $url = 'http://120.55.197.77:1210';
    public string $userCode;
    public string $userPass;

//    public string $userCode = 'QDRHCF';
//    public string $userPass = 'Yunxin123';

    public int $channel = 0; //默认为0.国际短信为999

    private array $_message = [
        '-1'  => ['code' => 100001, 'msg' => '应用程序异常'],
        '-3'  => ['code' => 100003, 'msg' => '用户名密码错误或者用户无效'],
        '-4'  => ['code' => 100004, 'msg' => '短信内容和备案的模板不一样'],
        '-5'  => ['code' => 100005, 'msg' => '签名不正确'],
        '-7'  => ['code' => 100007, 'msg' => '余额不足'],
        '-8'  => ['code' => 100008, 'msg' => '通道错误'],
        '-9'  => ['code' => 100009, 'msg' => '无效号码'],
        '-10' => ['code' => 100010, 'msg' => '签名内容不符合长度'],
        '-11' => ['code' => 100011, 'msg' => '用户有效期过期'],
        '-12' => ['code' => 100012, 'msg' => '黑名单'],
        '-13' => ['code' => 100013, 'msg' => '语音验证码的Amount参数必须是整形字符串'],
        '-14' => ['code' => 100014, 'msg' => '语音验证码的内容只能为数字'],
        '-15' => ['code' => 100015, 'msg' => '语音验证码的内容最长为6位'],
        '-16' => ['code' => 100016, 'msg' => '余额请求过于频繁，5秒才能取余额一次'],
        '-17' => ['code' => 100017, 'msg' => '非法IP'],
        '-18' => ['code' => 100018, 'msg' => 'Msg格式错误'],
        '-19' => ['code' => 100019, 'msg' => '短信数量错误，小于1或者大于50'],
        '-20' => ['code' => 100020, 'msg' => '号码错误或者黑名单'],
        '-21' => ['code' => 100021, 'msg' => '没有找到对应的SubmitID设置'],
        '-23' => ['code' => 100023, 'msg' => '解密失败'],
    ];

    private string $_error = '';

    public function __construct($channel, $userCode, $userPass)
    {
        $this->channel  = $channel;
        $this->userCode = $userCode;
        $this->userPass = $userPass;
    }

    public function sendMessage($mobile, $message, $method = "/Services/MsgSend.asmx/SendMsg")
    {
        $param                      = ["userCode" => $this->userCode, "userPass" => $this->userPass, "DesNo" => $mobile, 'Msg' => $message, 'Channel' => $this->channel];
        $url                        = $this->url . $method;
        $ch                         = curl_init();
        $config                     = array(CURLOPT_RETURNTRANSFER => true, CURLOPT_URL => $url);
        $config[CURLOPT_POST]       = true;
        $config[CURLOPT_POSTFIELDS] = http_build_query($param);
        curl_setopt_array($ch, $config);
        $result = curl_exec($ch);
        curl_close($ch);
        $jsonData = simplexml_load_string($result);
        $xmljson  = json_encode($jsonData, JSON_UNESCAPED_UNICODE);
        $dataArr  = json_decode($xmljson, true);
        if (isset($this->_message[$dataArr[0]])) {
            return $this->_message[$dataArr[0]]['msg'] ?? '原因不明：' . json_encode($dataArr, JSON_UNESCAPED_UNICODE);
        } else {
            return true;
        }
    }
}
