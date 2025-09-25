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

class Arr extends \Hyperf\Collection\Arr
{
    /**
     * @DOC   :
     * @param array $item
     * @param string $sourceKey
     * @param string $targetKey
     * @return array
     *
     */
    public static function arrayKeyChange(array $item, string $sourceKey = 'item_sku_name', string $targetKey = 'text')
    {
        return array_map(function ($item) use ($sourceKey, $targetKey) {
            $item[$targetKey] = $item[$sourceKey];
            unset($item[$sourceKey]);
            return $item;
        }, $item);
    }

    /**
     * @DOC  一维数组安装某个字段转为二维数组
     * @Name   ArrayReduce
     * @Author wangfei
     * @date   2024/5/24 2024
     * @param array $singleArray 需要转成二维数组一维数组
     * @param string $key 字段
     * @return mixed
     */
    public static function ArrayReduce(array $singleArray, string $key)
    {
        return array_reduce($singleArray, function ($result, $item) use ($key) {
            if (isset($item[$key])) {
                $result[$item[$key]][] = $item;
            }
            return $result;
        });

    }

    /**
     * @DOC   :查找并返回当前数组重复值
     * @Name  : fetchRepeatInArray
     * @Author: wangfei
     * @date  : 2023-02-20 2023
     * @return array
     */
    public static function fetchRepeatInArray(array $array)
    {
        // 获取去掉重复数据的数组
        $unique_arr = array_unique($array);
        // 获取重复数据的数组
        return array_diff_assoc($array, $unique_arr);
    }

