<?php

namespace App\Controller\Home\Member;

use Alipay\EasySDK\Kernel\Config;
use Alipay\EasySDK\Kernel\Factory;
use App\Common\Lib\Arr;
use App\Common\Lib\Crypt;
use App\Common\Lib\OpenAliOss;
use App\Common\Lib\SendTemplate;
use App\Common\Lib\UploadAliOssSev;
use App\Controller\Home\AbstractController;
use App\Exception\HomeException;
use App\Model\AgentMemberModel;
use App\Model\AgentPlatformModel;
use App\Model\AgentRateModel;
use App\Model\MemberAmountLogModel;
use App\Model\MemberChildModel;
use App\Model\MemberJoinAppModel;
use App\Model\MemberRechargeModel;
use App\Model\OrderPaymentModel;
use App\Model\UploadFileModel;
use App\Service\ParcelPaymentService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Redis\Redis;
use Hyperf\WebSocketServer\Sender;
use OSS\OssClient;
use Psr\Http\Message\ResponseInterface;
use Yurun\PaySDK\Weixin\SDK;

#[Controller(prefix: "member/async")]
class AsyncController extends AbstractController
{

    #[Inject]
    protected Sender $sender;

    /**
     * @DOC 用户上传实名回调接口
     */
    #[RequestMapping(path: "memberAuthCallback", methods: "post")]
    public function memberAuthCallback(): ResponseInterface
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
        $path    = $this->request->getUri()->getPath();
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
            $UploadFileModel  = new UploadFileModel();
            $where['file_id'] = $param['upload_file_id'] ?? 0;
            if (Arr::hasArr($param, 'upload_file_id')) {
                $imageArr['image_size'] = $param['size'] ?? 0;
                $imageArr['image_w']    = $param['width'] ?? 0;
                $imageArr['image_h']    = $param['height'] ?? 0;
                $imageArr['file_url']   = $param['filename'] ?? '';
                $imageArr['suffix']     = $param['imageInfo_format'] ?? '';
                $UploadFileModel->where($where)->update($imageArr);
                $path = $param['path'];
                # 获取上传用户的代理信息

                list($ret, $config) = (new UploadController)->configuration($param['agent_uid']);
                if (!$ret) {
                    throw new HomeException($config);
                }
                $aliOss              = new UploadAliOssSev($config);
                $aliOss->dir         = $path;
                $param['accessHost'] = $aliOss->config['Host'];
                $param['file_url']   = $imageArr['file_url'];
                $img_url             = $aliOss->config['Host'] . '/' . $imageArr['file_url'];
                $param['img_url']    = $this->signUrl($img_url, $config);;
            }
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
        return $this->response->json([
            'code' => 200,
            'msg'  => '上传成功',
            'data' => $param,
        ]);
    }


    public function signUrl($url, $config)
    {

        //判断是否是oss地址
        if (strpos($url, 'oss-') === false) return $url;
        //处理地址，获取基础部分
        $url         = parse_url($url);
        $url['path'] = trim($url['path'], '/');
        $ossClient   = new OssClient($config['AccessKeyId'], $config['AccessKeySecret'], $config['Endpoint']);
        $result      = $ossClient->signUrl($config['Bucket'], $url['path'], '3600');

        return $result;
    }

    /**
     * @DOC 图片上传回调（开放）
     */
    #[RequestMapping(path: "imageCallback", methods: "post")]
    public function imageCallback(): ResponseInterface
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

            list($ret, $config) = (new UploadController)->configuration($param['agent_uid']);
            if (!$ret) {
                throw new HomeException($config);
            }
            $aliOss              = new OpenAliOss($config);
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


    /**
     * @DOC 微信支付回调接口
     */
    #[RequestMapping(path: "notify", methods: "post")]
    public function notify()
    {
        //获取回调数据
        $result      = $this->request->all();
        $transaction = MemberRechargeModel::where('order_no', '=', $result['out_trade_no'])
            ->select(['id', 'order_no', 'status'])
            ->first();
        if (!$transaction) {
            return $this->response->raw('FAIL');
        }
        if ($transaction['status'] == 1) {
            return $this->response->raw('SUCCESS');
        }

        //判断支付状态
        if ($result['return_code'] != 'SUCCESS' && $result['result_code'] != 'SUCCESS') {
            return $this->response->raw('FAIL');
        }

        //获取系统内及系统外单号
        $outTradeNo = $result['out_trade_no'];
        $tradeNo    = $result['transaction_id'];

        //最终业务逻辑处理
        $ret = $this->wxNotify($tradeNo, $outTradeNo, '微信支付');
        if (!$ret) {
            return $this->response->raw('FAIL');
        } else {
            return $this->response->raw('SUCCESS');
        }
    }

    /**
     * @DOC 支付宝回调地址
     */
    #[RequestMapping(path: "callback", methods: "get,post")]
    public function callback()
    {
        $params = $this->request->all();

        $api                        = config('api')['Alipay'];
        $config                     = new Config();
        $config->protocol           = "https";
        $config->gatewayHost        = "openapi.alipay.com";
        $config->signType           = $api['sign_type'];
        $config->appId              = $api['app_id'];
        $config->merchantPrivateKey = $api['merchant_private_key'];
        $config->alipayPublicKey    = $api['alipay_public_key'];

        Factory::setOptions($config);
        // 验证签名
        $alipay = Factory::payment()->common()->verifyNotify($params);

        if (!$alipay) {
            return $this->response->raw('fail');
        }

        $transaction = MemberRechargeModel::where('order_no', '=', $params['out_trade_no'])
            ->select(['id', 'order_no', 'status'])
            ->first();
        if (!$transaction) {
            return $this->response->raw('fail');
        }
        if ($transaction['status'] == 1) {
            return $this->response->raw('success');
        }


        // 判断交易状态并处理
        if ($params['trade_status'] == 'TRADE_SUCCESS') {
            $ret = $this->wxNotify($params['trade_no'], $params['out_trade_no'], '支付宝支付');
        } elseif ($params['trade_status'] == 'WAIT_BUYER_PAY') {
            $this->failNotify($params['out_trade_no'], 0);
            $ret = true;
        } elseif ($params['trade_status'] == 'TRADE_CLOSED') {
            $this->failNotify($params['out_trade_no'], 2);
            $ret = true;
        } else {
            $ret = false;
        }

        if ($ret) {
            return $this->response->raw('success');
        } else {
            return $this->response->raw('fail');
        }
    }

    # 回调函数逻辑
    public function wxNotify($tradeNo, $outTradeNo, $phrase): bool
    {
        # 更新支付单号
        $status = MemberRechargeModel::where('order_no', '=', $outTradeNo)
            ->update(['status' => 1, 'trade_no' => $tradeNo]);
        if (!$status) {
            return false;
        }
        # 获取支付单号数据
        $recharge = MemberRechargeModel::where('order_no', $outTradeNo)->first();
        if (!$recharge) {
            return false;
        }
        $recharge = $recharge->toArray();
        $member   = AgentMemberModel::where('member_uid', '=', $recharge['uid'])
            ->where('parent_join_uid', $recharge['parent_join_uid'])
            ->where('parent_agent_uid', $recharge['parent_agent_uid'])
            ->with('member')
            ->first();
        # 查询当前平台的币种（汇率）
        $platform   = AgentPlatformModel::where('agent_platform_uid', $recharge['parent_agent_uid'])
            ->first();
        $amountRate = $recharge['amount'];
        $rate       = [];
        if ($platform['currency_id'] != 4) {
            $rate       = AgentRateModel::where('agent_platform_uid', $recharge['parent_agent_uid'])
                ->with(['source', 'target'])
                ->first();
            $amountRate = number_format($recharge['amount'] * $rate['rate'], 2);
        }

        # 计算要增加的金额
        $amount = $member['amount'] + $amountRate;
        $crypt             = \Hyperf\Support\make(Crypt::class);
        $amount_encryption = $crypt->balanceEncrypt($amount);
        $agentMemberUpdate = [
            'amount'            => $amount,
            'amount_encryption' => $amount_encryption,
        ];

        $status = AgentMemberModel::where('member_uid', $recharge['uid'])
            ->where('parent_join_uid', $recharge['parent_join_uid'])
            ->where('parent_agent_uid', $recharge['parent_agent_uid'])
            ->update($agentMemberUpdate);
        if (!$status) {
            return false;
        }
        if ($recharge['child_uid']) {
            $child_uid                     = MemberChildModel::where('child_uid', $recharge['child_uid'])->first();
            $member['member']['user_name'] = $child_uid['child_name'] ?? '';
        }
        if (!empty($recharge['data'])) {
            # 转账
            $data  = json_decode($recharge['data'], true);
            $where = [
                ['member_uid', '=', $data['member_uid']],
                ['parent_agent_uid', '=', $recharge['parent_agent_uid']],
            ];

            $member_send = AgentMemberModel::where($where)->with('member')->first();

            $amount_log = [
                [
                    'member_uid'       => $member_send['member_uid'],
                    'child_uid'        => $recharge['child_uid'],
                    'parent_join_uid'  => $member_send['parent_join_uid'],
                    'parent_agent_uid' => $member_send['parent_agent_uid'],
                    'amount'           => '-' . $amountRate,
                    'currency'         => 'CNY',
                    'surplus_amount'   => $member_send['amount'],
                    'desc'             => '给' . $member['member']['user_name'] . '转账',
                    'add_time'         => time(),
                    'reason'           => $data['desc'],
                    'cfg_id'           => 24001,
                ],
                [
                    'member_uid'                => $recharge['uid'],
                    'child_uid'                 => 0,
                    'parent_join_uid'           => $recharge['parent_join_uid'],
                    'parent_agent_uid'          => $recharge['parent_agent_uid'],
                    'amount'                    => $amountRate,
                    'currency'                  => empty($rate) ? 'CNY' : $rate['source']['currency_code'],
                    'surplus_amount'            => $amount,
                    'desc'                      => '来自' . $member_send['member']['user_name'] . '充值',
                    'add_time'                  => time(),
                    'reason'                    => '',
                    'cfg_id'                    => 24005,
                    'surplus_amount_encryption' => $amount_encryption,
                ]
            ];
            MemberAmountLogModel::insert($amount_log[0]);
            $MemberAmountLogId = MemberAmountLogModel::insertGetId($amount_log[1]);
        } else {
            $rate_desc = empty($rate) ? '元' : $rate['target']['currency_name'] . '(' . $recharge['amount'] . '元)';
            # 充值
            $amount_log = [
                'member_uid'                => $recharge['uid'],
                'child_uid'                 => $recharge['child_uid'],
                'parent_join_uid'           => $recharge['parent_join_uid'],
                'parent_agent_uid'          => $recharge['parent_agent_uid'],
                'amount'                    => $amountRate,
                'currency'                  => empty($rate) ? 'CNY' : $rate['source']['currency_code'],
                'surplus_amount'            => $amount,
                'desc'                      => $recharge['type'] . '：充值' . $amountRate . $rate_desc,
                'add_time'                  => time(),
                'reason'                    => '',
                'cfg_id'                    => 24005,
                'surplus_amount_encryption' => $amount_encryption,
            ];
            $MemberAmountLogId = MemberAmountLogModel::insertGetId($amount_log);
        }
        MemberRechargeModel::where('order_no', '=', $outTradeNo)
            ->update(['amount_log_id' => $MemberAmountLogId]);

        # 支付成功，回显提示
        $this->send($recharge['uid'], '支付成功');

        # 支付成功，公众号通知
        $param       = [
            'character_string5' => $outTradeNo,   // 支付单号
            'phrase6'           => $phrase,       // 支付方式
            'amount2'           => $amountRate,   // 充值金额
            'amount8'           => $amount,       // 储值余额
            'time4'             => date('Y-m-d H:i:s'), // 充值时间
        ];
        $send_member = [
            'uid'              => $amount_log['member_uid'],
            'parent_join_uid'  => $amount_log['parent_join_uid'],
            'parent_agent_uid' => $amount_log['parent_agent_uid'],
        ];

        SendTemplate::sendNotice($send_member, $param, 'rechargeSuccess');

        return true;
    }


    /**
     * @DOC 支付失败的回调函数
     */
    public function failNotify($outTradeNo, $status): bool
    {
        MemberRechargeModel::where('order_no', '=', $outTradeNo)
            ->update(['status' => $status]);
        return true;
    }


    /**
     * @DOC 发送通知给前端
     */
    public function send($uid, $msg)
    {
        $redis = $this->container->get(Redis::class);
        $fd    = $redis->lPop('member_pay:' . $uid);
        if (!$fd) {
            return '';
        }
        $this->sender->push((int)$fd, $msg);
        return '';
    }

    /**
     * @DOC 订单微信支付回调
     */
    #[RequestMapping(path: "order/notify", methods: "post")]
    public function orderNotify()
    {

        $logger = \Hyperf\Support\make(LoggerFactory::class)->get('log', 'WxPayLogProcess');
        $param  = $this->request->all();

        // 校验微信支付回调参数
        $transaction = OrderPaymentModel::where('payment_sn', $param['out_trade_no'])->first();
        if (!$transaction) {
            $logger->info('微信支付异常错误 未查询到订单信息：', [$param]);
            return $this->response->raw('FAIL');
        }
        $transaction = $transaction->toArray();

        // 验证回调参数信息
        try {
            $configData = MemberJoinAppModel::where('member_join_uid', $transaction['parent_join_uid'])
                ->where('member_agent_uid', $transaction['parent_agent_uid'])->first();
            if (empty($configData)) {
                $logger->info('微信支付异常错误 请联系平台代理配置小程序信息：', [$param]);
                return $this->response->raw('FAIL');
            }
            $configData = $configData->toArray();
            $config     = [
                'appID'       => $configData['app_id'], // 微信支付分配的公众账号ID（企业号corpid即为此appId）
                'mch_id'      => $configData['mch_id'], // 微信支付分配的商户号
                'key'         => $configData['app_key'], // 微信支付API密钥，在商户平台设置
                'certPath'    => $configData['cert_path'], // 证书文件路径（线上）
                'keyPath'     => $configData['key_path'], // 密钥文件路径（线上）
                //                'certPath'    => '/data/project/qiye/yfd-hyperf/public/wx.pem/85/cert_content.pem', // 本地测试证书路径
                //                'keyPath'     => '/data/project/qiye/yfd-hyperf/public/wx.pem/85/key_content.pem', // 本地测试证书路径
                'sign_type'   => 'MD5', // 加密方式
                'apiDomain'   => 'https://api.mch.weixin.qq.com/',
                'reportLevel' => 2,
            ];
            $sdkRet     = (new SDK((object)$config))->verifyCallback($param);
            if (!$sdkRet) {
                $logger->info('微信支付异常错误 微信校验失败：', [$param]);
                return $this->response->raw('FAIL');
            }
        } catch (\Exception $e) {
            $logger->info('微信支付异常错误 微信校验失败：', ['Msg: ' . $e->getMessage() . 'File:' . $e->getFile() . 'Line: ' . $e->getLine()]);
            return $this->response->raw('FAIL');
        }


        if ($transaction['payment_status'] == 4) {
            return $this->response->raw('SUCCESS');
        }
        $member         = [
            'uid'              => $transaction['member_uid'],
            'child_uid'        => $transaction['child_uid'],
            'parent_join_uid'  => $transaction['parent_join_uid'],
            'parent_agent_uid' => $transaction['parent_agent_uid'],
        ];
        $payment_method = [
            'third_code' => 'wx',
            'third_name' => '微信支付',
        ];
        $ParcelPayment  = \Hyperf\Support\make(ParcelPaymentService::class);
        $ParcelPayment->AgentPlatformCache($member['parent_agent_uid']);
        $resultPay = $ParcelPayment->memberPay($member, [$transaction['order_sys_sn']], $payment_method);
        if (Arr::hasArr($resultPay, 'success')) {
            try {
                //加入结算统计
                $redis_key                  = 'queues:AsyncBillSettlementProcess';
                $data['order_sys_sn']       = [$transaction['order_sys_sn']];
                $data['member_sett_status'] = 0;
                $data['join_sett_status']   = 0;
                $redis                      = \Hyperf\Support\make(Redis::class);
                $redis->lPush($redis_key, json_encode($data));
                // 删除order_payment废弃数据
                OrderPaymentModel::where('payment_sn', $param['out_trade_no'])->delete();
                // 修改新order_payment记录
                $payment_sn   = $resultPay['success'][0]['payment_sn'];
                $orderPayment = [
                    'payment_status'   => 4,
                    'out_trade_no'     => $param['out_trade_no'],
                    'transaction_id'   => $param['transaction_id'],
                    'payment_currency' => 'CNY',
                    'payment_code'     => 'wx',
                    'payment_method'   => '微信支付',
                    'update_time'      => time()
                ];
                OrderPaymentModel::where('payment_sn', $payment_sn)->update($orderPayment);
            } catch (\Exception $e) {
                $logger->info('微信支付异常错误->' . $e->getMessage() . '->' . $e->getLine(), [$resultPay]);
                return $this->response->raw('FAIL');
            }
            return $this->response->raw('SUCCESS');
        }
        $logger->info('微信支付异常错误 resultPay 支付记录失败：', [$resultPay]);
        return $this->response->raw('FAIL');
    }


}
