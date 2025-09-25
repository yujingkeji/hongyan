<?php

namespace App\Common\Lib;

/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

use App\Exception\HomeException;
use Exception;
use Hyperf\Di\Annotation\Inject;

class JWTAuth
{
    #[Inject]
    protected Crypt $Crypt;

    protected $group =
        [
            'home'  => 'kArESDa2EGjhmkz54hnFmJrzYzDS0rE7NA4wGetPJRzkSd9wFepwaTwwM4ME',
            'admin' => 'XTrFSC6xiTBn0Z96FAE37mEDJWD3k8FiGT3MY2swFEN0m9cc1niYz6YFXNbP',
        ];

    /**
     * @DOC   :
     * @Name  : builder
     * @return string
     * @throws Exception
     */
    public function builder(array $params, string $groupName = 'home')
    {
        try {
            $paramsString = json_encode($params);
            $JWT          = $this->Crypt->encrypt($paramsString, $this->group[$groupName]);
            return base64_encode($JWT);
        } catch (HomeException $e) {
            throw new HomeException($e->getMessage());
        }
    }

    /**
     * @DOC   :
     * @Name  : auth
     * @return mixed
     * @throws Exception
     */
    public function auth($JWT, string $groupName = 'home')
    {
        $JWT   = base64_decode($JWT);
        if (false === $JWT) {
            throw new HomeException('JWT解码失败', 401);
        }
        $crypt = new Crypt();
        $JWT   = $crypt->decrypt($JWT, $this->group[$groupName]);
        $JWT   = json_decode($JWT, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new HomeException('JWT格式错误: ' . json_last_error_msg(), 401);
        }
        // 判断登录是否过期
        if (!is_numeric($JWT['end_time']) || $JWT['end_time'] <= time()) {
            throw new HomeException('登录过期', 401);
        }
        return $JWT;

    }
}
