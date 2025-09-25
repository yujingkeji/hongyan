<?php

namespace App\Controller\Home\Member;

use App\Common\Lib\SendTemplate;
use App\Controller\Home\HomeBaseController;
use App\Exception\HomeException;
use App\Model\AgentMemberModel;
use App\Model\OrderCostMemberItemModel;
use App\Model\OrderModel;
use App\Model\ParcelSendModel;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Redis\Redis;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: "member/wechat")]
class WechatController extends HomeBaseController
{

    /**
     * @DOC 鉴权
     */
    public function check_signature($param): bool|string
    {
        $signature = $param['signature'];
        $timestamp = $param['timestamp'];
        $nonce     = $param['nonce'];
        $token     = 'yfd';
        $tmpArr    = array($token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr          = implode($tmpArr);
        $tmpStr          = sha1($tmpStr);
        $param['tmpStr'] = $tmpStr;
        //重点
        header("Content-type: text/html; charset=utf-8");
        if ($tmpStr == $signature) {
            // 设置时使用
            return htmlspecialchars($param['echostr']);
        } else {
            return false;
        }
    }

    /**
     * @DOC 公众号配置事件
     */
    #[RequestMapping(path: "handle", methods: "get,post")]
    public function handle(RequestInterface $request): ResponseInterface
    {
        $eventData = $request->all();

        if (!isset($eventData['FromUserName'])) {
            # 鉴权 配置信息
            $msg = $this->check_signature($eventData);
            return $this->response->raw($msg);
        }
        $logger = \Hyperf\Support\make(LoggerFactory::class)->get('log', 'text');
        $logger->info('data:', [$eventData]);
        $openid = $eventData['FromUserName'];

        $msg = '';
        # 事件
        if (isset($eventData['Event'])) {
            if ($eventData['Event'] === 'subscribe') {
                # 关注事件
                if ($eventData['EventKey'] != []) {
                    # redis 取出逻辑处理
                    $key   = 'wx_qrcode:' . substr($eventData['EventKey'], 8);
                    $redis = \Hyperf\Support\make(Redis::class);
                    $val   = $redis->get($key);
                    if (!$val) {
                        return $this->response->raw('success');
                    }
                    $member = explode(',', $val);

                    list($ret, $msg) = $this->saveToDatabase($openid, $member);
                    if ($ret) {
                        $msg = '感谢您的关注！';
                    }
                } else {
                    $msg = '感谢您的关注！';
                }

            } elseif ($eventData['Event'] === 'SCAN') {
                # 扫码事件
                if ($eventData['EventKey'] != []) {
                    # redis 取出逻辑处理
                    $key   = 'wx_qrcode:' . $eventData['EventKey'];
                    $redis = \Hyperf\Support\make(Redis::class);
                    $val   = $redis->get($key);
                    if (!$val) {
                        return $this->response->raw('success');
                    }
                    $member = explode(',', $val);

                    list($ret, $msg) = $this->saveToDatabase($openid, $member);
                    if ($ret) {
                        $msg = '扫描成功，已进行绑定跨境助手';
                    }
                } else {
                    $msg = '扫描成功，已进行绑定跨境助手';
                }
            } else {
                $msg = '';
            }
        }

        # 消息回复
        if (isset($eventData['MsgType']) && $eventData['MsgType'] === 'text') {
            if ($eventData['Content'] == '取消解绑') {
                $msg = "请回复 '确认解绑'，即可进行解绑操作";
            } else if ($eventData['Content'] === '确认解绑') {
                list($ret, $msg) = $this->unbinding($eventData['FromUserName']);
            } else {
                $msg = '';
            }
        }
        $replyXml = $this->replyMessage($eventData, $msg);
        // 返回回复 XML 给微信服务器
        $this->response->getBody()->write($replyXml);
        return $this->response->withAddedHeader('Content-Type', 'application/xml');
    }

    /**
     * @DOC 被动回复事件消息
     */
    public function replyMessage($eventData, $msg): string
    {
        // 构建回复 XML
        return sprintf(
            '<xml>
                <ToUserName><![CDATA[%s]]></ToUserName>
                <FromUserName><![CDATA[%s]]></FromUserName>
                <CreateTime>%d</CreateTime>
                <MsgType><![CDATA[text]]></MsgType>
                <Content><![CDATA[%s]]></Content>
            </xml>',
            $eventData['FromUserName'],
            $eventData['ToUserName'],
            time(),
            $msg
        );

    }

    /**
     * @DOC 更新用户 openID
     */
    private function saveToDatabase(string $openid, $member)
    {
        $memberOpenId = AgentMemberModel::where('openid', $openid)->first();
        if ($memberOpenId) {
            return [false, '您已绑定过账号'];
        }

        AgentMemberModel::where('member_uid', $member[0])
            ->where('parent_join_uid', $member[1])
            ->where('parent_agent_uid', $member[2])
            ->update(['openid' => $openid]);
        return [true, ''];
    }

    /**
     * @DOC 解绑 账号与微信的关系
     */
    private function unbinding($openid): array
    {
        $agent_member_uid = AgentMemberModel::where('openid', $openid)->value('agent_member_uid');
        if (!$agent_member_uid) {
            return [false, '抱歉，您的微信未进行绑定账号'];
        }
        AgentMemberModel::where('agent_member_uid', $agent_member_uid)->update(['openid' => '']);
        return [true, '账号解绑成功'];
    }

    /**
     * @DOC 收货工作台--补费通知
     */
    #[RequestMapping(path: "takeOverNotice", methods: "post")]
    public function takeOverNotice(RequestInterface $request): ResponseInterface
    {
        # 参数校验
        $validationFactory = \Hyperf\Support\make(ValidatorFactoryInterface::class);
        $validator         = $validationFactory->make(
            $request->all(), ['order_sys_sn' => 'required'],
            [
                'order_sys_sn.required' => '系统单号必填',
            ]
        );
        if ($validator->fails()) {
            throw new HomeException($validator->errors()->first(), 201);
        }
        $param = $validator->validated();

        # 获取用户信息
        $order = OrderModel::where('order_sys_sn', $param['order_sys_sn'])->first();
        if (!$order) {
            return $this->response->json(['code' => 200, 'msg' => '未查询到订单', 'data' => []]);
        }

        # 获取补交费用
        $fee = OrderCostMemberItemModel::where('order_sys_sn', $param['order_sys_sn'])
            ->where('payment_status', '=', 0)
            ->sum('exchange_amount');

        if (!$fee) {
            return $this->response->json(['code' => 200, 'msg' => '无补交费用', 'data' => []]);
        }

        # 获取收货时的重量
        $parcelSend = ParcelSendModel::where('order_sys_sn', $param['order_sys_sn'])->first();

        $member['uid']              = $order['member_uid'];
        $member['parent_join_uid']  = $order['parent_join_uid'];
        $member['parent_agent_uid'] = $order['parent_agent_uid'];

        $data = [
            'character_string3' => $order['order_sys_sn'],           // 快递单号
            'character_string1' => $parcelSend['receive_weight'],    // 实际重量
            'amount2'           => $fee,                             // 补费金额
        ];
        SendTemplate::sendNotice($member, $data, 'repairFreight');
        return $this->response->json(['code' => 200, 'msg' => '发送成功', 'data' => []]);
    }


}
