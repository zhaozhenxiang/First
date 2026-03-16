<?php

declare(strict_types=1);

namespace Bin\Request;

/**
 * HTTP 请求封装
 */
class Request implements \ArrayAccess, \Iterator
{
    /** @var array<string, mixed> 请求数据 */
    protected array $data = [];
    /** @var array<string, string> 头信息 */
    protected array $header = [];
    /** @var array<string, string> URL 匹配参数 */
    private array $urlMatch = [];

    public function __construct()
    {
        $this->initializeData();
    }

    /**
     * 初始化请求数据
     */
    private function initializeData(): void
    {
        foreach ($_REQUEST as $key => $value) {
            $this->data[$key] = $value;
        }
    }

    /**
     * 获取单个输入值
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * 获取多个输入值
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->data, array_flip($keys));
    }

    /**
     * 获取除指定键外的所有输入
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->data, array_flip($keys));
    }

    /**
     * 检查输入是否存在
     */
    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * 获取请求头信息
     */
    public function header(string $key = null): array|string|null
    {
        if ($key === null) {
            return $_SERVER;
        }

        $headerKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $_SERVER[$headerKey] ?? $_SERVER[strtoupper($key)] ?? null;
    }

    /**
     * 获取请求路径
     */
    public function getPath(): string
    {
        return trim($_SERVER['PATH_INFO'] ?? '/', '/');
    }

    /**
     * 获取请求方法
     */
    public function getMethod(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // 支持 _method 覆盖
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        return $method;
    }

    /**
     * 检查是否为 AJAX 请求
     */
    public function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * 检查是否为 JSON 请求
     */
    public function isJson(): bool
    {
        return str_contains($this->header('Content-Type') ?? '', 'application/json');
    }

    /**
     * 获取请求的 Content-Type
     */
    public function getContentType(): string
    {
        return $_SERVER['CONTENT_TYPE'] ?? '';
    }

    /**
     * 获取客户端 IP
     */
    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * 获取 User-Agent
     */
    public function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * 获取请求开始时间
     */
    public function getStartTime(string $format = 'Y-m-d H:i:s'): string
    {
        return date($format, START_TIME ?? time());
    }

    /**
     * 获取所有请求数据
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * 获取 URL 参数
     */
    public function getUrlParam(): array
    {
        return $this->urlMatch;
    }

    /**
     * 设置 URL 参数
     */
    public function setUrlParam(array $params): self
    {
        $this->urlMatch = $params;
        return $this;
    }

    // Magic methods
    public function &__get(string $key): mixed
    {
        return $this->data[$key];
    }

    public function __set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function __isset(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function __unset(string $key): void
    {
        unset($this->data[$key]);
    }

    // ArrayAccess
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    // Iterator
    public function rewind(): void
    {
        reset($this->data);
    }

    public function current(): mixed
    {
        return current($this->data);
    }

    public function key(): mixed
    {
        return key($this->data);
    }

    public function next(): void
    {
        next($this->data);
    }

    public function valid(): bool
    {
        return key($this->data) !== null;
    }
}
