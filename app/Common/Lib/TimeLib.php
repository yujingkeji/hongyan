<?php

namespace App\Common\Lib;
class TimeLib
{

    /**
     * @DOC   : 时间格式
     * @Name  : format
     * @Author: wangfei
     * @date  : 2022-06-23 2022
     * @param $time
     * @param string $fomat
     */
    public static function format($time, string $fomat = 'Y-m-d H:i:s')
    {
        if (!Str::number($time)) {
            $time = strtotime($time);
        }
        return date($fomat, $time);
    }

    /**
     * 返回今日开始和结束的时间戳
     *
     * @return array
     */
    public static function today()
    {
        list($y, $m, $d) = explode('-', date('Y-m-d'));
        return [
            'start' => mktime(0, 0, 0, $m, $d, $y),
            'end'   => mktime(23, 59, 59, $m, $d, $y),
        ];
    }

    /**
     * 返回昨日开始和结束的时间戳
     *
     * @return array
     */
    public static function yesterday()
    {
        $yesterday = date('d') - 1;
        return [
            mktime(0, 0, 0, date('m'), $yesterday, date('Y')),
            mktime(23, 59, 59, date('m'), $yesterday, date('Y')),
        ];
    }

    /**
     * 返回本周开始和结束的时间戳
     *
     * @return array
     */
    public static function week()
    {
        list($y, $m, $d, $w) = explode('-', date('Y-m-d-w'));
        if ($w == 0) $w = 7; //修正周日的问题
        return [
            mktime(0, 0, 0, $m, $d - $w + 1, $y), mktime(23, 59, 59, $m, $d - $w + 7, $y),
        ];
    }

    /**
     * 返回上周开始和结束的时间戳
     *
     * @return array
     */
    public static function lastWeek()
    {
        $timestamp = time();
        return [
            strtotime(date('Y-m-d', strtotime("last week Monday", $timestamp))),
            strtotime(date('Y-m-d', strtotime("last week Sunday", $timestamp))) + 24 * 3600 - 1,
        ];
    }

    /**
     * @DOC
     * @Name   month
     * @Author wangfei
     * @date   2023/11/14 2023
     * @param string $day Y-m-d  若为空返回当前月份的 开始和结束时间，若不为空返回指定年月的开始和节数时间
     * @return array
     */
    public static function month()
    {
        list($y, $m, $t) = explode('-', date('Y-m-t'));
        return [
            mktime(0, 0, 0, $m, 1, $y),
            mktime(23, 59, 59, $m, $t, $y),
        ];
    }

    /**
     * 返回上个月开始和结束的时间戳
     * 若填写日期，返回当前月份的第一天和最后一天
     * @return array
     */
    public static function lastMonth(int $month = 0, int $year = 0)
    {
        $y     = ($year > 0) ? $year : date('Y');
        $m     = ($month > 0) ? $month : (date('m') - 1);
        $begin = mktime(0, 0, 0, $m, 1, $y);
        $end   = mktime(23, 59, 59, $m, date('t', $begin), $y);
        return [$begin, $end];
    }


    /**
     * 返回今年开始和结束的时间戳
     *
     * @return array
     */
    public static function year()
    {
        $y = date('Y');
        return [
            mktime(0, 0, 0, 1, 1, $y),
            mktime(23, 59, 59, 12, 31, $y),
        ];
    }

    /**
     * 返回去年开始和结束的时间戳
     *
     * @return array
     */
    public static function lastYear()
    {
        $year = date('Y') - 1;
        return [
            mktime(0, 0, 0, 1, 1, $year),
            mktime(23, 59, 59, 12, 31, $year),
        ];
    }

    /**
     * 获取几天前零点到现在/昨日结束的时间戳
     *
     * @param int $day 天数
     * @param bool $now 返回现在或者昨天结束时间戳
     * @return array
     */
    public static function dayToNow($day = 1, $now = true)
    {
        $end = time();
        if (!$now) {
            list($foo, $end) = self::yesterday();
        }

        return [
            mktime(0, 0, 0, date('m'), date('d') - $day, date('Y')),
            $end,
        ];
    }

    /**
     * 返回几天前的时间戳
     *
     * @param int $day
     * @return int
     */
    public static function daysAgo($day = 1)
    {
        $nowTime = time();
        return $nowTime - self::daysToSecond($day);
    }


    /**
     * @DOC   :返回几天后的时间戳
     * @Name  : daysAfter
     * @Author: wangfei
     * @date  : 2022-04-19 2022
     * @param int $day
     * @param int $nowTime
     * @return float|int
     */
    public static function daysAfter(int $day = 1, int $nowTime = 0)
    {
        $nowTime = ($nowTime > 0) ? $nowTime : time();
        return strtotime("+ $day day", $nowTime);
    }


    /**
     * @DOC   : 返回几年后的日期
     * @Name  : yearAfter
     * @Author: wangfei
     * @date  : 2022-04-19 2022
     * @param int $year
     * @param int $nowTime
     * @return false|int
     */
    public static function yearAfter(int $year = 1, int $nowTime = 0)
    {
        $nowTime = ($nowTime > 0) ? $nowTime : time();
        return strtotime("+ $year year", $nowTime);
    }

    /**
     * 天数转换成秒数
     *
     * @param int $day
     * @return int
     */
    public static function daysToSecond($day = 1)
    {
        return $day * 86400;
    }

    /**
     * 周数转换成秒数
     *
     * @param int $week
     * @return int
     */
    public static function weekToSecond($week = 1)
    {
        return self::daysToSecond() * 7 * $week;
    }

    /**
     * 获取毫秒级别的时间戳
     */
    public static function microtime()
    {
        $microtime  = explode(" ", microtime());
        $microtime  = $microtime[1] . ($microtime[0] * 1000);
        $microtime2 = explode(".", $microtime);
        $microtime  = $microtime2[0];
        return $microtime;
    }
}
