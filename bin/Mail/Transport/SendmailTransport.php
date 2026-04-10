<?php

declare(strict_types=1);

namespace Bin\Mail\Transport;

use Bin\Mail\Mailable;
use RuntimeException;

/**
 * Sendmail 传输
 *
 * 通过系统 sendmail 命令发送邮件。
 */
class SendmailTransport implements TransportInterface
{
    protected string $path;

    /** @var array<string> 已发送消息 */
    protected array $sentIds = [];

    public function __construct(array $config = [])
    {
        $this->path = $config['path'] ?? '/usr/sbin/sendmail -bs';
    }

    public function send(Mailable $mailable): array
    {
        $message = $this->buildMessage($mailable);

        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $process = proc_open($this->path, $descriptors, $pipes);

        if ($process === false) {
            throw new RuntimeException('Failed to open sendmail process');
        }

        fwrite($pipes[0], $message);
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException("Sendmail failed (exit code {$exitCode}): {$error}");
        }

        $id = uniqid('msg_', true);
        $this->sentIds[] = $id;

        return [$id];
    }

    public function getSentMessages(): array
    {
        return $this->sentIds;
    }

    /**
     * 构建邮件消息
     */
    protected function buildMessage(Mailable $mailable): string
    {
        $headers = [];

        if ($mailable->getFrom() !== []) {
            $headers[] = 'From: ' . $this->formatAddress($mailable->getFrom());
        }

        $headers[] = 'Date: ' . date('r');

        if ($mailable->getSubject() !== '') {
            $headers[] = 'Subject: =?UTF-8?B?' . base64_encode($mailable->getSubject()) . '?=';
        }

        $to = $mailable->getTo();
        if ($to !== []) {
            $headers[] = 'To: ' . implode(', ', $to);
        }

        $body = $mailable->getHtmlContent() ?: $mailable->getTextContent();

        if ($mailable->getHtmlContent() !== '') {
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        }

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    protected function formatAddress(array $from): string
    {
        if ($from === []) {
            return 'noreply@example.com';
        }
        $address = $from[0];
        $name = $from[1] ?? '';
        if ($name !== '') {
            return "\"{$name}\" <{$address}>";
        }
        return $address;
    }
}
