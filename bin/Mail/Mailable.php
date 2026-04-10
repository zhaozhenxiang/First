<?php

declare(strict_types=1);

namespace Bin\Mail;

use Bin\Mail\Transport\TransportInterface;
use Bin\Queue\ShouldQueue;

/**
 * Mailable 基类
 *
 * 用户继承此类，在 build() 中定义邮件内容和收件人。
 *
 * 用法：
 *   class WelcomeEmail extends Mailable {
 *       public function build(): void {
 *           $this->subject('Welcome')
 *                 ->to('user@example.com')
 *                 ->html('<h1>Welcome!</h1>');
 *       }
 *   }
 */
abstract class Mailable
{
    protected string $subjectValue = '';
    protected array $fromAddress = [];
    protected array $toAddresses = [];
    protected array $ccAddresses = [];
    protected array $bccAddresses = [];
    protected array $replyToAddresses = [];
    protected array $attachments = [];

    protected string $htmlContent = '';
    protected string $textContent = '';
    protected string $viewName = '';
    protected array $viewData = [];

    /**
     * 构建邮件（用户重写此方法）
     */
    public function build(): void
    {
        // 子类重写
    }

    // ─── 链式配置方法 ───

    public function subject(string $subject): static
    {
        $this->subjectValue = $subject;
        return $this;
    }

    public function from(string $address, ?string $name = null): static
    {
        $this->fromAddress = [$address, $name ?? ''];
        return $this;
    }

    public function to(string|array $address, ?string $name = null): static
    {
        $this->toAddresses = array_merge($this->toAddresses, $this->parseAddresses($address, $name));
        return $this;
    }

    public function cc(string|array $address, ?string $name = null): static
    {
        $this->ccAddresses = array_merge($this->ccAddresses, $this->parseAddresses($address, $name));
        return $this;
    }

    public function bcc(string|array $address, ?string $name = null): static
    {
        $this->bccAddresses = array_merge($this->bccAddresses, $this->parseAddresses($address, $name));
        return $this;
    }

    public function replyTo(string|array $address, ?string $name = null): static
    {
        $this->replyToAddresses = array_merge($this->replyToAddresses, $this->parseAddresses($address, $name));
        return $this;
    }

    public function html(string $content): static
    {
        $this->htmlContent = $content;
        return $this;
    }

    public function text(string $content): static
    {
        $this->textContent = $content;
        return $this;
    }

    public function view(string $name, array $data = []): static
    {
        $this->viewName = $name;
        $this->viewData = $data;
        return $this;
    }

    public function attach(string $path, array $options = []): static
    {
        $this->attachments[] = ['path' => $path, 'options' => $options];
        return $this;
    }

    public function attachData(string $data, string $name, array $options = []): static
    {
        $this->attachments[] = ['data' => $data, 'name' => $name, 'options' => $options];
        return $this;
    }

    // ─── Getter 方法 ───

    public function getSubject(): string
    {
        return $this->subjectValue;
    }

    public function getFrom(): array
    {
        return $this->fromAddress;
    }

    public function getFromAddress(): string
    {
        return $this->fromAddress[0] ?? 'noreply@example.com';
    }

    public function getTo(): array
    {
        return $this->toAddresses;
    }

    public function getCc(): array
    {
        return $this->ccAddresses;
    }

    public function getBcc(): array
    {
        return $this->bccAddresses;
    }

    public function getReplyTo(): array
    {
        return $this->replyToAddresses;
    }

    public function getHtmlContent(): string
    {
        return $this->htmlContent;
    }

    public function getTextContent(): string
    {
        return $this->textContent;
    }

    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function getView(): string
    {
        return $this->viewName;
    }

    public function getViewData(): array
    {
        return $this->viewData;
    }

    // ─── 发送方法 ───

    /**
     * 渲染为 HTML
     */
    public function render(): string
    {
        $this->build();

        if ($this->viewName !== '') {
            if (function_exists('view')) {
                return view($this->viewName, $this->viewData);
            }
        }

        return $this->htmlContent ?: $this->textContent;
    }

    /**
     * 发送邮件
     */
    public function send(?Mailer $mailer = null): void
    {
        $this->build();

        $mailer = $mailer ?? Mailer::getInstance();
        $mailer->send($this);
    }

    /**
     * 队列发送
     */
    public function queue(?Mailer $mailer = null): mixed
    {
        $this->build();

        $mailer = $mailer ?? Mailer::getInstance();
        return $mailer->queue($this);
    }

    /**
     * 延迟发送
     */
    public function later(int $delay, ?Mailer $mailer = null): mixed
    {
        $this->build();

        $mailer = $mailer ?? Mailer::getInstance();
        return $mailer->later($delay, $this);
    }

    // ─── 内部方法 ───

    /**
     * 解析地址
     */
    protected function parseAddresses(string|array $address, ?string $name = null): array
    {
        if (is_string($address)) {
            return [$address];
        }

        return $address;
    }

    /**
     * 序列化（用于队列）
     */
    public function __serialize(): array
    {
        return [
            'subject' => $this->subjectValue,
            'from' => $this->fromAddress,
            'to' => $this->toAddresses,
            'cc' => $this->ccAddresses,
            'bcc' => $this->bccAddresses,
            'replyTo' => $this->replyToAddresses,
            'html' => $this->htmlContent,
            'text' => $this->textContent,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->subjectValue = $data['subject'] ?? '';
        $this->fromAddress = $data['from'] ?? [];
        $this->toAddresses = $data['to'] ?? [];
        $this->ccAddresses = $data['cc'] ?? [];
        $this->bccAddresses = $data['bcc'] ?? [];
        $this->replyToAddresses = $data['replyTo'] ?? [];
        $this->htmlContent = $data['html'] ?? '';
        $this->textContent = $data['text'] ?? '';
    }
}
