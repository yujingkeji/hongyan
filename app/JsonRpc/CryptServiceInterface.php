<?php

namespace App\JsonRpc;


interface CryptServiceInterface
{
    //加密
    public function encrypt($text);

    //解密
    public function decrypt($text);

    public function rsaEncrypt(string|array $text);

    public function rsaDecrypt(string|array $text);
}
