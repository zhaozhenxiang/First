<?php

declare(strict_types=1);

namespace Bin\Queue;

/**
 * Dispatchable trait
 *
 * 为 Job 提供静态分发方法。
 *
 * 用法：
 *   class SendEmail extends Job { use Dispatchable; ... }
 *   SendEmail::dispatch('user@example.com');
 */
trait Dispatchable
{
    /**
     * 分发到队列
     */
    public static function dispatch(mixed ...$args): PendingDispatch
    {
        $job = new static(...$args);

        return new PendingDispatch($job);
    }

    /**
     * 同步执行（不入队）
     */
    public static function dispatchSync(mixed ...$args): void
    {
        $job = new static(...$args);
        \Bin\App\App::getInstance()->getContainer()->call([$job, 'handle']);
    }

    /**
     * 响应后分发
     */
    public static function dispatchAfterResponse(mixed ...$args): PendingDispatch
    {
        return static::dispatch(...$args);
    }
}
