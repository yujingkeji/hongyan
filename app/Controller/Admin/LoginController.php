<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Common\Lib\Arr;
use App\Common\Lib\Captcha;
use App\Exception\HomeException;
use App\Request\LibValidation;
use App\Service\LoginService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: '/', server: 'httpAdmin')]
class LoginController extends AdminBaseController
{
    /**
     * @DOC 后台登录
     */
    #[RequestMapping(path: 'login/login', methods: 'post')]
    public function login(RequestInterface $request): ResponseInterface
    {
        $params        = $request->all();
        $Captcha       = \Hyperf\Support\make(Captcha::class);
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $params        = $LibValidation->validate($params,
            [
                'username'  => 'required|min:3|max:25',
                'password'  => 'required|min:8|max:25',
                'code'      => 'required|min:4',
                'verify_id' => 'required|min:10',
            ], [
                'code.required'     => '请填写验证码',
                'code.min'          => '验证码长度不符合标准',
                'password.min'      => '密码长度不能小于8个字符',
                'password.max'      => '密码长度不能超过25个字符',
                'username.required' => '请填写账号',
                'username.min'      => '密码长度不能小于4个字符',
                'username.max'      => '密码长度不能超过25个字符',
            ]);
        if (Arr::hasArr($params, 'verify_id')) {
            if (!$Captcha->check($params['verify_id'], $params['code'])) {
               // throw new HomeException('验证码不正确或者过期、请重新填写');
            }
        }
        $loginService = \Hyperf\Support\make(LoginService::class);
        $token        = $loginService->adminCheck($params);
        return $this->response->json([
            'code' => 200,
            'msg'  => '登录成功',
            'data' => [
                'token' => $token
            ]
        ]);

    }

    /**
     * @DOC 获取验证码
     */
    #[RequestMapping(path: 'login/verify', methods: 'get,post')]
    public function verify(): ResponseInterface
    {
        $Captcha = \Hyperf\Support\make(Captcha::class);
        $result  = $Captcha->verify();
        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => ['imgUrl' => $result['verify_src'], 'verify_id' => $result['verify_id']]
        ]);
    }
}
