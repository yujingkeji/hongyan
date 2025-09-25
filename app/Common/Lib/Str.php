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

class Str extends \Hyperf\Stringable\Str
{
    /**
     * @DOC   : 分割字符串
     * @Name  : splitCharts
     * @Author: wangfei
     * @date  : 2022-06-24 2022
     * @param int $length 分割的长度
     * @param string $character 分割字符串
     * @param string $otherSplit 是否开启尾号其他分隔符
     * @param string $other 尾号分割字符串
     */
    public static function splitCharts(string $chars, int $length = 2, string $character = '.', $otherSplit = false, string $other = ' ')
    {
        $vLh  = self::length($chars);
        $sp   = ceil($vLh / $length);
        $vStr = '';
        for ($i = 0; $i < $vLh; ++$i) {
            $str = mb_substr($chars, $i, 1);
            $str = trim($str);
            if ($i > 0 && $i % $length == 0) {
                $vStr .= (($sp - 1) * $length == $i && ($vLh % $length != 0) && $otherSplit) ? $other : $character;
            }
            $vStr .= $str;
        }
        unset($vLh, $sp, $str, $other, $chars, $length, $character, $otherSplit);
        return $vStr;
    }

    /**
     * @DOC   :
     * @Name  : beforeStar
     * @Author: wangfei
     * @date  : 2022-02-17 2022
     */
    public static function beforeStar($str)
    {
        if (empty($str)) {
            return '';
        }
        $length = self::length($str);
        if ($length <= 1) {
            return '*';
        }
        $num = ceil($length / 2);
        $end = self::substr($str, (int)($length - $num), (int)$num);
        return str_pad('', self::length($str) - self::length($end), '*', 2) . $end;
    }

    /**
     * @DOC   :中级加*
     * @Name  : centerStar
     * @Author: wangfei
     * @date  : 2022-02-17 2022
     */
    public static function centerStar($str)
    {
        if (empty($str)) {
            return '';
        }
        $length = self::length($str);
        if ($length <= 1) {
            return '*';
        }
        switch ($length) {
            case 2:
                return self::beforeStar($str, 1);
                break;
            case 3:
            case 4:
                $before = self::substr($str, 0, 1);
                $end    = self::substr($str, (int)($length - 1), 1);
                return $before . str_pad('', (int)($length - 2), '*', 2) . $end;
                break;
            case 18: // 身份证脱敏
                $prefix = substr($str, 0, 4);
                $suffix = substr($str, -4);
                $masked = str_repeat('*', strlen($str) - 8);
                return $prefix . $masked . $suffix;
                break;
            default:
                $num    = ceil($length / 3);
                $before = self::substr($str, 0, (int)($num - 1));
                $end    = self::substr($str, (int)($length - $num), (int)$num);
                return $before . str_pad('', (int)($length - ($num * 2 - 1)), '*', 2) . $end;
        }
    }

    /**
     * @DOC   : 尾部加星
     * @Name  : endStar
     * @Author: wangfei
     * @date  : 2022-02-17 2022
     * @return string
     */
    public static function endStar($str)
    {
        if (empty($str)) {
            return '';
        }
        $length = self::length($str);
        if ($length <= 1) {
            return '*';
        }
        $num    = ceil($length / 2);
        $before = self::substr($str, 0, $num);
        return $before . str_pad('', self::length($str) - self::length($before), '*', 2);
    }

    /**
     * @DOC 字符串替换 * 号
     */
    public static function starReplace($str)
    {
        if (empty($str)) {
            return '';
        }
        return preg_replace('/\d+/', '*', $str);
    }

    /**
     * @DOC   : 判断奇偶数
     * @Name  : checkOddEven
     * @Author: wangfei
     * @date  : 2021-04-28 2021
     * @return string
     */
    public static function checkOddEven($number)
    {
        return ((int)$number % 2) ? false : true;
    }

    /**
     * @DOC   : 判断是否为全英文
     * @Name  : isEn
     * @Author: wangfei
     * @date  : 2021-11-10 2021
     * @return false|int
     */
    public static function isEn($str)
    {
        $str = trim($str, ' ');
        return preg_match('/^[A-za-z]+$/', $str);
    }

    public static function stringType($str)
    {
        if (is_numeric($str) == true) {
            return 'Number';
        }
        if (self::integer($str) == true && Str::length($str) <= 20) {
            return 'Integer';
        }
        if (self::float($str) == true) {
            return 'Boolean';
        }
        if (is_string($str) == true) {
            return 'String';
        }
        if (is_array($str) == true) {
            return 'Array';
        }
        if (is_object($str) == true) {
            return 'Object';
        }
        if (is_double($str) == true) {
            return 'Double';
        }
    }

