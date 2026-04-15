<?php

declare(strict_types=1);

namespace Bin\Response;

use Bin\View\Compiler;
use Bin\View\View;

class Response
{
    private mixed $originContent = '';

    private int $statusCode = 200;

    /** @var array<string, string> */
    private array $headers = [];

    /**
     * @param $response
     * @throws \Exception
     * @todo 之后要把该函数的实现改成工厂模式
     */
    public function __construct($response = null, int $statusCode = 200, array $headers = [])
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;

        //根据response类型来获取数据
        if (is_string($response)) {
            $this->originContent = $response;
        }

        if (is_array($response)) {
            $this->originContent = json_encode($response, JSON_THROW_ON_ERROR);
        }

        //todo 处理编译view的问题
        if ($response instanceof View) {
            $this->originContent = (new Compiler($response))->render();
        }

        if ($response === null) {
            $this->originContent = '';
        }
        //todo 处理active record
    }

    /**
     *  获取content
     * @return string
     */
    public function getContent(): string
    {
        return (string)$this->originContent;
    }

    /**
     *  设置content
     * @param  mixed  $string
     * @return self
     */
    public function setContent(mixed $string): self
    {
        $this->originContent = $string;
        return $this;
    }

    /**
     *  获取数据
     * @return string
     */
    public function __toString()
    {
        return $this->getContent();
    }

    /**
     * 获取状态码
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * 获取全部响应头
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * 获取单个响应头
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * 设置响应头
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * 发送响应内容到输出缓冲区
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}", true);
            }
        }

        echo $this->getContent();
    }

    /**
     *  设置状态
     * @param  int     $httpStatus
     * @param  string  $content
     * @return self
     */
    public function setStatus(int $httpStatus, mixed $content = null): self
    {
        $status = [
            // Informational 1xx
            100 => 'Continue',
            101 => 'Switching Protocols',
            // Success 2xx
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            // Redirection 3xx
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Moved Temporarily ',  // 1.1
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            // 306 is deprecated but reserved
            307 => 'Temporary Redirect',
            // Client Error 4xx
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            // Server Error 5xx
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            509 => 'Bandwidth Limit Exceeded',
        ];
        $this->statusCode = $httpStatus;

        if (null == $content) {
            $this->originContent = (string)View::make($httpStatus);
            return $this;
        }

        $this->setContent($content);

        return $this;
    }
}