    /**
     * @DOC   : 数组排序并装成字符串
     * @Name  : hasSortString
     * @Author: wangfei
     * @date  : 2023-02-20 2023
     * @param array $data 数组
     * @return string
     */
    public static function hasSortString(array $data)
    {
        ksort($data);
        $stringToBeSigned = '';
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $v = self::hasSortString($v); // 递归调用
            }
            $stringToBeSigned .= "{$k}{$v}";
        }
        unset($k, $v);
        return $stringToBeSigned;
    }

    /**
     * @DOC   将数二维组合并一位数组 [[],[]]
     * @Name   flattenArray
     * @Author wangfei
     * @date   2023-09-06 2023
     * @param $array
     * @return mixed
     */
    public static function flattenArray($array)
    {
        return array_reduce($array, function ($carry, $item) {
            return array_merge($carry, $item);
        }, []);
    }

    /**
     * @DOC   :
     * @Name  : del
     * @Author: wangfei
     * @date  : 2022-04-15 2022
     * @param array $array //被删除的数据
     * @param       $value //需要删除的数据
     */
    public static function del(array &$array, $value)
    {
        $value = [$value];
        foreach ($array as $key => $val) {
            if (in_array($val, $value)) {
                unset($array[$key]);
            }
        }

        return $array;
    }

    /**
     * @DOC   : 删除数组中空值
     * @Name  : delEmpty
     * @Author: wangfei
     * @date  : 2023-02-17 2023
     * @return array;
     */
    public static function delEmpty(array $array)
    {
        foreach ($array as $k => $v) {
            if (empty($v)) {
                unset($array[$k]);
            } else {
                $array[$k] = trim((string)$v);
            }
        }
        return $array;
    }

    public static function hasArr( $array, $keys, bool $numeric = false): bool
    {
        // 空值快速失败
        if (empty($keys) || empty($array)) {
            return false;
        }
        $keys = (array)$keys;
        foreach ($keys as $key) {
            // 键存在性检查
            if (!array_key_exists($key, $array)) {
                return false;
            }

            $value = $array[$key];
            // 空字符串和NULL检查
            if (is_null($value) || $value === '') {
                return false;
            }
            // 严格模式检查
            if ($numeric) {
                if (!is_numeric($value) || $value === '') {
                    return false;
                }
            } else {
                if (empty($value) && $value !== 0 && $value !== '0') {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * @DOC   : 数组转Tree
     * @Name  : tree
     * @Author: wangfei
     * @date  : 2021-11-29 2021
     * @param string $pk
     * @param string $pidName
     * @param string $children
     * @return array
     */
    public static function tree($list, $pk = 'id', $pidName = 'pid', $children = 'children')
    {
        $list  = self::ArrayDesignKey($list, $pk);
        $trees = self::handleTree($list, $pk, $pidName, $children);
        unset($list);
        return $trees;
    }

    public static function ArrayDesignKey($array, $design_key)
    {
        $newData = [];
        foreach ($array as $key => $data) {
            if (is_array($design_key)) {
                $key = implode('-', array_map(function ($value) use ($data) {
                    return $data[$value];
                }, $design_key));
            } else {
                $key = $data[$design_key];
            }
            $newData[$key] = $data;
        }
        return $newData;
    }

    protected static function handleTree($list, $pk, $pidName = 'pid', $children = 'children')
    {
        $hasTree = array_column($list, null, $pk);
        $trees   = [];
        foreach ($list as $value) {
            if ($value[$pidName] != 0 && isset($hasTree[$value[$pidName]])) {
                $list[$value[$pidName]][$children][] = &$list[$value[$pk]];
            } else {

                $trees[] = &$list[$value[$pk]];
            }
        }
        return $trees;
    }

    /**
     * @DOC   : 数组重新排序
     * @Name  : reorder
     * @Author: wangfei
     * @date  : 2021-11-20 2021
     * @param array $source 需要排序的数组
     * @param string $key 根据排序的字段
     * @param string $order SORT_ASC：升序，SORT_DESC:倒序
     * @throws Exception
     */
    public static function reorder(array $source, string $key, string $order = 'SORT_DESC'): array
    {
        if (!in_array($order, ['SORT_DESC', 'SORT_ASC'])) {
            throw new Exception('order in [SORT_DESC,SORT_ASC]');
        }
        $sort = array_column($source, $key);

        if (empty($order) || $order == 'SORT_DESC') {
            array_multisort($sort, SORT_DESC, $source);
        } else {
            array_multisort($sort, SORT_ASC, $source);
        }
        return $source;
    }

    /**
     * @DOC   : 获取数组的前 几位值
     * @Name  : arrToBefore
     * @Author: wangfei
     * @date  : 2021-11-20 2021
     * @param array $array 需要截取的数组
     * @param int $Before 获取数组前几位 只要大于等于1
     */
    public static function arrToBefore(array $array, int $Before = 1): array
    {
        if (count($array) == 0) {
            return [];
        }
        $n      = 0;
        $result = [];
        foreach ($array as $key => $val) {
            ++$n;
            if ($n <= $Before) {
                array_push($result, $val);
            }
        }
        unset($array, $Before);
        return $result;
    }

    /**
     * @DOC   : 向toArray 追加数组 $addArr
     * @Name  : pushArr
     * @Author: wangfei
     * @date  : 2022-01-19 2022
     * @param array $addArr 被追加的数组
     * @param array $toArray //目标数组
     * @return array
     */
    public static function pushArr(array $addArr, array $toArray)
    {
        array_walk($toArray, function (&$value, $key, $addArr) {
            $value = array_merge($value, $addArr);
        }, $addArr);
        return $toArray;
    }

    /**
     * @DOC   : 检测数组是不是全部是数字类型
     * @Name  : checkNumber
     * @Author: wangfei
     * @date  : 2022-01-22 2022
     * @return bool
     */
    public static function checkNumber(array $toArray)
    {
        try {
            array_walk($toArray, function ($value, $key) {
                if (!Str::number($value)) {
                    throw new \Exception('非数字类型');
                }
            });
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }


    /**
     * @DOC 处理新的商品分类
     * @Name   handleGoodsCategory
     * @Author wangfei
     * @date   2024/3/1 2024
     * @param array $GoodsCategoryCache
     * @param int $cfgItemId
     * @param array $resultData
     * @return array
     */
    public static function handleGoodsCategory(array $GoodsCategoryCache, int $cfgItemId, array $resultData = [])
    {

        $result = [
            'data'   => [],
            'cfg'    => '',
            'string' => ''
        ];
        try {
            foreach ($GoodsCategoryCache as $key => $item) {
                if ($item['id'] == $cfgItemId) {
                    array_unshift($resultData, $item);
                    if (isset($item['parent_id']) && $item['parent_id'] > 0) {
                        return self::handleGoodsCategory($GoodsCategoryCache, $item['parent_id'], $resultData);
                    }
                }
            }
            $result['data']   = $resultData;
            $result['cfg']    = implode(' -> ', array_column($resultData, 'id'));
            $result['string'] = implode(' -> ', array_column($resultData, 'goods_name'));
        } catch (\Throwable $e) {
        }
        return $result;
    }

    # 对象转数组

    /**
     * 将对象彻底转换为数组
     *
     * @param mixed $object
     * @return mixed
     */
    public static function objectToArray($object)
    {
        if (!is_object($object) && !is_array($object)) {
            return $object;
        }
        return array_map([self::class, 'objectToArray'], (array)$object);
    }

    /**
     * @DOC   : 数组分组、根据数组的键值相同的，分割成不同的集合
     * @Name  : groupBy
     * @Author: wangfei
     * @date  : 2025-02 13:23
     * @param array $data 数组
     * @param string $field
     * @return mixed
     *
     */
    public static function groupBy(array $data, string $field): mixed
    {
        return array_reduce($data, function ($carry, $item) use ($field) {
            if (!isset($item[$field])) {
                throw new \Exception("Field '$field' does not exist in item.");
            }
            $key = $item[$field];
            if (!isset($carry[$key])) {
                $carry[$key] = [];
            }
            $carry[$key][] = $item;
            return $carry;
        }, []);
    }
}
