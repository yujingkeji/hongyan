<?php
/**
 * 备案处理
 * *****************************************************************
 * 这不是一个自由软件,谢绝修改再发布.
 * @Created by PhpStorm.
 * @Name    :   Auth.php
 * @Email   :   28386631@qq.com
 * @Author  :   wangfei
 * @Date    :   2023-04-17 11:24
 * @Link    :   http://ServPHP.LinkUrl.cn
 * *****************************************************************
 */

namespace App\Controller\Home\Orders;


use App\Common\Lib\Arr;
use App\Exception\HomeException;
use App\Model\OrderExceptionItemModel;
use App\Service\AuthWayService;
use App\Service\Cache\BaseCacheService;
use App\Service\OrderAuthService;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\Rule;


#[Controller(prefix: 'orders/auth')]
class AuthController extends OrderBaseController
{
    protected $pictureCode = ['passport_front', 'identity_front', 'identity_back'];//这些字段必须上传照片
    #[Inject]
    protected ValidatorFactoryInterface $validationFactory;

    #[Inject]
    protected BaseCacheService $baseCacheService;

    //【hyperf】提交认证

    #[RequestMapping(path: 'apply', methods: 'get,post')]
    public function apply(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '绑定失败';
        $member         = $request->UserInfo;
        $params         = $request->all();
        // 提交认证
        $result = (new AuthWayService())->apply($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC  整理收件人表更新内容
     * @Name   handleOrderReceiver
     * @Author wangfei
     * @date   2023-09-09 2023
     * @param $paramsElement
     * @param $authResult
     * @param $authWayEelementCodeCache
     * @return array
     * @throws \Exception
     */
    protected function handleOrderReceiver($paramsElement, $authResult, $authWayEelementCodeCache)
    {
        $order_receiver['auth_status'] = 22103;
        $order_receiver['auth_msg']    = $authResult['msg'];
        //身份证号码
        if (Arr::hasArr($paramsElement, 'identity_code') && in_array('identity_code', $authWayEelementCodeCache)) {
            $order_receiver['identity_code'] = $paramsElement['identity_code'];
        }
        //身份证正面
        if (Arr::hasArr($paramsElement, 'identity_front') && in_array('identity_front', $authWayEelementCodeCache)) {
            $order_receiver['identity_front'] = $paramsElement['identity_front'];
        }
        //身份证反面
        if (Arr::hasArr($paramsElement, 'identity_back') && in_array('identity_back', $authWayEelementCodeCache)) {
            $order_receiver['identity_back'] = $paramsElement['identity_back'];
        }
        //通关编码
        if (Arr::hasArr($paramsElement, 'customs_personal_code') && in_array('customs_personal_code', $authWayEelementCodeCache)) {
            $order_receiver['customs_personal_code'] = $paramsElement['customs_personal_code'];
        }
        //护照号码
        if (Arr::hasArr($paramsElement, 'passport_code') && in_array('passport_code', $authWayEelementCodeCache)) {
            $order_receiver['passport_code'] = $paramsElement['passport_code'];
        }
        //护照正面
        if (Arr::hasArr($paramsElement, 'passport_front') && in_array('passport_front', $authWayEelementCodeCache)) {
            $order_receiver['passport_front'] = $paramsElement['passport_front'];
        }
        //告知书地址
        if (Arr::hasArr($paramsElement, 'notice_book') && in_array('notice_book', $authWayEelementCodeCache)) {
            $order_receiver['notice_book'] = $paramsElement['notice_book'];
        }

        //TODO 认证通过 才可以修改 姓名与手机号码
        if (Arr::hasArr($authResult, 'code') && $authResult['code'] == 200) {
            $order_receiver['auth_status'] = 22104;
            $order_receiver['auth_msg']    = $authResult['msg'];
            //收件人姓名
            if (Arr::hasArr($paramsElement, 'name') && in_array('name', $authWayEelementCodeCache)) {
                $order_receiver['name'] = base64_encode($this->crypt->encrypt($paramsElement['name']));
            }
            //手机号码验证
            if (Arr::hasArr($paramsElement, 'phone') && in_array('phone', $authWayEelementCodeCache)) {
                $order_receiver['phone'] = base64_encode($this->crypt->encrypt($paramsElement['phone']));
            }
        }
        return $order_receiver;
    }

}
