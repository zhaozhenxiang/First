<?php

declare(strict_types=1);

namespace Bin\Cache;

use Closure;
use DateInterval;
use RuntimeException;

/**
 * 文件缓存驱动
 */
class FileStore implements CacheRepository
{
    /**
     * 缓存目录
     */
    protected string $path;

    /**
     * 构造函数
     */
    public function __construct(?string $path = null)
    {
        $this->path = $path ?? basePath('/storage/cache');

        // 确保缓存目录存在
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    /**
     * 获取缓存项
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->getPath($key);

        if (!file_exists($path)) {
            return $default;
        }

        $content = file_get_contents($path);
        $data = unserialize($content);

        // 检查是否过期
        if ($data['expires'] !== null && $data['expires'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $data['value'];
    }

    /**
     * 设置缓存项
     */
    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        $path = $this->getPath($key);

        $expires = null;
        if ($ttl !== null) {
            $expires = $this->calculateExpiration($ttl);
        }

        $data = [
            'value' => $value,
            'expires' => $expires,
        ];

        $content = serialize($data);

        return file_put_contents($path, $content, LOCK_EX) !== false;
    }

    /**
     * 删除缓存项
     */
    public function delete(string $key): bool
    {
        $path = $this->getPath($key);

        if (!file_exists($path)) {
            return true;
        }

        return unlink($path);
    }

    /**
     * 清空所有缓存
     */
    public function clear(): bool
    {
        $files = glob($this->path . '/*');

        if ($files === false) {
            return true;
        }

        $success = true;

        foreach ($files as $file) {
            if (is_file($file)) {
                if (!unlink($file)) {
                    $success = false;
                }
            }
        }

        return $success;
    }

    /**
     * 获取多个缓存项
     */
    public function getMultiple(array $keys, mixed $default = null): array
    {
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }

        return $results;
    }

    /**
     * 设置多个缓存项
     */
    public function setMultiple(array $values, int|DateInterval|null $ttl = null): bool
    {
        $success = true;

        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * 删除多个缓存项
     */
    public function deleteMultiple(array $keys): bool
    {
        $success = true;

        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * 检查缓存项是否存在
     */
    public function has(string $key): bool
    {
        $path = $this->getPath($key);

        if (!file_exists($path)) {
            return false;
        }

        $content = file_get_contents($path);
        $data = unserialize($content);

        // 检查是否过期
        if ($data['expires'] !== null && $data['expires'] < time()) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    /**
     * 获取并删除缓存项
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);

        $this->delete($key);

        return $value;
    }

    /**
     * 不存在时存储并返回
     */
    public function remember(string $key, int|DateInterval|Closure|null $ttl, Closure $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        // 如果 ttl 是闭包，执行它获取值和 TTL
        if ($ttl instanceof Closure) {
            $result = $ttl();
            $ttl = null;
        } else {
            $result = $callback();
        }

        $this->set($key, $result, $ttl);

        return $result;
    }

    /**
     * 获取或设置
     */
    public function getOrSet(string $key, mixed $value, int|DateInterval|null $ttl = null): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        // 如果 value 是闭包，执行它
        if ($value instanceof Closure) {
            $value = $value();
        }

        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * 获取缓存文件路径
     */
    protected function getPath(string $key): string
    {
        $hash = md5($key);

        return $this->path . '/' . $hash . '.cache';
    }

    /**
     * 计算过期时间
     */
    protected function calculateExpiration(int|DateInterval $ttl): int
    {
        if ($ttl instanceof DateInterval) {
            $now = new \DateTime();
            $now->add($ttl);
            return $now->getTimestamp();
        }

        return time() + $ttl;
    }

    /**
     * 增加缓存值
     */
    public function increment(string $key, int $value = 1): int
    {
        $current = $this->get($key, 0);

        if (!is_numeric($current)) {
            return false;
        }

        $newValue = (int) $current + $value;

        $this->set($key, $newValue);

        return $newValue;
    }

    /**
     * 减少缓存值
     */
    public function decrement(string $key, int $value = 1): int
    {
        return $this->increment($key, -$value);
    }

    /**
     * 永久存储
     */
    public function forever(string $key, mixed $value): bool
    {
        return $this->set($key, $value, null);
    }

    /**
     * 获取缓存目录
     */
    public function getCachePath(): string
    {
        return $this->path;
    }
}
