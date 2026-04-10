<?php

declare(strict_types=1);

namespace Bin\Mail\Transport;

use Bin\Mail\Mailable;

/**
 * 数组传输（测试用）
 *
 * 将邮件存储在内存中，不实际发送。
 */
class ArrayTransport implements TransportInterface
{
    /** @var Mailable[] 已发送的 Mailable 列表 */
    protected array $sent = [];

    public function send(Mailable $mailable): array
    {
        $this->sent[] = clone $mailable;
        return [];
    }

    public function getSentMessages(): array
    {
        return $this->sent;
    }

    /**
     * 断言已发送指定类型的邮件
     */
    public function assertSent(string $mailableClass, ?callable $callback = null): bool
    {
        $count = 0;
        foreach ($this->sent as $mailable) {
            if ($mailable instanceof $mailableClass) {
                if ($callback === null || $callback($mailable)) {
                    $count++;
                }
            }
        }
        return $count > 0;
    }

    /**
     * 断言未发送指定类型的邮件
     */
    public function assertNotSent(string $mailableClass): bool
    {
        foreach ($this->sent as $mailable) {
            if ($mailable instanceof $mailableClass) {
                return false;
            }
        }
        return true;
    }

    /**
     * 获取已发送数量
     */
    public function count(): int
    {
        return count($this->sent);
    }

    /**
     * 清除已发送记录
     */
    public function reset(): void
    {
        $this->sent = [];
    }
}
