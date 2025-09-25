<?php

namespace App\Controller\Home\Member;

use App\Common\Lib\Arr;
use App\Controller\Home\AbstractController;
use App\Exception\HomeException;
use App\Model\MemberThirdConfigureItemModel;
use App\Model\MemberThirdConfigureModel;
use App\Model\ThirdConfigureFieldModel;
use App\Model\ThirdConfigureModel;
use App\Request\MemberRequest;
use App\Request\ThirdConfigureRequest;
use App\Service\Cache\BaseCacheService;
use App\Service\Cache\BaseEditUpdateCacheService;
use App\Service\SmsService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;


#[Controller(prefix: "member/sms")]
class SmsController extends AbstractController
{

    #[Inject]
    protected BaseCacheService $baseCacheService;

    #[Inject]
    protected SmsService $service;

    /**
     * @DOC 配置列表
     */
    #[RequestMapping(path: "lists", methods: "get,post")]
    public function lists(RequestInterface $request): ResponseInterface
    {
        $pid   = $request->input('pid', 0);
        $param = $request->all();
        $where = [];
        if (Arr::hasArr($param, 'status', true)) {
            $where[] = ['status', '=', $param['status']];
        }

        if (!$pid) throw new HomeException('配置信息错误');
        $member     = $request->UserInfo;
        $configData = MemberThirdConfigureModel::query()
            ->whereHas('third', function ($query) use ($pid) {
                $query->where('pid', '=', $pid)->select(['third_id']);
            })
            ->where('member_uid', '=', $member['parent_agent_uid'])
            ->where($where)->get()->toArray();

        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $configData]);
    }

    /**
     * @DOC 配置短信参数
     */
    #[RequestMapping(path: "cfg", methods: "get,post")]
    public function cfg(RequestInterface $request): ResponseInterface
    {
        $SmsRequest = $this->container->get(ThirdConfigureRequest::class);
        $SmsRequest->scene('default')->validated();
        $param  = $request->all();
        $member = $request->UserInfo;
        # 查询code 是否存在
        $configure = ThirdConfigureModel::where('third_code', $param['third_code'])->first();
        if (!$configure) {
            throw new HomeException('未查询到配置信息');
        }
        $where = [
            ['third_id', '=', $configure['third_id']],
            ['member_uid', '=', $member['uid']],
        ];

        $MemberThirdConfigure = MemberThirdConfigureModel::where($where)->first();

        $MemberThirdConfigureData['member_uid'] = $member['uid'];
        $MemberThirdConfigureData['status']     = $param['status'];
        $MemberThirdConfigureData['third_id']   = $configure['third_id'];
        $MemberThirdConfigureData['third_code'] = $configure['third_code'];
        $MemberThirdConfigureData['third_name'] = $configure['third_name'];
        if (empty($MemberThirdConfigure)) {
            $MemberThirdConfigureData['add_time']    = time();
            $MemberThirdConfigure['member_third_id'] = MemberThirdConfigureModel::insertGetId($MemberThirdConfigureData);
        } else {
            MemberThirdConfigureModel::where('member_third_id', $MemberThirdConfigure['member_third_id'])->update($MemberThirdConfigureData);
        }
        $mustField = [];
        switch ($param['third_code']) {
            # 短信配置：华全
            case 'huaquan':
                $mustField = ['appKey', 'appSecret'];
                break;
            # 支付方式：余额 -- 不需要配置参数
            case 'balance':
                return $this->response->json(['code' => 200, 'msg' => '配置成功', 'data' => []]);
            # 支付方式：支付宝
            case 'alipay':
                $mustField = ['appid', 'alipay_public_key', 'alipay_private_key'];
                break;
            # 支付方式：微信
            case 'weixin':
                $mustField = ['appID', 'mch_id', 'key', 'certContent', 'keyContent'];
                break;
            # oss存储：阿里云
            case 'aliyun':
                $mustField = ['Endpoint', 'Host', 'Bucket', 'AccessKeyId', 'AccessKeySecret', 'Expire', 'OpenHost', 'OpenBucket'];
                break;
            # 公众号配置
            case 'wx_official':
                $mustField = ['AppID', 'AppSecret'];
                break;
            # 模板配置
            case 'template_id':
                $mustField = ['rechargeSuccess', 'orderException', 'repairTaxation', 'repairFreight'];
                break;
            # 备案设置
            case 'record':
                $mustField = ['app_key', 'app_secret'];
                break;

        }
        # 处理字段信息
        $itemData = [];
        foreach ($param['item'] as $key => $val) {
            # 消息模板 可为空
            if ($param['third_code'] != 'template_id') {
                if (!$val) {
                    throw new HomeException('提交信息不可为空');
                }
            }
            if (in_array($key, $mustField)) {
                unset($cfg);
                # 特殊处理微信支付的证书路径
                if ($param['third_code'] == 'weixin' && in_array($key, ['certContent', 'keyContent'])) {
                    $path        = env('CERT_PATH');
                    $field_value = $path . $member['uid'] . '/' . $key . '.pem';
                    if (!file_exists($path . $member['uid'])) {
                        mkdir($path . $member['uid'], 0777, true);
                    }
                    $file = fopen($field_value, 'w');
                    fwrite($file, $val);
                    fclose($file);
                }
                $cfg['field']           = $key;
                $cfg['field_value']     = $val;
                $cfg['member_uid']      = $member['uid'];
                $cfg['member_third_id'] = $MemberThirdConfigure['member_third_id'];
                array_push($itemData, $cfg);
                if ($param['third_code'] == 'weixin' && $key == 'certContent') {
                    $cfg['field']           = 'certPath';
                    $cfg['field_value']     = $field_value ?? '';
                    $cfg['member_uid']      = $member['uid'];
                    $cfg['member_third_id'] = $MemberThirdConfigure['member_third_id'];
                    array_push($itemData, $cfg);
                }
                if ($param['third_code'] == 'weixin' && $key == 'keyContent') {
                    $cfg['field']           = 'keyPath';
                    $cfg['field_value']     = $field_value ?? '';
                    $cfg['member_uid']      = $member['uid'];
                    $cfg['member_third_id'] = $MemberThirdConfigure['member_third_id'];
                    array_push($itemData, $cfg);
                }
            }
        }
        if (!empty($itemData)) {
            MemberThirdConfigureItemModel::where('member_uid', $member['uid'])
                ->where('member_third_id', $MemberThirdConfigure['member_third_id'])->delete();
            MemberThirdConfigureItemModel::insert($itemData);
        }
        \Hyperf\Support\make(BaseEditUpdateCacheService::class)->memberPaymentMethodCache($member['parent_agent_uid']);
        return $this->response->json(['code' => 200, 'msg' => '配置成功', 'data' => []]);

    }

    /**
     * @DOC 获取配置信息
     */
    #[RequestMapping(path: "info", methods: "post")]
    public function info(RequestInterface $request): ResponseInterface
    {
        $SmsRequest = $this->container->get(ThirdConfigureRequest::class);
        $param      = $SmsRequest->scene('info')->validated();
        $member     = $request->UserInfo;

        $fieldWhere = [
            ['third_id', '=', $param['third_id']],
            ['status', '=', 1],
        ];
        $infoWhere  = [
            ['member_uid', '=', $member['uid']],
        ];
        $field      = ThirdConfigureFieldModel::where($fieldWhere)
            ->with(['fieldValue' => function ($fieldValue) use ($infoWhere) {
                $fieldValue->where($infoWhere);
            }])
            ->orderBy('sort')
            ->get()->toArray();

        return $this->response->json(['code' => 200, 'msg' => 'success', 'data' => $field]);
    }

    /**
     * @DOC 获取配置信息
     */
    #[RequestMapping(path: "modify", methods: "post")]
    public function modify(RequestInterface $request): ResponseInterface
    {
        $param  = $request->input('list', []);
        $member = $request->UserInfo;

        foreach ($param as $v) {
            unset($where, $update);
            $where  = [
                ['third_id', '=', $v['third_id']],
                ['member_uid', '=', $member['uid']],
            ];
            $update = ['status' => $v['status']];
            MemberThirdConfigureModel::where($where)->update($update);
        }
        return $this->response->json(['code' => 200, 'msg' => '修改成功', 'data' => []]);
    }

    /**
     * @DOC 发送手机号验证码
     */
    #[RequestMapping(path: "sendCode", methods: "post")]
    public function sendCode(): ResponseInterface
    {
        $phoneRequest = $this->container->get(MemberRequest::class);
        $param        = $phoneRequest->scene('sendCode')->validated();

        $this->service->send($param['area_code'], $param['mobile'], $param['flag']);
        return $this->response->json(['code' => 200, 'msg' => 'success', 'data' => []]);
    }


}
