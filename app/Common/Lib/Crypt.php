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

namespace App\Common\Lib;

use App\JsonRpc\CryptServiceInterface;

class Crypt
{
    protected $encryptMethod = 'aes-256-cbc';

    protected $passwordEnd = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9Hl}U|rW52gHL:E0V;M$K%|E&WP2Z$7CasA';

    protected $cryptKey = ''; // 加解密key
    protected string $balanceEncryptor = 'D41D8CD98F00B204E9800998ECF8427E'; // 余额加密 key=balance md5 32 大写

    /**
     * @DOC   : 查看支持加密类型
     * @Name  : sslList
     * @return array
     */
    public function sslList()
    {
        return openssl_get_cipher_methods();
    }

    /**
     * @Note: 加密
     * @return string
     */
    public function encrypt(string $decrypt_str, string $passwordEnd = ''): string
    {
        if (empty($decrypt_str)) {
            throw new \Exception('禁止加密空字符串');
        }
        $passwordKey = $this->randomStr($passwordEnd);
        // 生成IV
        $iv = md5($passwordKey, true);
        // 加密
        return openssl_encrypt($decrypt_str, $this->encryptMethod, $passwordKey, 0, $iv);
    }

    /**
     * @Note: 解密
     * @param $encrypt_str : 加密字符串
     * @param $passwordEnd : 加密字符串
     * @return string
     * @throws Exception
     */
    public function decrypt(string $encrypt_str, string $passwordEnd = ''): string
    {
        if (empty($encrypt_str)) {
            return $encrypt_str;
        }
        $passwordKey = $this->randomStr($passwordEnd);
        // 生成IV
        $iv = md5($passwordKey, true);
        // 解密
        $decrypted = openssl_decrypt($encrypt_str, $this->encryptMethod, $passwordKey, 0, $iv);
        if (empty($decrypted)) {
            return '解密失败:' . base64_encode($encrypt_str);
            // throw new \Exception('解密失败:' . $encrypt_str);
        }
        return $decrypted;
    }

    protected function randomStr(string $passwordEnd = '')
    {
        $this->cryptKey = empty($passwordEnd) ? $this->passwordEnd : $passwordEnd;
        return $this->cryptKey;
    }

    /**
     * @DOC 余额加密
     */
    public function balanceEncrypt(float $balance): string
    {
        try {
            $cryptService = \Hyperf\Support\make(CryptServiceInterface::class);
            $encrypt      = $cryptService->encrypt((string)$balance);
            if (!empty($encrypt['text_encrypt'])) {
                return $encrypt['text_encrypt'];
            }
        } catch (\Exception $e) {
            echo $e->getMessage() . $e->getFile() . $e->getFile() . PHP_EOL;
        }
        return '';
    }

    /**
     * @DOC 解密用户的余额
     */
    public function balanceDecrypt(string $encryptedBalance)
    {
        try {
            $cryptService = \Hyperf\Support\make(CryptServiceInterface::class);
            $decrypt      = $cryptService->decrypt($encryptedBalance);
            if (!empty($decrypt['text_decrypt'])) {
                return $decrypt['text_decrypt'];
            }
        } catch (\Exception $e) {
            echo $e->getMessage() . $e->getFile() . $e->getFile() . PHP_EOL;
        }
        return '';
    }


}
