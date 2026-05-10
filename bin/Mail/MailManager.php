<?php

declare(strict_types=1);

namespace Bin\Mail;

use Bin\Mail\Transport\ArrayTransport;
use Bin\Mail\Transport\SmtpTransport;
use Bin\Mail\Transport\TransportInterface;
use RuntimeException;

/**
 * 邮件管理器
 *
 * 单例模式，从配置文件读取驱动，工厂方法创建 Mailer。
 *
 * 用法：
 *   MailManager::getInstance()->to('user@example.com')->send(new WelcomeEmail());
 */
class MailManager
{
    /** @var array<string, Mailer> 已解析的 mailer 实例 */
    protected array $mailers = [];

    /** @var string 默认 mailer 名 */
    protected string $defaultMailer = 'array';

    /** @var array 配置 */
    protected array $config = [];

    /** @var array 默认发件人 */
    protected array $from = [];

    /** @var self|null 单例 */
    private static ?self $instance = null;

    public function __construct()
    {
        if ($this->config === [] && function_exists('config')) {
            $this->config = config('mail.mailers') ?? [];
            $this->defaultMailer = config('mail.default') ?? 'array';
            $this->from = config('mail.from') ?? [];
        }
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
     * 设置配置
     */
    public function setConfig(array $config): static
    {
        $this->config = $config;
        return $this;
    }

    /**
     * 设置默认 mailer
     */
    public function setDefaultMailer(string $name): static
    {
        $this->defaultMailer = $name;
        return $this;
    }

    /**
     * 获取 Mailer 实例
     */
    public function mailer(?string $name = null): Mailer
    {
        $name = $name ?? $this->defaultMailer;

        if (!isset($this->mailers[$name])) {
            $this->mailers[$name] = $this->resolveMailer($name);
        }

        return $this->mailers[$name];
    }

    /**
     * 解析 Mailer
     */
    protected function resolveMailer(string $name): Mailer
    {
        $config = $this->config[$name] ?? [];

        if ($config === []) {
            throw new RuntimeException("Mailer [{$name}] is not configured.");
        }

        $driver = $config['driver'] ?? 'array';
        $transport = $this->createTransport($driver, $config);

        return new Mailer($transport, $name);
    }

    /**
     * 创建 Transport
     */
    protected function createTransport(string $driver, array $config): TransportInterface
    {
        return match ($driver) {
            'smtp' => new SmtpTransport($config),
            'sendmail' => new \Bin\Mail\Transport\SendmailTransport($config),
            'array' => new ArrayTransport(),
            default => throw new RuntimeException("Unsupported mail driver: {$driver}"),
        };
    }

    /**
     * 清除已解析的 mailer
     */
    public function flush(): void
    {
        $this->mailers = [];
    }

    // ─── 代理方法 ───

    public function to(string|array $address): Mailer
    {
        return $this->mailer()->to($address);
    }

    public function send(Mailable $mailable): array
    {
        return $this->mailer()->send($mailable);
    }

    public function queue(Mailable $mailable): mixed
    {
        return $this->mailer()->queue($mailable);
    }

    public function later(int $delay, Mailable $mailable): mixed
    {
        return $this->mailer()->later($delay, $mailable);
    }

    public function failures(): array
    {
        return $this->mailer()->failures();
    }

    /**
     * 获取 ArrayTransport（测试辅助）
     */
    public function getArrayTransport(): ?ArrayTransport
    {
        $mailer = $this->mailer();
        $transport = $mailer->getTransport();

        return $transport instanceof ArrayTransport ? $transport : null;
    }
}
