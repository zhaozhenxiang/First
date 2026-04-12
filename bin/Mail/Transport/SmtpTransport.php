<?php

declare(strict_types=1);

namespace Bin\Mail\Transport;

use Bin\Mail\Mailable;
use RuntimeException;

/**
 * SMTP 传输
 *
 * 通过 fsockopen 直连 SMTP 服务器发送邮件。
 */
class SmtpTransport implements TransportInterface
{
    /** @var array<string> 已发送消息 */
    protected array $sentIds = [];

    protected string $host;
    protected int $port;
    protected string $encryption;
    protected ?string $username;
    protected ?string $password;
    protected int $timeout;

    /** @var resource|null */
    protected $socket = null;

    public function __construct(array $config = [])
    {
        $this->host = $config['host'] ?? 'localhost';
        $this->port = (int) ($config['port'] ?? 587);
        $this->encryption = $config['encryption'] ?? 'tls';
        $this->username = $config['username'] ?? null;
        $this->password = $config['password'] ?? null;
        $this->timeout = (int) ($config['timeout'] ?? 30);
    }

    public function send(Mailable $mailable): array
    {
        $this->connect();

        try {
            $this->sendGreeting();
            $this->startTls();
            $this->authenticate();
            $this->sendMailFrom($mailable);
            $this->sendRcptTo($mailable);
            $this->sendData($mailable);
        } finally {
            $this->disconnect();
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
     * 连接 SMTP 服务器
     */
    protected function connect(): void
    {
        $prefix = ($this->encryption === 'ssl') ? 'ssl://' : '';
        $socket = @fsockopen($prefix . $this->host, $this->port, $errno, $errstr, $this->timeout);

        if ($socket === false) {
            throw new RuntimeException("SMTP connect failed: [{$errno}] {$errstr}");
        }

        $this->socket = $socket;
        stream_set_timeout($socket, $this->timeout);

        $this->readResponse(); // 读取欢迎信息
    }

    /**
     * 发送 EHLO
     */
    protected function sendGreeting(): void
    {
        $this->sendCommand('EHLO ' . gethostname(), 250);
    }

    /**
     * STARTTLS
     */
    protected function startTls(): void
    {
        if ($this->encryption !== 'tls') {
            return;
        }

        $this->sendCommand('STARTTLS', 220);

        if (!stream_socket_enable_crypto(
            $this->socket,
            true,
            STREAM_CRYPTO_METHOD_TLS_CLIENT
        )) {
            throw new RuntimeException('Failed to enable TLS encryption');
        }

        $this->sendCommand('EHLO ' . gethostname(), 250);
    }

    /**
     * SMTP 认证
     */
    protected function authenticate(): void
    {
        if ($this->username === null || $this->password === null) {
            return;
        }

        $this->sendCommand('AUTH LOGIN', 334);
        $this->sendCommand(base64_encode($this->username), 334);
        $this->sendCommand(base64_encode($this->password), 235);
    }

    /**
     * MAIL FROM
     */
    protected function sendMailFrom(Mailable $mailable): void
    {
        $from = $this->sanitizeAddress($mailable->getFromAddress());
        $this->sendCommand("MAIL FROM:<{$from}>", 250);
    }

    /**
     * RCPT TO
     */
    protected function sendRcptTo(Mailable $mailable): void
    {
        $recipients = array_merge(
            $mailable->getTo(),
            $mailable->getCc(),
            $mailable->getBcc()
        );

        if ($recipients === []) {
            throw new RuntimeException('No recipients specified');
        }

        foreach ($recipients as $address) {
            $address = $this->sanitizeAddress($address);
            $this->sendCommand("RCPT TO:<{$address}>", [250, 251]);
        }
    }

    /**
     * DATA 段
     */
    protected function sendData(Mailable $mailable): void
    {
        $this->sendCommand('DATA', 354);

        $message = $this->buildMessage($mailable);
        $message .= "\r\n.";

        $this->sendCommand($message, 250);
    }

    /**
     * 断开连接
     */
    protected function disconnect(): void
    {
        if ($this->socket !== null) {
            $this->sendCommand('QUIT', 221);
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * 构建邮件消息
     */
    protected function buildMessage(Mailable $mailable): string
    {
        $headers = [];
        $headers[] = 'From: ' . $this->formatAddress($mailable->getFrom());
        $headers[] = 'Date: ' . date('r');

        if ($mailable->getSubject() !== '') {
            $headers[] = 'Subject: =?UTF-8?B?' . base64_encode($mailable->getSubject()) . '?=';
        }

        $to = $mailable->getTo();
        if ($to !== []) {
            $headers[] = 'To: ' . implode(', ', array_map($this->sanitizeAddress(...), $to));
        }

        $cc = $mailable->getCc();
        if ($cc !== []) {
            $headers[] = 'Cc: ' . implode(', ', array_map($this->sanitizeAddress(...), $cc));
        }

        $bcc = $mailable->getBcc();
        if ($bcc !== []) {
            $headers[] = 'Bcc: ' . implode(', ', array_map($this->sanitizeAddress(...), $bcc));
        }

        $replyTo = $mailable->getReplyTo();
        if ($replyTo !== []) {
            $headers[] = 'Reply-To: ' . implode(', ', array_map($this->sanitizeAddress(...), $replyTo));
        }

        $body = $mailable->getHtmlContent() ?: $mailable->getTextContent();

        // MIME boundary
        $boundary = md5((string) time());

        if ($mailable->getHtmlContent() !== '' && $mailable->getTextContent() !== '') {
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";

            $body = "--{$boundary}\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
                . $mailable->getTextContent() . "\r\n\r\n"
                . "--{$boundary}\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
                . $mailable->getHtmlContent() . "\r\n\r\n"
                . "--{$boundary}--";
        } elseif ($mailable->getHtmlContent() !== '') {
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        }

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    /**
     * 格式化发件人地址
     */
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

    /**
     * 净化邮件地址，防止 CRLF 注入
     */
    protected function sanitizeAddress(string $address): string
    {
        return str_replace(["\r", "\n", "\t"], '', $address);
    }

    /**
     * 发送 SMTP 命令并验证响应
     */
    protected function sendCommand(string $command, int|array $expectedCode): string
    {
        if ($this->socket === null) {
            throw new RuntimeException('SMTP not connected');
        }

        fwrite($this->socket, $command . "\r\n");

        $response = $this->readResponse();
        $code = (int) substr($response, 0, 3);

        $expectedCodes = is_array($expectedCode) ? $expectedCode : [$expectedCode];

        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException("SMTP error: {$response} (expected: " . implode('/', $expectedCodes) . ")");
        }

        return $response;
    }

    /**
     * 读取 SMTP 响应
     */
    protected function readResponse(): string
    {
        if ($this->socket === null) {
            return '';
        }

        $response = '';
        while ($line = fgets($this->socket, 515)) {
            $response .= $line;
            // SMTP 多行响应以 3 位数字 + 空格结尾
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        return trim($response);
    }
}
