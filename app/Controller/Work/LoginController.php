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

namespace App\Controller\Work;

use App\Common\Lib\Arr;
use App\Common\Lib\Captcha;
use App\Common\Lib\JWTAuth;
use App\Exception\HomeException;
use App\Request\LibValidation;
use App\Service\BlService;
use App\Service\Cache\BaseCacheService;
use App\Service\LoginService;
use App\Service\ParcelService;
use Hoa\Exception\Exception;
use Hyperf\Context\Context;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\Rule;


#[Controller(prefix: "/", server: 'httpWork')]
class LoginController
{
    #[Inject]
    protected BaseCacheService $baseCacheService;
    #[Inject]
    protected ResponseInterface $response;

    #[RequestMapping(path: 'login/login', methods: 'post')]
    public function login(RequestInterface $request)
    {
        $result['code']    = 200;
        $result['msg']     = '查询成功';
        $host              = $request->getHeaderLine('host');
        $origin            = $request->getHeaderLine('Refererwork');
        $params            = $request->all();
        $params['referer'] = $origin ?? ($host ?? 'www.yfd.cn');
        $Captcha           = \Hyperf\Support\make(Captcha::class);
        $LibValidation     = \Hyperf\Support\make(LibValidation::class);
        $params            = $LibValidation->validate($params,
            [
                'code'      => 'required|min:4',
                'verify_id' => 'required|string',
                'password'  => 'required|string',
                'username'  => 'required|string',
                'referer'   => 'required|string'
            ]);
        if (Arr::hasArr($params, 'verify_id')) {
            if (!$Captcha->check($params['verify_id'], $params['code'])) {
                throw new HomeException('验证码不正确或者过期、请重新填写');
            }
        }
        $loginService = \Hyperf\Support\make(LoginService::class);
        $loginResult  = $loginService->check($params);
        if ($loginResult['code'] == 200) {
            switch ($loginResult['role_id']) {
                case 1:
                case 2:
                case 3:
                case 10: // 仓库管理员
                    $data['token']  = $loginResult['token'];
                    $result['data'] = $data;
                    break;
                default:
                    throw new HomeException('非平台代理/加盟商/仓库管理员、禁止登录');
                    break;
            }
        } else {
            $result = array_merge($result, $loginResult);
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
    #[RequestMapping(path: 'login/verify', methods: 'post')]
    public function verify(RequestInterface $request)
    {
        $Captcha        = \Hyperf\Support\make(Captcha::class);
        $result['code'] = 200;
        $result['msg']  = '查询成功';
        $result['data'] = $Captcha->verify();
        return $this->response->json($result);
    }

}
