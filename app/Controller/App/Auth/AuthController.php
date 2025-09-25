<?php

namespace App\Controller\App\Auth;

use App\Common\Lib\Crypt;
use App\Controller\Home\HomeBaseController;
use App\Exception\HomeException;
use App\Model\MemberJoinAppModel;
use App\Model\ParcelModel;
use App\Request\LibValidation;
use App\Service\AuthService;
use App\Service\Cache\BaseCacheService;
use App\Service\LoginService;
use App\Service\OrderParcelLogService;
use App\Service\SmsService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: 'app/auth')]
class AuthController extends HomeBaseController
{

    /**
     * @DOC 小程序登录
     */
    #[RequestMapping(path: 'login', methods: 'get,post')]
    public function login(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '查询失败';
        $params         = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $param          = $LibValidation->validate($params,
            [
                'appid' => ['required', 'string', 'min:10', 'max:50'],
                'code'  => ['required', 'string', 'min:1', 'max:100'],
            ], [
                'appid.required' => 'appid不能为空',
                'appid.string'   => 'appid必须为字符串',
                'appid.min'      => 'appid最小长度为10',
                'appid.max'      => 'appid最大长度为50',
                'code.required'  => 'code不能为空',
                'code.string'    => 'code必须为字符串',
                'code.min'       => 'code最小长度为1',
                'code.max'       => 'code最大长度为50',
            ]);
        $service        = \Hyperf\Support\make(AuthService::class);
        $result         = $service->login($param);

        return $this->response->json($result);
    }


    /**
     * @DOC 手机号登录
     */
    #[RequestMapping(path: 'mobile/login', methods: 'get,post')]
    public function mobileLogin(RequestInterface $request)
    {
        $params        = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'area_code' => ['required',],
                'mobile'    => ['required', 'string', 'min:10', 'max:50'],
                'code'      => ['required', 'min:4', 'max:6'],
                'app_id'    => ['required', 'string', 'min:10', 'max:50'],
            ],
            [
                'mobile.required' => '请输入手机号',
                'mobile.string'   => '手机号必须为字符串',
                'mobile.min'      => '手机号格式错误',
                'code.required'   => '请输入验证码',
                'code.min'        => '验证码最少为4个字符',
                'code.max'        => '验证码最多为6个字符',
                'app_id.required' => '未查询到小程序信息',
            ]
        );

        $appData = MemberJoinAppModel::where('app_id', $param['app_id'])->first();
        if (empty($appData)) {
            throw new HomeException('未查询到小程序信息');
        }
        $appData = $appData->toArray();

        // 校验手机号验证码
        (new SmsService())->checkVerifyCode($params['code'], $params['area_code'], $params['mobile'], 4);

        $loginService  = \Hyperf\Support\make(LoginService::class);
        $telLoginParam = ['area_code' => $param['area_code'], 'tel' => $param['mobile']];
        $result        = $loginService->phoneLogin($telLoginParam, $appData);
        return $this->response->json(['code' => 200, 'msg' => '登录成功', 'data' => ['token' => $result]]);

    }

    /**
     * @DOC 发送手机验证码
     */
    #[RequestMapping(path: "send/code", methods: "post")]
    public function sendCode(): ResponseInterface
    {
        $params        = $this->request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'area_code' => ['required', 'string', 'min:2'],
                'mobile'    => ['required', 'string', 'min:11'],
                'app_id'    => ['required'],
            ],
            [
                'area_code.required' => '请输入国家区号',
                'area_code.string'   => '国家区号必须为字符串',
                'area_code.min'      => '国家区号最少为2个字符',
                'mobile.required'    => '请输入手机号',
                'mobile.string'      => '手机号必须为字符串',
                'mobile.min'         => '手机号格式错误',
                'app_id.required'    => '未查询到小程序信息',
            ]
        );
        $appData       = MemberJoinAppModel::where('app_id', $param['app_id'])->first();
        if (empty($appData)) {
            throw new HomeException('未查询到小程序信息');
        }
        $service        = \Hyperf\Support\make(SmsService::class);
        $service->power = false;
        $service->send($param['area_code'], $param['mobile'], 4, $appData->member_agent_uid);
        return $this->response->json(['code' => 200, 'msg' => 'success', 'data' => []]);
    }

    /**
     * @DOC 获取手机号的国际区号数据
     */
    #[RequestMapping(path: "area", methods: "post")]
    public function listMobileAreaCode(): ResponseInterface
    {
        $data = \Hyperf\Support\make(BaseCacheService::class)->CountryCodeCache();
        $data = array_map(function ($item) {
            if (!empty($item['zip_code'])) {
                return [
                    'country_id' => $item['country_id'],
                    'area_code'  => $item['zip_code'],
                    'area_name'  => $item['country_name'],
                ];
            }
        }, $data);
        $data = array_filter($data, function ($value) {
            return !is_null($value);
        });
        return $this->response->json(['code' => 200, 'msg' => 'success', 'data' => $data]);
    }

    /**
     * @DOC 根据分享运单号查找分享信息
     */
    #[RequestMapping(path: "share", methods: "post")]
    public function shareInfo(RequestInterface $request)
    {
        $params        = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'transport_sn' => ['required',],
            ], [
                'transport_sn.required' => '运单号不能为空',
            ]);

        $parcel = ParcelModel::where('transport_sn', $param['transport_sn'])
            ->with([
                'sender'   => function ($query) {
                    $query->select(['order_sys_sn', 'name']);
                },
                'receiver' => function ($query) {
                    $query->select(['order_sys_sn', 'name']);
                },
                'line'     => function ($query) {
                    $query->with([
                        'send'   => function ($query) {
                            $query->select(['country_id', 'country_name', 'country_code']);
                        },
                        'target' => function ($query) {
                            $query->select(['country_id', 'country_name', 'country_code']);
                        }
                    ])->select(['line_id', 'line_name', 'send_country_id', 'target_country_id']);
                }
            ])
            ->select(['order_sys_sn', 'transport_sn', 'parcel_status', 'line_id'])
            ->first();
        if (empty($parcel)) {
            return $this->response->json(['code' => 201, 'msg' => '运单号查询失败', 'data' => []]);
        }
        $parcel = $parcel->toArray();
        // 解密收发件人
        try {
            $crypt = \Hyperf\Support\make(Crypt::class);
            if (!empty($parcel['sender']['name'])) {
                $sender_name              = base64_decode($parcel['sender']['name']);
                $parcel['sender']['name'] = $crypt->decrypt($sender_name);
            }
            if (!empty($parcel['receiver']['name'])) {
                $receiver_name              = base64_decode($parcel['receiver']['name']);
                $parcel['receiver']['name'] = $crypt->decrypt($receiver_name);
            }
            $logService            = \Hyperf\Support\make(OrderParcelLogService::class);
            $where['order_sys_sn'] = $parcel['order_sys_sn'];
            $parcel['log']         = $logService->LogOutput($where);
        } catch (\Exception $exception) {
            return $this->response->json(['code' => 201, 'msg' => '查询失败', 'data' => [
                'msg'  => $exception->getMessage(),
                'line' => $exception->getLine(),
                'file' => $exception->getFile(),
            ]]);
        }
        return $this->response->json(['code' => 200, 'msg' => '查询成功', 'data' => $parcel]);
    }

}
