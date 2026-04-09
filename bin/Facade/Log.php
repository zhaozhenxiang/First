<?php

declare(strict_types=1);

namespace Bin\Facade;

use Bin\Log\LogManager;

/**
 * Log Facade - 静态代理日志管理器
 *
 * @method static void debug(string $message, array $context = [])
 * @method static void info(string $message, array $context = [])
 * @method static void warning(string $message, array $context = [])
 * @method static void error(string $message, array $context = [])
 * @method static void log(string $level, string $message, array $context = [])
 */
class Log extends Facade
{
    protected function getClassName(): string
    {
        return LogManager::class;
    }

    protected static function getInstance(): object
    {
        return LogManager::getInstance()->channelFor();
    }
}
