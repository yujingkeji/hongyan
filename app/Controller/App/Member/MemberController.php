<?php

namespace App\Controller\App\Member;

use App\Common\Lib\Arr;
use App\Common\Lib\Crypt;
use App\Common\Lib\Str;
use App\Common\Lib\Unique;
use App\Common\Lib\UserDefinedIdGenerator;
use App\Controller\Home\HomeBaseController;
use App\Exception\HomeException;
use App\Model\AgentMemberModel;
use App\Model\AgentPlatformModel;
use App\Model\AgentRateModel;
use App\Model\CouponsModel;
use App\Model\DeliveryStationModel;
use App\Model\MemberChildModel;
use App\Model\MemberJoinAppModel;
use App\Model\MemberModel;
use App\Model\OrderModel;
use App\Model\OrderPaymentModel;
use App\Model\PriceTemplateItemModel;
use App\Model\PriceTemplateModel;
use App\Request\LibValidation;
use App\Service\ConfigService;
use App\Service\LoginService;
use App\Service\MembersService;
use App\Service\OrdersService;
use App\Service\ParcelPaymentService;
use App\Service\ParcelWeightCalcService;
use App\Service\PriceVersionCalcService;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Redis\Redis;
use Psr\Http\Message\ResponseInterface;
use Yurun\PaySDK\Weixin\JSAPI\Params\Pay\Request as WeiXinPreCreateRequest;
use Yurun\PaySDK\Weixin\SDK;
use function App\Common\Send;

