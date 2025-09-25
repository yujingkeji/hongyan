<?php

namespace App\Common\Lib;


use App\Model\AgentMemberModel;
use App\Model\MemberThirdConfigureItemModel;
use App\Model\MemberThirdConfigureModel;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Redis\Redis;

class SendTemplate
{
    # 公众号
    public static function sendNotice($member, $param, $type)
    {
        # 获取用户的openid
        $openid = self::getOpenId($member);
        if (empty($openid)) {
            return [false, '未查询到绑定的用户信息'];
        }
        # 获取数据模板ID
        $TemplateData = self::getTemplate($member, $type, $param);
        if (!$TemplateData) {
            return false; // 未查询到参数信息
        }
        # 数据拼接
        $data = [
            'touser'      => $openid, // openID
            'template_id' => $TemplateData['template_id'], // 模板ID
            'miniprogram' => [
                'appid'    => '', // 小程序AppID
                'pagepath' => '', // 小程序跳转页面
            ],
            'data'        => $TemplateData['data']
        ];

        list($access_token, $msg) = self::getAccessToken($member);

        if (!$access_token) {
            return [$access_token, $msg];
        }
        // 公众号推送
        $sendUrl = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token={$access_token}";

        $options = [
            'http' => [
                'method'        => 'POST',
                'header'        => 'Content-Type: application/json',
                'content'       => json_encode($data),
                'ignore_errors' => true // 忽略错误响应
            ]
        ];

        $context = stream_context_create($options);
        return json_decode(file_get_contents($sendUrl, false, $context), true);
    }

    /**
     * @DOC 获取 公众号 access_token
     */
    public static function getAccessToken($member): array
    {
        $redis = \Hyperf\Support\make(Redis::class);
        // 获取平台 公众号信息
        list($appid, $secret) = self::configuration($member['parent_agent_uid']);
        if (!$appid) {
            // 获取 公众号配置失败，返回错误信息
            return [$appid, $secret];
        }
        $token_url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$secret}&scope=snsapi_base";
        $token_ret = json_decode(file_get_contents($token_url), true);
        if (isset($token_ret['access_token']) && $token_ret['access_token']) {
            # 缓存微信 token
            $redis->set('wx_token:' . $appid, $token_ret['access_token'], 7000);
            return [$token_ret['access_token'], '获取成功'];
        } else {
            $logger = \Hyperf\Support\make(LoggerFactory::class)->get('log', 'text');
            $logger->info('token:', [$token_ret]);
            return [false, '获取失败'];
        }
    }

    /**
     * @DOC 获取平台微信公众号的配置信息
     */
    public static function configuration($parent_agent_uid): array
    {
        $cfgWhere = [
            ['third_id', '=', 101],
            ['status', '=', 1],
            ['member_uid', '=', $parent_agent_uid],
        ];
        $cfg      = MemberThirdConfigureModel::where($cfgWhere)->first();
        if (!$cfg) {
            return [false, '平台未开启微信公众号通知'];
        }

        $where = [
            ['member_third_id', '=', $cfg['member_third_id']],
            ['member_uid', '=', $parent_agent_uid],
        ];

        $wx = MemberThirdConfigureItemModel::where($where)->get()->toArray();
        if (empty($wx)) {
            return [false, '平台微信公众号未进行配置'];
        }
        $AppID     = '';
        $AppSecret = '';

        foreach ($wx as $v) {
            if ($v['field'] == 'AppID') {
                $AppID = $v['field_value'];
            }
            if ($v['field'] == 'AppSecret') {
                $AppSecret = $v['field_value'];
            }
        }
        return [$AppID, $AppSecret];
    }

    /**
     * @DOC 获取平台设置的消息模板
     */
    protected static function getTemplate($member, $type, $param): array|bool
    {
        $itemWhere = [
            ['member_uid', '=', $member['parent_agent_uid']],
            ['field', '=', $type],
        ];

        $templateParam = MemberThirdConfigureItemModel::where($itemWhere)->first();
        if (empty($templateParam)) {
            return false;
        }
        switch ($type) {
            case 'placeOrderId': // 下单通知
                $data = [
                    'character_string7' => ['value' => $param['character_string7']],    // 订单号
                    'thing2'            => ['value' => $param['thing2']],               // 收件人
                    'thing3'            => ['value' => $param['thing3']],               // 收件地址
                    'time5'             => ['value' => $param['time5']],                // 下单时间
                    'thing6'            => ['value' => $param['thing6']],               // 商品名称
                ];
                break;
            case 'obligation': // 待付款
                $data = [
                    'character_string4' => ['value' => $param['character_string4']],          // 订单号
                    'thing6'            => ['value' => $param['thing6']],                     // 订单信息
                    'character_string5' => ['value' => $param['character_string5'] . 'KG'],   // 重量
                    'amount2'           => ['value' => $param['amount2'] . '元'],             // 金额
                    'time3'             => ['value' => $param['time3']],                      // 时间
                ];
                break;
            case 'rechargeSuccess': // 充值成功通知
                $data = [
                    'character_string5' => ['value' => $param['character_string5']],    // 支付单号
                    'phrase6'           => ['value' => $param['phrase6']],              // 支付方式
                    'amount2'           => ['value' => $param['amount2'] . '元'],       // 充值金额
                    'amount8'           => ['value' => $param['amount8'] . '元'],       // 储值余额
                    'time4'             => ['value' => $param['time4']],                // 充值时间
                ];
                break;
            case 'orderException': // 订单异常提醒
                $data = [
                    'character_string1' => ['value' => $param['character_string1']],    // 订单号
                    'const3'            => ['value' => $param['const3']],               // 异常原因
                    'time7'             => ['value' => $param['time7']],                // 业务日期
                ];
                break;
            case 'packageStorage': // 包裹入库提醒
                $data = [
                    'character_string2' => ['value' => $param['character_string2']],    // 快递单号
                    'character_string3' => ['value' => $param['character_string3']],    // 包裹号
                    'time6'             => ['value' => $param['time6']],                // 入库时间
                ];
                break;
            case 'orderOutbound': // 订单已出库提醒
                $data = [
                    'character_string1' => ['value' => $param['character_string1']],    // 订单号
                    'const5'            => ['value' => $param['const5']],               // 订单状态
                    'time4'             => ['value' => $param['time6']],                // 更新时间
                ];
                break;
            case 'repairTaxation': // 货物税费补缴通知
                $data = [
                    'character_string2' => ['value' => $param['character_string2']],    // 订单号
                    'short_thing3'      => ['value' => $param['short_thing3']],         // 订单状态
                    'amount4'           => ['value' => $param['amount4'] . '元'],       // 缴税金额
                    'time5'             => ['value' => $param['time5']],                // 截止时间
                ];
                break;
            case 'repairFreight': // 快递订单补费通知(运费)
                $data = [
                    'character_string3' => ['value' => $param['character_string3']],           // 快递单号
                    'character_string1' => ['value' => $param['character_string1'] . 'KG'],    // 实际重量
                    'amount2'           => ['value' => $param['amount2'] . '元'],              // 补费金额
                ];
                break;
            default:
                return false;
        }

        return ['template_id' => $templateParam->toArray()['field_value'], 'data' => $data];
    }

    /**
     * @DOC 获取用户的openID
     */
    public static function getOpenId($member)
    {
        $where = [
            ['member_uid', '=', $member['uid']],
            ['parent_join_uid', '=', $member['parent_join_uid']],
            ['parent_agent_uid', '=', $member['parent_agent_uid']]
        ];
        return AgentMemberModel::where($where)->value('openid');
    }


}
