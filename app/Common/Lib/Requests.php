<?php
/**
 * 主要用途：进行远程请求
 */

namespace App\Common\Lib;

use App\Exception\HomeException;

class Requests
{

    /**
     * 判断是否是json
     * @param $string
     * @return bool
     */
    public function isJson($string)
    {
        if (is_array($string) || is_object($string)) return false;
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    /**
     * 通过curl进行请求
     * @param       $method
     * @param       $link
     * @param array $data
     * @return bool|string
     */
    private function curl($method, $link, &$data = array())
    {
        try {
            $method = strtoupper($method);
            $ch     = curl_init();
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_URL, $link);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 100);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($method == 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
            }
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                if ($this->isJson($data)) {
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        'Content-Type: application/json',
                    ));
                }
            }
            $result = curl_exec($ch);
            return $result;
        } catch (HomeException $e) {
            throw new HomeException($e->getMessage());
        }
    }


    /**
     * post请求方式
     * @param $link
     * @param $data
     * @return mixed
     */
    public function post($link, $data = array())
    {
        return $this->curl('post', $link, $data);
    }

    /**
     * get请求方式
     * @param $link
     * @return mixed
     */
    public function get($link)
    {
        return $this->curl('get', $link);
    }

    /**
     * put请求方式
     * @param $link
     * @param $data
     * @return mixed
     */
    public function put($link, $data = array())
    {
        return $this->curl('put', $link, $data);
    }

    /**
     * delete请求方式
     * @param $link
     * @param $data
     * @return mixed
     */
    public function delete($link, $data = array())
    {
        return $this->curl('delete', $link, $data);
    }
}
