<?php
//日志处理类

namespace App\Constants;

use Hyperf\Logger\LoggerFactory;

class Logger
{

    /**
     * @DOC   : 通用记录错误信息
     * @Name  : error
     * @Author: wangfei
     * @date  : 2025-02 22:17
     * @param $message
     * @param $context
     * @return void
     *
     */
    public static function error($message, $context = [])
    {
        $logger = make(LoggerFactory::class)->get('log', 'error');
        // 支持中文记录
        if (is_string($message)) {
            $message = json_encode($message, JSON_UNESCAPED_UNICODE);
        } else {
            $message = json_encode($message, JSON_UNESCAPED_UNICODE);
        }

        // 获取调用堆栈信息
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller    = $backtrace[1] ?? [];
        $file      = $caller['file'] ?? 'unknown';
        $line      = $caller['line'] ?? 'unknown';
        $function  = $caller['function'] ?? 'unknown';

        // 构建日志消息
        $logMessage = sprintf(
            '文件: %s, 行号: %s, 函数: %s, 错误信息: %s',
            $file,
            $line,
            $function,
            $message
        );

        // 如果 context 不为空，将其添加到日志消息中
        if (!empty($context)) {
            $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE);
            $logMessage  .= ', 上下文信息: ' . $contextJson;
        }
        $logger->error($logMessage);
    }
}
