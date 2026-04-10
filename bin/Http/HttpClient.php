<?php

declare(strict_types=1);

namespace Bin\Http;

/**
 * HTTP 客户端管理器
 *
 * 单例模式，从配置文件读取默认设置，工厂方法创建 PendingRequest。
 *
 * 用法：
 *   HttpClient::getInstance()->get('https://api.example.com/users');
 *   HttpClient::getInstance()->withToken('xxx')->post('https://api.example.com/data', [...]);
 */
class HttpClient
{
    /** @var array 配置 */
    protected array $config = [];

    /** @var self|null 单例 */
    private static ?self $instance = null;

    public function __construct()
    {
        if ($this->config === [] && function_exists('config')) {
            $this->config = config('http') ?? [];
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
     * 重置单例（测试用）
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
     * 创建新的 PendingRequest（带默认配置）
     */
    public function make(): PendingRequest
    {
        $config = [
            'base_url' => $this->config['base_url'] ?? '',
            'timeout' => $this->config['timeout'] ?? 30,
            'connect_timeout' => $this->config['connect_timeout'] ?? 10,
            'verify_ssl' => $this->config['verify_ssl'] ?? true,
            'follow_redirects' => $this->config['follow_redirects'] ?? true,
            'max_redirects' => $this->config['max_redirects'] ?? 5,
            'headers' => $this->config['headers'] ?? [],
        ];

        return new PendingRequest($config);
    }

    /**
     * 代理方法到 PendingRequest
     *
     * 允许直接调用：HttpClient::getInstance()->get(...)
     */
    public function __call(string $method, array $arguments): mixed
    {
        $pendingRequest = $this->make();

        if (method_exists($pendingRequest, $method)) {
            return $pendingRequest->{$method}(...$arguments);
        }

        throw new \BadMethodCallException("Method {$method} does not exist on PendingRequest");
    }
}
