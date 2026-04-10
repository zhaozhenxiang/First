<?php

declare(strict_types=1);

namespace Bin\Http;

use RuntimeException;

/**
 * HTTP 响应
 */
class HttpResponse
{
    protected int $statusCode;
    protected string $body;
    protected array $headers;
    protected array $cookies;
    protected string $effectiveUrl;

    public function __construct(
        int $statusCode,
        string $body = '',
        array $headers = [],
        array $cookies = [],
        string $effectiveUrl = ''
    ) {
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->headers = $headers;
        $this->cookies = $cookies;
        $this->effectiveUrl = $effectiveUrl;
    }

    /**
     * 从 curl 句柄创建响应
     */
    public static function fromCurl(mixed $ch, string $rawResponse): static
    {
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

        // 解析头部和 body
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $rawHeaders = substr($rawResponse, 0, $headerSize);
        $body = substr($rawResponse, $headerSize);

        $headers = static::parseHeaders($rawHeaders);
        $cookies = static::parseCookies($headers);

        return new static($statusCode, $body, $headers, $cookies, $effectiveUrl);
    }

    /**
     * HTTP 状态码
     */
    public function status(): int
    {
        return $this->statusCode;
    }

    /**
     * 原始 body
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * 解码 JSON body
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        $data = json_decode($this->body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $default;
        }

        if ($key === null) {
            return $data;
        }

        return static::dataGet($data, $key, $default);
    }

    /**
     * 所有响应头
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * 获取单个响应头
     */
    public function header(string $name, ?string $default = null): ?string
    {
        $lower = strtolower($name);

        foreach ($this->headers as $key => $value) {
            if (strtolower((string) $key) === $lower) {
                return is_array($value) ? implode(', ', $value) : $value;
            }
        }

        return $default;
    }

    /**
     * 响应 cookies
     */
    public function cookies(): array
    {
        return $this->cookies;
    }

    /**
     * 最终 URL（重定向后）
     */
    public function effectiveUrl(): string
    {
        return $this->effectiveUrl;
    }

    /**
     * 请求是否成功 (2xx)
     */
    public function successful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * 请求是否失败 (4xx or 5xx)
     */
    public function failed(): bool
    {
        return $this->clientError() || $this->serverError();
    }

    /**
     * 客户端错误 (4xx)
     */
    public function clientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * 服务端错误 (5xx)
     */
    public function serverError(): bool
    {
        return $this->statusCode >= 500;
    }

    /**
     * 是否重定向 (3xx)
     */
    public function redirect(): bool
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    /**
     * 失败时抛出异常
     */
    public function throw(): static
    {
        if ($this->failed()) {
            throw new RuntimeException(
                "HTTP request failed with status {$this->statusCode}: {$this->body}",
                $this->statusCode
            );
        }

        return $this;
    }

    /**
     * 失败时执行回调
     */
    public function onError(callable $callback): static
    {
        if ($this->failed()) {
            $callback($this);
        }

        return $this;
    }

    /**
     * 解析原始响应头
     */
    protected static function parseHeaders(string $rawHeaders): array
    {
        $headers = [];

        foreach (explode("\r\n", $rawHeaders) as $line) {
            if (str_contains($line, ': ')) {
                [$key, $value] = explode(': ', $line, 2);
                $key = trim($key);

                if (isset($headers[$key])) {
                    $headers[$key] = array_merge((array) $headers[$key], [$value]);
                } else {
                    $headers[$key] = $value;
                }
            }
        }

        return $headers;
    }

    /**
     * 从 Set-Cookie 头解析 cookies
     */
    protected static function parseCookies(array $headers): array
    {
        $cookies = [];

        $setCookie = $headers['Set-Cookie'] ?? [];
        $setCookie = is_array($setCookie) ? $setCookie : [$setCookie];

        foreach ($setCookie as $line) {
            $parts = explode(';', $line);
            $kv = explode('=', trim($parts[0]), 2);
            if (count($kv) === 2) {
                $cookies[$kv[0]] = $kv[1];
            }
        }

        return $cookies;
    }

    /**
     * 点号路径取值
     */
    protected static function dataGet(mixed $data, string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);

        foreach ($keys as $segment) {
            if (is_array($data) && array_key_exists($segment, $data)) {
                $data = $data[$segment];
            } else {
                return $default;
            }
        }

        return $data;
    }
}
