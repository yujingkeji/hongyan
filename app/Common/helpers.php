<?php
declare(strict_types=1);

namespace App\Common;


use Hyperf\DbConnection\Db;
use Hyperf\Logger\LoggerFactory;

if (!function_exists('reform_keys')) {
    function reform_keys($array)
    {
        if (!is_array($array)) {
            return $array;
        }
        $keys = implode('', array_keys($array));
        if (is_numeric($keys)) {
            $array = array_values($array);
        }
        $array = array_map('reform_keys', $array);
        //框架中这么写，
        return $array;
    }
}

if (!function_exists('batchUpdateSql')) {
//批量更新Sql
    /**
     * @DOC
     * @Name   batchUpdateSql
     * @Author wangfei
     * @date   2023-08-12 2023
     * @param string $table
     * @param array $multipleData
     * @param array|string $primaryKeys
     * @return array|string
     */
    function batchUpdateSql(string $table, array $multipleData = [], array|string $primaryKeys = [])
    {
        try {
            if (empty($multipleData)) {
                return [];
            }

            $firstRow = current($multipleData);
            $fieldKey = array_keys($firstRow);
            // 如果 $primaryKeys 是字符串，则转换为数组
            if (is_string($primaryKeys)) {
                $primaryKeys = [$primaryKeys];
            }
            // 如果 $primaryKeys 为空，默认使用第一个字段作为主键
            if (empty($primaryKeys)) {
                $primaryKeys = [$fieldKey[0]];
            }

            // 检查主键是否在数据中
            foreach ($primaryKeys as $primaryKey) {
                if (!in_array($primaryKey, $fieldKey)) {
                    throw new \Exception("Primary key $primaryKey not found in data.");
                }
            }
            // 移除主键字段
            $updateFields = array_diff($fieldKey, $primaryKeys);

            $updateSql = "UPDATE `" . $table . "` SET ";
            $sets      = [];

            foreach ($updateFields as $field) {
                $nodeSql = "`" . $field . "` = (CASE ";
                foreach ($multipleData as $data) {
                    $conditions = [];
                    foreach ($primaryKeys as $primaryKey) {
                        $conditions[] = "`" . $primaryKey . "` = '" . addslashes((string)$data[$primaryKey]) . "'";
                    }
                    $nodeSql .= " WHEN " . implode(" AND ", $conditions) . " THEN '" . addslashes((string)$data[$field]) . "'";
                }
                $nodeSql .= " END)";
                $sets[]  = $nodeSql;
            }

            $updateSql .= implode(', ', $sets);

            $whereConditions = [];
            foreach ($primaryKeys as $primaryKey) {
                $values            = array_unique(array_column($multipleData, $primaryKey));
                $whereConditions[] = "`" . $primaryKey . "` IN ('" . implode("', '", $values) . "')";
            }

            return $updateSql . " WHERE " . implode(" AND ", $whereConditions);
        } catch (\Exception $e) {
            return [];
        }
    }
}

