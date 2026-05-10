<?php

declare(strict_types=1);

namespace Bin\Mail;

use Bin\Mail\Transport\TransportInterface;
use Bin\Queue\ShouldQueue;

/**
 * 邮件发送器
 *
 * 封装 Transport，提供发送/队列接口。
 */
class Mailer
{
    protected TransportInterface $transport;
    protected array $failures = [];
    protected ?string $name;

    /** @var array 收件人覆盖 */
    protected array $toOverride = [];

    /** @var self|null 单例 */
    private static ?self $instance = null;

    public function __construct(?TransportInterface $transport = null, ?string $name = null)
    {
        $this->transport = $transport ?? new \Bin\Mail\Transport\ArrayTransport();
        $this->name = $name;
    }

    /**
     * 获取单例
     */
    public static function getInstance(): static
    {
        return self::$instance ??= new static();
    }

    /**
     * 重置单例
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * 设置 Transport
     */
    public function setTransport(TransportInterface $transport): static
    {
        $this->transport = $transport;
        return $this;
    }

    /**
     * 获取 Transport
     */
    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    /**
     * 设置收件人（全局覆盖）
     */
    public function to(string|array $address): static
    {
        $this->toOverride = is_array($address) ? $address : [$address];
        return $this;
    }

    /**
     * 发送邮件
     */
    public function send(Mailable $mailable): array
    {
        // 确保 build() 已调用
        $mailable->build();

        // 应用全局收件人覆盖
        if ($this->toOverride !== []) {
            $mailable->to($this->toOverride);
        }

        $this->failures = [];

        try {
            return $this->transport->send($mailable);
        } catch (\Throwable $e) {
            $this->failures = $mailable->getTo();
            throw $e;
        }
    }

    /**
     * 队列发送
     */
    public function queue(Mailable $mailable): mixed
    {
        if ($mailable instanceof ShouldQueue) {
            // 利用 QueueManager 入队
            if (class_exists(\Bin\Queue\QueueManager::class)) {
                return \Bin\Queue\QueueManager::getInstance()->push(new SendQueuedMailable($mailable, $this->name));
            }
        }

        // 不支持队列则直接发送
        $this->send($mailable);
        return true;
    }

    /**
     * 延迟发送
     */
    public function later(int $delay, Mailable $mailable): mixed
    {
        if ($mailable instanceof ShouldQueue) {
            if (class_exists(\Bin\Queue\QueueManager::class)) {
                return \Bin\Queue\QueueManager::getInstance()->later($delay, new SendQueuedMailable($mailable, $this->name));
            }
        }

        $this->send($mailable);
        return true;
    }

    /**
     * 获取上次发送失败的收件人
     */
    public function failures(): array
    {
        return $this->failures;
    }
}