#[Controller(prefix: 'app/member')]
class MemberController extends HomeBaseController
{
    /**
     * @DOC 用户详情
     */
    #[RequestMapping(path: 'info', methods: 'get,post')]
    public function info(RequestInterface $request): ResponseInterface
    {
        $userInfo = $request->UserInfo;
        $member   = MemberModel::where('uid', $userInfo['uid'])
            ->with([
                'member' => function ($query) use ($userInfo) {
                    $query->where('parent_agent_uid', $userInfo['parent_agent_uid'])
                        ->where('parent_join_uid', $userInfo['parent_join_uid'])
                        ->where('member_uid', $userInfo['uid'])
                        ->select(['member_uid', 'amount', 'warning_amount', 'code', 'app_openid']);
                }
            ])
            ->select(['uid', 'user_name', 'email', 'head_url', 'tel', 'nick_name'])
            ->first()->toArray();
        if (isset($member['member']['agent_status']) && in_array($member['member']['agent_status'], [0, 1]) && $member['member']['role_id'] == 3) {
            return $this->response->json(['code' => 200, 'msg' => '账号未审核', 'data' => []]);
        }
        # 解密用户手机号
        $member          = $this->memberDecrypt($member, true);
        $member['child'] = [];
        if ($userInfo['child_uid']) {
            $member['child'] = MemberChildModel::select(['uid', 'child_name', 'head_url'])
                ->where('child_uid', $userInfo['child_uid'])->first();
        }
        $member['platform'] = AgentPlatformModel::where('agent_platform_uid', $userInfo['parent_agent_uid'])
            ->select(['agent_platform_uid', 'currency_id', 'currency', 'web_name'])
            ->first();
        # 汇率
        $member['platform']['rate'] = AgentRateModel::where('agent_platform_uid', $userInfo['parent_agent_uid'])
            ->with(['source', 'target'])
            ->first();
        $member['join']             = MemberModel::where('uid', $userInfo['parent_join_uid'])
            ->select(['uid', 'user_name', 'nick_name', 'email', 'tel', 'head_url'])->first();
        if (!empty($member['join'])) {
            $member['join']         = $member['join']->toArray();
            $member['join']['code'] = AgentMemberModel::where('member_uid', $member['join']['uid'])->value('code');
        }

        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $member, 'user' => $userInfo]);
    }

    /**
     * @DOC 图片上传
     */
    #[RequestMapping(path: 'upload', methods: 'post')]
    public function upload(RequestInterface $request): ResponseInterface
    {
        $param = $request->all();
        $file  = $request->file('pic'); // 图片
        // 处理图片信息
        $fileData['name'] = $file->getClientFilename();
        $fileData['size'] = $file->getSize();
        $fileData['type'] = $file->getClientMediaType();
        if (in_array($fileData['type'], ['image/jpg', 'image/jpeg', 'image/png', 'image/gif'])) {
            $stream       = $file->getStream();
            $tempFilePath = tempnam(sys_get_temp_dir(), 'tmp_');
            file_put_contents($tempFilePath, $stream->getContents());
            list($fileData['width'], $fileData['height']) = getimagesize($tempFilePath);
            unlink($tempFilePath);
        } else {
            return $this->response->json(['code' => 201, 'msg' => '上传图片类型错误']);
        }
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(ConfigService::class)->uploadFile($param, $file, $fileData, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 运费计算
     */
    #[RequestMapping(path: 'calc', methods: 'post')]
    public function calc(RequestInterface $request)
    {
        $params        = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'send_country_id'   => ['required', 'integer'],  //发出国家
                'target_country_id' => ['required', 'integer'],  // 到达国家
                'weight'            => ['required', 'min:0.01'], // 重量
                'length'            => ['nullable', 'numeric', 'min:0.1'],  // 物品长度
                'width'             => ['nullable', 'numeric', 'min:0.1'],  // 物品宽度
                'height'            => ['nullable', 'numeric', 'min:0.1'],  // 物品高度
            ], [
                'send_country_id.required'   => '请选择发出国家',
                'target_country_id.required' => '请选择到达国家',
                'weight.required'            => '请输入重量（KG）',
                'length.required'            => '请输入物品长度（CM）',
                'width.required'             => '请输入物品宽度（CM）',
                'height.required'            => '请输入物品高度（CM）',
                'weight.min'                 => '重量不能小于0.01KG',
                'length.min'                 => '物品长度不能小于0.1CM',
                'width.min'                  => '物品宽度不能小于0.1CM',
                'height.min'                 => '物品高度不能小于0.1CM',
                'length.numeric'             => '物品长度必须为数字',
                'width.numeric'              => '物品宽度必须为数字',
                'height.numeric'             => '物品高度必须为数字',
            ]);

        $member = $request->UserInfo;
        // 计算价格
        // 查询线路对应的价格模版
        $where   = [
            'member_uid'        => $member['parent_agent_uid'],
            'send_country_id'   => $param['send_country_id'],
            'target_country_id' => $param['target_country_id'],
        ];
        $priceDb = PriceTemplateModel::where($where)->first();
        if (empty($priceDb)) {
            throw new HomeException('计算失败：未查询到价格模版');
        }
        $priceDb     = $priceDb->toArray();
        $where       = [
            'version_id'  => $priceDb['use_version'],
            'template_id' => $priceDb['template_id'],
        ];
        $priceItemDb = PriceTemplateItemModel::where($where)->first();
        if (empty($priceItemDb)) {
            throw new HomeException('计算失败：未查询到价格模版');
        }
        $priceItemDb = $priceItemDb->toArray();
        $WeightPrice = end($priceItemDb['price_item']);
        // 体积计算
        $volume = 0;
        if (!empty($param['length']) && !empty($param['width']) && !empty($param['height'])) {
            $volume = floor($param['length']) * floor($param['width']) * floor($param['height']) / 6000;
        }
        $weight       = max($param['weight'], $volume);
        $priceService = \Hyperf\Support\make(PriceVersionCalcService::class);
        $result       = $priceService->calcFee($WeightPrice, $weight);
        return $this->response->json(['code' => 200, 'msg' => '计算成功', 'data' => $result]);
    }


    /**
     * @DOC 微信支付调用
     */
    #[RequestMapping(path: 'wx/pay', methods: 'post')]
    public function wxPay(RequestInterface $request)
    {
        $params        = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $params        = $LibValidation->validate($params,
            [
                'order_sys_sn' => ['required', 'string'],  // 订单编号
                'openid'       => ['required',], // 微信用户openID
                'coupons_code' => ['string',], // 优惠券
            ], [
                'order_sys_sn.required' => '请输入订单编号',
                'openid.required'       => '未检测到微信账户信息',
            ]);

        $member = $request->UserInfo;
        // 获取小程序配置信息
        $configData = MemberJoinAppModel::where('member_join_uid', $member['parent_join_uid'])
            ->where('member_agent_uid', $member['parent_agent_uid'])->first();
        if (empty($configData)) {
            throw new HomeException('请联系平台代理配置小程序信息');
        }
        $configData = $configData->toArray();

        //使用优惠券
        if (!empty($params['coupons_code'])) {
            make(ParcelPaymentService::class)
                ->handleOrderToSplitCoupons(member: $member, order_sys_sn: [$params['order_sys_sn']], coupons_code: $params['coupons_code']);
        }
        // 查询订单的状态
        $orderDb = OrderModel::with([
            'cost_member_item' => function ($query) {
                $query->select(['order_sys_sn', 'payment_sn', 'charge_code', 'charge_code_name', 'payment_status', 'payment_currency', 'original_total_fee', 'payment_amount', 'exchange_rate', 'exchange_amount', 'income_currency']);
            },
            'prediction'       => function ($query) {
                $query->where('parcel_type', DeliveryStationModel::TYPE_COLLECT)->select(['order_sys_sn', 'delivery_status', 'parcel_type']);
            }
        ])->where('order_sys_sn', $params['order_sys_sn'])
            ->where('order_status', '!=', 220)
            ->first();
        if (empty($orderDb)) {
            throw new HomeException('未查询到支付订单信息');
        }

        $logger                  = $this->container->get(LoggerFactory::class)->get('log', 'AsyncSupplementWeightCalcProcess');
        $parcelWeightCalcService = \Hyperf\Support\make(ParcelWeightCalcService::class, [$logger, $member]);
        $data                    = $parcelWeightCalcService->orderToParcelCalc([$params['order_sys_sn']], $member, $params);
        $data                    = current($data['data']);
        $amount                  = $data['need_pay_fee'];

        //  }
        if ($amount <= 0) {
            throw new HomeException('当前订单未查寻到支付信息');
        }
        //$amount     = 0.01; // 测试
        $OnlyNumber = new Unique();
        $OnlyNumber->uniqueTime();
        $sn = $OnlyNumber->unique();

        // 新增order_payment
        $orderPaymentData = [
            'payment_sn'       => $sn,
            'order_sys_sn'     => $params['order_sys_sn'],
            'payment_amount'   => $amount,
            'payment_status'   => 0,
            'member_uid'       => $member['uid'],
            'parent_join_uid'  => $member['parent_join_uid'],
            'parent_agent_uid' => $member['parent_agent_uid'],
            'payment_code'     => 'wx',
            'payment_method'   => '微信支付',
            'desc'             => '微信支付前创建，作用：回调时可查询这条支付信息',
        ];
        OrderPaymentModel::insert($orderPaymentData);

        $config                      = [
            'appID'       => $configData['app_id'], // 微信支付分配的公众账号ID（企业号corpid即为此appId）
            'mch_id'      => $configData['mch_id'], // 微信支付分配的商户号
            'key'         => $configData['app_key'], // 微信支付API密钥，在商户平台设置
            'certPath'    => $configData['cert_path'], // 证书文件路径（线上）
            'keyPath'     => $configData['key_path'], // 密钥文件路径（线上）
            //            'certPath'    => '/data/project/qiye/yfd-hyperf/public/wx.pem/85/cert_content.pem', // 本地测试证书路径
            //            'keyPath'     => '/data/project/qiye/yfd-hyperf/public/wx.pem/85/key_content.pem', // 本地测试证书路径
            'sign_type'   => 'MD5', // 加密方式
            'apiDomain'   => 'https://api.mch.weixin.qq.com/',
            'reportLevel' => 2,
        ];
        $paySDK                      = new SDK((object)$config);
        $wxRequest                   = new WeiXinPreCreateRequest();
        $wxRequest->body             = '跨境助手订单支付';
        $wxRequest->out_trade_no     = $sn; // 支付单号
        $wxRequest->total_fee        = $amount * 100; // 单位：分;
        $wxRequest->spbill_create_ip = '0.0.0.0';
        $wxRequest->notify_url       = config('api')['order_notify_url']; # 回调函数;
        $wxRequest->openid           = $params['openid']; // 用户openid

        try {
            $result = $paySDK->execute($wxRequest);
            if ($paySDK->checkResult()) {
                $request            = new \Yurun\PaySDK\Weixin\JSAPI\Params\JSParams\Request;
                $request->prepay_id = $result['prepay_id'];
                $jsapiParams        = $paySDK->execute($request);
                // 最后需要将数据传给js，使用WeixinJSBridge进行支付
                return $this->response->json(['code' => 200, 'msg' => '获取配置成功', 'data' => $jsapiParams, 'out_trade_no' => $sn]);
            }
        } catch (\Exception $e) {
            return $this->response->json(['code' => 201, 'msg' => $e->getMessage(), 'data' => []]);
        }
        return $this->response->json(['code' => 201, 'msg' => '获取配置失败', 'data' => []]);
    }


    /**
     * @DOC 用户取消支付
     */
    #[RequestMapping(path: 'wx/cancel', methods: 'post')]
    public function wxCancel(RequestInterface $request)
    {
        $params        = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'out_trade_no' => ['required', 'string'],  // 订单编号
            ], [
                'out_trade_no.required' => '支付单号不能为空',
            ]);

        $member = $request->UserInfo;
        // 获取小程序配置信息
        $configData = MemberJoinAppModel::where('member_join_uid', $member['parent_join_uid'])
            ->where('member_agent_uid', $member['parent_agent_uid'])->first();
        if (empty($configData)) {
            throw new HomeException('请联系平台代理配置小程序信息');
        }
        $configData              = $configData->toArray();
        $config                  = [
            'appID'       => $configData['app_id'], // 微信支付分配的公众账号ID（企业号corpid即为此appId）
            'mch_id'      => $configData['mch_id'], // 微信支付分配的商户号
            'key'         => $configData['app_key'], // 微信支付API密钥，在商户平台设置
            'certPath'    => $configData['cert_path'], // 证书文件路径（线上）
            'keyPath'     => $configData['key_path'], // 密钥文件路径（线上）
            'sign_type'   => 'MD5', // 加密方式
            'apiDomain'   => 'https://api.mch.weixin.qq.com/',
            'reportLevel' => 2,
        ];
        $paySDK                  = new SDK((object)$config);
        $wxRequest               = new \Yurun\PaySDK\Weixin\CloseOrder\Request;
        $wxRequest->out_trade_no = $param['out_trade_no'];
        $orderPayData            = OrderPaymentModel::where('payment_sn', $param['out_trade_no'])->first();
        try {
            $result = $paySDK->execute($wxRequest);
            if ($paySDK->checkResult() && !empty($orderPayData)) {
                // 删除
                Db::table('order_payment')->where('payment_sn', $param['out_trade_no'])->delete();
                Db::table('coupons_member')->where('order_sys_sn', '=', $orderPayData->order_sys_sn)
                    ->update(['status' => 0, 'order_sys_sn' => '', 'updated_at' => 0]);
                // 返回优惠卷
                Db::table('order_cost_member_item')->where('order_sys_sn', $orderPayData->order_sys_sn)
                    ->where('member_uid', '=', $member['uid'])
                    ->where('parent_agent_uid', '=', $member['parent_agent_uid'])
                    ->where('payment_status', '=', 0)
                    ->where('charge_code', CouponsModel::COUPONS_COST_CODE)->delete();
                Db::table('order_cost_join_item')
                    ->where('order_sys_sn', '=', $orderPayData->order_sys_sn)
                    ->where('should_member_uid', '=', $member['parent_join_uid'])
                    ->where('parent_agent_uid', '=', $member['parent_agent_uid'])
                    ->where('payment_status', '=', 0)
                    ->where('charge_code', '=', CouponsModel::COUPONS_COST_CODE)->delete();
                return $this->response->json(['code' => 200, 'msg' => '关闭订单成功', 'data' => [$result]]);
            }
        } catch (\Exception $e) {
            return $this->response->json(['code' => 201, 'msg' => $e->getMessage(), 'data' => []]);
        }
        return $this->response->json(['code' => 201, 'msg' => '关闭订单失败', 'data' => []]);
    }


    /**
     * @DOC 获取微信小程序openID
     */
    #[RequestMapping(path: 'wx/openid', methods: 'post')]
    public function wxOpenid(RequestInterface $request)
    {
        $params        = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'code' => ['required', 'string'],  // 编号
            ], [
                'code.required' => 'code不能为空',
            ]);
        $member        = $request->UserInfo;
        // 获取小程序配置信息
        $configData = MemberJoinAppModel::where('member_join_uid', $member['parent_join_uid'])
            ->where('member_agent_uid', $member['parent_agent_uid'])->first();
        if (empty($configData)) {
            throw new HomeException('请联系平台代理配置小程序信息');
        }
        $configData = $configData->toArray();
        $link       = "https://api.weixin.qq.com/sns/jscode2session?appid={$configData['app_id']}&secret={$configData['app_secret']}&js_code={$param['code']}&grant_type=authorization_code";
        $ret        = Send($link, []);
        $ret        = is_array($ret) ? $ret : json_decode($ret, true);

        if (!isset($ret['openid']) || !isset($ret['session_key'])) {
            throw new HomeException('获取用户openid失败' . $ret['errmsg'] ?? '');
        } else {
            AgentMemberModel::where('parent_agent_uid', $member['parent_agent_uid'])
                ->where('parent_join_uid', $member['parent_join_uid'])
                ->where('member_uid', $member['uid'])
                ->update(['app_openid' => $ret['openid']]);
            return $this->response->json(['code' => 200, 'msg' => '获取配置成功', 'data' => $ret]);
        }
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

    /**
     * @DOC 被邀请人提交订单信息存储
     */
    #[RequestMapping(path: "invited/order", methods: "post")]
    public function invitedOrder(RequestInterface $request)
    {
        $param         = $request->all();
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param,
            [
                'key'                       => ['required'],
                'data'                      => ['required'],
                'data.receiver'             => ['required'],
                'data.receiver.name'        => ['required'],
                'data.receiver.country_id'  => ['required'],
                'data.receiver.country'     => ['min:2'],
                'data.receiver.province_id' => ['required_without:province_id', 'min:2'],
                'data.receiver.province'    => ['required_without:province', 'min:1'],
                'data.receiver.district'    => ['min:2'],
                'data.receiver.district_id' => ['integer', 'min:1'],
                'data.receiver.street'      => ['nullable'],
                'data.receiver.street_id'   => ['integer', 'nullable'],
                'data.receiver.address'     => ['required', 'string', 'min:2'],
                'data.receiver.zip'         => ['required'],
                'data.receiver.area_code'   => ['required'],
                'data.receiver.mobile'      => ['required', 'min:10'],
            ],
            [
                'key.required'                               => '当前邀请页错误，请重新发起邀请填写',
                'data.required'                              => '未检测到提交信息',
                'data.receiver.required'                     => '收件人信息不能为空',
                'data.receiver.name.required'                => '收件人姓名不能为空',
                'data.receiver.country_id.required'          => '收件人国家id不能为空',
                'data.receiver.country.min'                  => '收件人国家名称不能为空',
                'data.receiver.province_id.required_without' => '请选择省份',
                'data.receiver.province.required_without'    => '请选择省份',
                'data.receiver.province.integer'             => '收件人省份id必须为整数',
                'data.receiver.address.required'             => '收件人详细地址不能为空',
                'data.receiver.address.string'               => '收件人详细地址必须为字符串',
                'data.receiver.address.min'                  => '收件人详细地址不能少于2个字符',
                'data.receiver.zip.required'                 => '收件人邮编不能为空',
                'data.receiver.area_code.required'           => '收件人地区代码不能为空',
                'data.receiver.mobile.required'              => '收件人手机号不能为空',
                'data.receiver.mobile.min'                   => '手机号格式错误',
            ]
        );
        $redis     = \Hyperf\Support\make(Redis::class);
        $redisData = $redis->get('order_address:' . $param['key']);
        if (!empty($redisData)) {
            $redisData = json_decode($redisData, true);
            // 处理其他人已提交
            if (!empty($redisData['target_id'])) {
                return $this->response->json(['code' => 201, 'msg' => '其他用户已提交当前订单']);
            }
            $redisData['data']      = $param['data'];
            $redisData['target_id'] = $member['uid'];
            $redisData['invited']   = 1;
            $redisData['status']    = false;
            // redis 剩余时长
            $time = $redis->ttl('order_address:' . $param['key']);
            $redis->setex('order_address:' . $param['key'], $time, json_encode($redisData, true));
        }

        return $this->response->json(['code' => 200, 'msg' => '提交成功']);
    }

    /**
     * @DOC 获取信息提交订单信息
     */
    #[RequestMapping(path: "invited/get", methods: "post")]
    public function getInvited(RequestInterface $request)
    {
        $param         = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param,
            [
                'key' => ['required'],
            ],
            [
                'key.required' => '未检测到邀请码',
            ]
        );
        $redis     = \Hyperf\Support\make(Redis::class);
        $redisData = $redis->get('order_address:' . $param['key']);
        if (!empty($redisData)) {
            $redisData = json_decode($redisData, true);
        }
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $redisData]);
    }

    /**
     * @DOC 获取信息提交订单信息
     */
    #[RequestMapping(path: "invited/edit", methods: "post")]
    public function editInvitedMake(RequestInterface $request)
    {
        $param         = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param,
            [
                'key' => ['required'],
            ],
            [
                'key.required' => '未检测到邀请码',
            ]
        );
        $redis     = \Hyperf\Support\make(Redis::class);
        $redisData = $redis->get('order_address:' . $param['key']);
        if (!empty($redisData)) {
            $orderData                = json_decode($redisData, true);
            $orderData['is_complete'] = true;
            $time                     = $redis->ttl('order_address:' . $param['key']);
            $redis->setex('order_address:' . $param['key'], $time, json_encode($orderData, true));
            return $this->response->json(['code' => 200, 'msg' => '制单成功', 'data' => []]);
        }
        return $this->response->json(['code' => 201, 'msg' => '邀请码错误', 'data' => []]);
    }

    /**
     * @DOC 根据邀请码查询信息
     */
    #[RequestMapping(path: "invited/info", methods: "post")]
    public function getInvitedInfo(RequestInterface $request)
    {
        $param         = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param,
            [
                'key' => ['required'],
            ],
            [
                'key.required' => '未检测到邀请码',
            ]
        );

        $order = OrderModel::where('user_custom_sn', $param['key'])
            ->select(['member_uid', 'invited_uid', 'order_sys_sn', 'user_custom_sn'])
            ->first();
        if (empty($order)) {
            // is_order = false 未制单
            $data['is_order'] = false;
            return $this->response->json(['code' => 200, 'msg' => '未检测到订单', 'data' => $data]);
        }
        // is_order = true  发送人UID， 填写人 UID  order_sys_sn 订单号
        $data['is_order']       = true;
        $data['member_uid']     = $order->member_uid;
        $data['invited_uid']    = $order->invited_uid;
        $data['order_sys_sn']   = $order->order_sys_sn;
        $data['user_custom_sn'] = $order->user_custom_sn;
        return $this->response->json(['code' => 200, 'msg' => '查询成功', 'data' => $data]);
    }

    /**
     * @DOC 扫描加盟商二维码
     */
    #[RequestMapping(path: "scan/qrcode", methods: "post")]
    public function scanQrCode(RequestInterface $request)
    {
        $params        = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($params,
            [
                'code' => ['required'],
            ],
            [
                'code.required' => '未检测到加盟商编码',
            ]
        );
        $member = $request->UserInfo;
        // 扫码加盟商信息
        $joinMemberInfo = AgentMemberModel::where('code', $params['code'])->first();
        if (empty($joinMemberInfo)) {
            return $this->response->json(['code' => 201, 'msg' => '加盟商编码不存在']);
        }
        if ($member['parent_join_uid'] == $joinMemberInfo->member_uid) {
            return $this->response->json(['code' => 201, 'msg' => '已登录当前加盟商下用户']);
        }
        // 当前用户信息
        $memberInfo = MemberModel::where('uid', $member['uid'])->first()->toArray();
        $tel        = base64_decode((new Crypt())->decrypt($memberInfo['tel']));

        // 查找当前加盟商下用户
        $multiple = MemberModel::where('tel', $memberInfo['tel'])
            ->where('role_id', 5)
            ->whereHas('member', function ($query) use ($joinMemberInfo) {
                $query->where('parent_agent_uid', $joinMemberInfo->parent_agent_uid)
                    ->where('parent_join_uid', $joinMemberInfo->member_uid);
            })
            ->first();

        $loginService = \Hyperf\Support\make(LoginService::class);

        if ($multiple) {
            $multiple = $multiple->toArray();
            // 用户存在
            $member_uid = $multiple['uid'];
            $username   = $multiple['user_name'];
        } else {
            // 注册用户
            $hash       = Str::random(6);
            $memberData = [
                'head_url'  => 'https://open-hjd.oss-cn-hangzhou.aliyuncs.com/yfd/user/icon/head/head_' . rand(1, 36) . '.png',
                'hash'      => $hash,
                'reg_time'  => time(),
                'area_code' => $memberInfo['area_code'],
                'tel'       => $memberInfo['tel'],
                'status'    => 1, // 等待审核状态
                'role_id'   => 5, // 角色关系
            ];

            $generator               = \Hyperf\Support\make(UserDefinedIdGenerator::class);
            $user_name_sn            = $generator->generate($member['parent_join_uid']);
            $memberData['user_name'] = $user_name_sn;
            $memberData['nick_name'] = $user_name_sn;

            $memberData['user_password'] = $loginService->mkPw($memberData['user_name'], $tel, $hash);
            $member_uid                  = Db::table('member')->insertGetId($memberData);
            $username                    = $memberData['user_name'];
            $agentMemberData             = [
                'member_uid'       => $member_uid,
                'parent_join_uid'  => $joinMemberInfo->member_uid,
                'parent_agent_uid' => $joinMemberInfo->parent_agent_uid,
                'role_id'          => 5,
                'agent_status'     => 2,
                'add_time'         => time(),
                'code'             => 'M' . str_pad($member_uid, 4, '0', STR_PAD_LEFT)
            ];
            Db::table('agent_member')->insert($agentMemberData);
        }

        // 生成登录token
        $tokenData                 = [
            'uid'              => $member_uid,
            'member_uid'       => $member_uid,
            'parent_join_uid'  => $joinMemberInfo->member_uid,
            'parent_agent_uid' => $joinMemberInfo->parent_agent_uid,
            'role_id'          => 5,
            'user_name'        => $username,
        ];
        $tokenData['role']['name'] = '个人商家';
        $result['code']            = 200;
        $result['msg']             = '获取成功';
        $result['token']           = $loginService->JWT($tokenData, [], 720);
        return $result;
    }

    /**
     * @DOC 查询当前用户其它账号信息
     */
    #[RequestMapping(path: "other/account", methods: "post")]
    public function otherAccount(RequestInterface $request)
    {
        $member  = $request->UserInfo;
        $service = \Hyperf\Support\make(MembersService::class);
        $result  = $service->otherAccount($member);
        return $this->response->json($result);
    }


}