if (!function_exists('batchUpdateTemplate')) {
    /**
     * @DOC   : 批量更新Sql模板、采用预处理
     * @Name  : batchUpdateTemplate
     * @Author: wangfei
     * @date  : 2025-02 9:42
     * @param string $table
     * @param array $multipleData
     * @param array|string $primaryKeys
     * @return array
     *
     */
    function batchUpdateTemplate(string $table, array $multipleData = [], array|string $primaryKeys = []): array
    {
        try {
            if (empty($multipleData)) {
                return ['sql' => '', 'params' => []];
            }
            $firstRow = current($multipleData);
            $fieldKey = array_keys($firstRow);
            // 如果 $primaryKeys 是字符串，则转换为数组
            if (is_string($primaryKeys)) {
                $primaryKeys = [$primaryKeys];
            }
            // 如果 $primaryKeys 为空，默认使用第一个字段作为主键
            if (empty($primaryKeys)) {
                $primaryKeys = [$fieldKey[0]];
            }
            // 检查主键是否在数据中
            foreach ($primaryKeys as $primaryKey) {
                if (!in_array($primaryKey, $fieldKey)) {
                    throw new \Exception("Primary key $primaryKey not found in data.");
                }
            }
            // 移除主键字段
            $updateFields = array_diff($fieldKey, $primaryKeys);
            $setClauses   = [];
            $params       = [];
            foreach ($updateFields as $field) {
                $caseClauses = [];
                foreach ($multipleData as $data) {
                    $conditions    = buildConditions($primaryKeys, $data, $params);
                    $caseClauses[] = "WHEN " . implode(" AND ", $conditions) . " THEN ?";
                    $params[]      = $data[$field];
                }
                $setClauses[] = "`$field` = (CASE " . implode(" ", $caseClauses) . " ELSE NULL END)";
            }

            $whereConditions = [];
            foreach ($primaryKeys as $primaryKey) {
                $values            = array_unique(array_column($multipleData, $primaryKey));
                $placeholders      = implode(", ", array_fill(0, count($values), "?"));
                $whereConditions[] = "`$primaryKey` IN ($placeholders)";
                $params            = array_merge($params, $values);
            }
            $sql = "UPDATE `$table` SET " . implode(", ", $setClauses) . " WHERE " . implode(" AND ", $whereConditions);

            return ['sql' => $sql, 'params' => $params, 'code' => 200];
        } catch (\Exception $e) {
            return ['sql' => '', 'params' => [], 'code' => 201, 'error' => $e->getMessage()];
        }
    }

}

if (!function_exists('buildConditions')) {
    function buildConditions(array $primaryKeys, array $data, array &$params): array
    {
        $conditions = [];
        foreach ($primaryKeys as $primaryKey) {
            $conditions[] = "`$primaryKey` = ?";
            $params[]     = $data[$primaryKey];
        }
        return $conditions;
    }
}

/**
 * @DOC   :
 * @Name  : Send
 * @Author: wangfei
 * @date  : 2019/1/17 13:06
 * @param        $Url         请求地址
 * @param        $data        提交数据
 * @param string $requestType 请求类型
 * @return mixed
 *
 */
if (!function_exists('Send')) {
    function Send($Url, $data, $requestType = 'get', $header = [])
    {
        try {
            //初始化curl
            $ch = curl_init();
            //设置超时
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_URL, $Url);
            curl_setopt($ch, CURLOPT_SSLVERSION, 6);
            //curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            if (!empty($header)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            }
            if (strtolower($requestType) == 'post') {
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }

            $return = curl_exec($ch);
            curl_close($ch);

            return $return;
        } catch (\Exception $e) {
            $return['success'] = 'false';
            $return['code']    = $e->getCode();
            $return['msg']     = $e->getMessage();

            return $return;
        }

    }
}

/**
 *  解析XML数据
 */
if (!function_exists('LoadXml')) {
    function LoadXml($XmlBody)
    {
        $XmlBody = simplexml_load_string($XmlBody);
        $XmlBody = json_encode($XmlBody, JSON_UNESCAPED_UNICODE);
        $XmlBody = json_decode($XmlBody, true);
        return $XmlBody;
    }
}


if (!function_exists('Format')) {
    function Format($amount, $length = 2)
    {
        $amount = (float)$amount;
        return number_format($amount, $length, '.', '');
    }
}

/**
 * @DOC   :
 * @Name  : hasSort
 * @Author: wangfei
 * @date  : 2021-05-10 2021
 * @param $params
 * @return string
 */
if (!function_exists("hasSort")) {

    function hasSort($params)
    {
        ksort($params);
        $stringToBeSigned = '';
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                $v = hasSort($v); //递归调用
            }
            $stringToBeSigned .= "$k$v";
        }
        unset($k, $v);
        return $stringToBeSigned;
    }
}