    /**
     * @DOC   : 判断是否是数字
     * @Name  : number
     * @Author: wangfei
     * @date  : 2022-01-05 2022
     * @return bool
     */
    public static function number($str)
    {
        return ctype_digit((string)$str);
    }

    /**
     * @DOC   :
     * @Name  : decimal
     * @Author: wangfei
     * @date  : 2022-02-12 2022
     * @return string
     */
    public static function decimal($decimal, int $length = 2)
    {
        $decimal = round($decimal, $length);
        $decimal = (string)$decimal;
        $strpos  = mb_strpos($decimal, '.');
        $Before  = self::substr($decimal, 0, $strpos);
        $End     = self::substr($decimal, $strpos + 1, self::length($decimal) - $strpos);
        $End     = str_pad($End, $length, '0', 1);
        return $Before . '.' . $End;
    }

    /**
     * @DOC   : 是否是一个有效日期
     * @Name  : date
     * @Author: wangfei
     * @date  : 2022-01-19 2022
     * @return bool
     */
    public static function date($str)
    {
        return strtotime($str) !== false;
    }

    /**
     * @DOC   : 检测是否是文件
     * @Name  : file
     * @Author: wangfei
     * @date  : 2022-01-19 2022
     * @return bool
     */
    public static function file($str)
    {
        return $str instanceof File;
    }

    /**
     * @DOC   : 验证是否是浮点型
     * @Name  : float
     * @Author: wangfei
     * @date  : 2022-01-05 2022
     * @return bool
     */
    public static function float($str)
    {       // self::filter($str, FILTER_VALIDATE_FLOAT);
        return in_array($str, [true, false, 0, 1, '0', '1'], true);
    }

    public static function email($str)
    {
        return self::filter($str, FILTER_VALIDATE_EMAIL);
    }

    public static function ip($str)
    {
        return self::filter($str, [FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6]);
    }

    public static function integer($str)
    {
        return self::filter($str, FILTER_VALIDATE_INT);
    }

    public static function url($str)
    {
        return self::filter($str, FILTER_VALIDATE_URL);
    }

    /**
     * @DOC   : 日期格式
     * @Name  : dateFormat
     * @Author: wangfei
     * @date  : 2022-01-19 2022
     */
    public static function dateFormat($str, $rule): bool
    {
        $info = date_parse_from_format($rule, $str);
        return $info['warning_count'] == 0 && $info['error_count'] == 0;
    }

    /**
     * @DOC   : 验证规则
     * @Name  : filter
     * @Author: wangfei
     * @date  : 2022-01-05 2022
     */
    public static function filter($value, $rule): bool
    {
        if (is_string($rule) && strpos($rule, ',')) {
            [$rule, $param] = explode(',', $rule);
        } elseif (is_array($rule)) {
            $param = $rule[1] ?? 0;
            $rule  = $rule[0];
        } else {
            $param = 0;
        }

        return filter_var($value, is_int($rule) ? $rule : filter_id($rule), $param) !== false;
    }

    #随机字符串

    /**
     * @DOC   :
     * @Name  : generate_random_string
     * @Author: wangfei
     * @date  : 2025-02 14:53
     * @param int $length 生成字符串的长度（默认12）
     * @param bool $use_lower 是否包含小写字母（默认true）
     * @param bool $use_upper 是否包含大写字母（默认true）
     * @param bool $use_numbers 是否包含数字（默认true）
     * @param bool $use_symbols 是否包含特殊符号（默认false）
     * @return string
     * * @throws \Random\RandomException
     */
    public static function generate_random_string(int $length = 12, bool $use_lower = true, bool $use_upper = true, bool $use_numbers = true, bool $use_symbols = false): string
    {
        if ($length <= 0) {
            throw new \Exception('字符串长度必须为正整数');
        }

        // 定义字符池
        $lower   = 'abcdefghijklmnopqrstuvwxyz';
        $upper   = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '0123456789';
        $symbols = '!@#$%^&*()-_=+[]{}|;:,.<>?';

        // 构建字符池
        $characters = '';
        if ($use_lower) $characters .= $lower;
        if ($use_upper) $characters .= $upper;
        if ($use_numbers) $characters .= $numbers;
        if ($use_symbols) $characters .= $symbols;

        // 验证字符池
        if ($characters === '') {
            throw new \Exception('至少需要启用一种字符类型');
        }
        // 生成随机字符串
        $str = '';
        $max = strlen($characters) - 1;
        for ($i = 0; $i < $length; $i++) {
            $str .= $characters[random_int(0, $max)];
        }
        return $str;
    }
}
