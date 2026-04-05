<?php

declare(strict_types=1);

if (!function_exists('logger')) {
    /**
     * 记录日志
     */
    function logger(?string $channel = null): \Bin\Log\Logger
    {
        return \Bin\Log\LogManager::channel($channel);
    }
}

if (!function_exists('info')) {
    /**
     * 记录 info 日志
     */
    function info(string $message, array $context = []): void
    {
        \Bin\Log\LogManager::channel()->info($message, $context);
    }
}

if (!function_exists('error')) {
    /**
     * 记录 error 日志
     */
    function error(string $message, array $context = []): void
    {
        \Bin\Log\LogManager::channel()->error($message, $context);
    }
}
