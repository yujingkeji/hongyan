<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Controller\Home\Member;

use App\Common\Lib\Arr;
use App\Common\Lib\Captcha;
use App\Common\Lib\UploadAliOssSev;
use App\Exception\HomeException;
use App\JsonRpc\BaseServiceInterface;
use App\Model\AgentPlatformModel;
use App\Request\LibValidation;
use App\Service\Cache\BaseCacheService;
use App\Service\LoginService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;


#[Controller(prefix: 'member')]
class LoginController
{
    #[Inject]
    protected BaseCacheService $baseCacheService;
    #[Inject]
    protected ResponseInterface $response;

    #[RequestMapping(path: 'login', methods: 'post')]
    public function login(RequestInterface $request)
    {
        $result['code'] = 200;
        $result['msg']  = '登录成功';
        $origin         = $request->getHeaderLine('Referer');

        $params = $request->all();
        if (empty($origin)) {
            throw new HomeException('非法的来源域名');
        }
        $params['referer'] = $origin;
        // $Captcha           = \Hyperf\Support\make(Captcha::class);
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $params        = $LibValidation->validate($params,
            [
                #'code'     => 'required|min:4',
                // 'verify_id' => 'required|string',
                #'token'    => 'required|string',
                #'point'    => 'required|string',
                'password' => 'required|string',
                'username' => 'required|string',
                'referer'  => 'required|string'
            ], [
                #'code.required'     => '验证码不能为空',
                #'code.min'          => '验证码不能少于4位',
                'token.required'    => '验证码不能为空',
                'token.string'      => '验证码格式不正确',
                'point.required'    => '验证码格式不正确',
                'point.string'      => '验证码格式不正确',
                'password.required' => '密码不能为空',
                'password.string'   => '密码格式不正确',
                'username.required' => '用户名不能为空',
                'username.string'   => '用户名格式不正确',
                'referer.required'  => '来源域名不能为空',
                'referer.string'    => '来源域名格式不正确',
            ]);
        /*  if (Arr::hasArr($params, 'verify_id')) {
              if (!$Captcha->check($params['verify_id'], $params['code'])) {
                  //  throw new HomeException('验证码不正确或者过期、请重新填写');
              }
          }*/

        /*$resultCaptcha = make(BaseServiceInterface::class)->captchaCheck($params);
        if ($resultCaptcha['code'] != 200) {
            throw new HomeException($resultCaptcha['repMsg']);
        }*/
        $loginServie = \Hyperf\Support\make(LoginService::class);
        $loginResult = $loginServie->check($params);
        if ($loginResult['code'] == 200) {
            $result['data'] = [
                'role_id'      => $loginResult['role_id'],
                'token'        => $loginResult['token'],
                'agent_status' => (in_array($loginResult['role_id'], [3, 4, 5])) ? $loginResult['data']['agent_status'] : 1,

            ];
        } else {
            $result['code'] = 201;
            $result['msg']  = $loginResult['msg'];
        }

        return $this->response->json($result);
    }

    /**
     * @DOC  生成验证码
     * @Name   verify
     * @Author wangfei
     * @date   2023-09-18 2023
     * @param RequestInterface $request
     * @return mixed
     */
    #[RequestMapping(path: 'login/verify', methods: 'get,post')]
    public function verify(RequestInterface $request)
    {
        /* $Captcha                     = \Hyperf\Support\make(Captcha::class);
         $result['code']              = 200;
         $result['msg']               = '查询成功';
         $res                         = $Captcha->verify();
         $result['data']['imgUrl']    = $res['verify_src'];
         $result['data']['verify_id'] = $res['verify_id'];*/

        $result = make(BaseServiceInterface::class)->captcha();
        return $this->response->json($result);
    }

    /**
     * @DOC 获取各个代理的信息
     */
    #[RequestMapping(path: 'login/platform', methods: 'get,post')]
    public function platform(RequestInterface $request)
    {
        $origin = $request->getHeaderLine('Referer');
        if (empty($origin)) {
            return $this->response->json(['code' => 200, 'msg' => '错误：非法的来源', 'data' => []]);
        }
        $origin   = parse_url($origin)['host'];
        $origin   = md5($origin);
        $platform = AgentPlatformModel::where('web_domain_md5', $origin)
            ->select(['agent_platform_uid', 'web_name', 'web_logo as logo'])
            ->first();
        if (!$platform) {
            return $this->response->json(['code' => 200, 'msg' => '错误：非法的来源', 'data' => []]);
        }
        $platform = $platform->toArray();
        if ($platform['logo'] == '') {
            return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $platform]);
        }
        list($ret, $config) = (new UploadController)->configuration($platform['agent_platform_uid']);

        if (!$ret) {
            return $this->response->json(['code' => 200, 'msg' => '获取失败', 'data' => $platform]);
        }
        $Upload           = new UploadAliOssSev($config);
        $img_url          = $Upload->config['Host'] . '/' . $platform['logo'];
        $platform['logo'] = $Upload->signUrl($img_url);

        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $platform]);
    }


}
