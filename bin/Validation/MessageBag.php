<?php

declare(strict_types=1);

namespace Bin\Validation;

/**
 * 验证错误消息容器
 *
 * 支持单字段多条错误，提供 first/all/has/get/add 等方法。
 * 兼容 Laravel Illuminate\Support\MessageBag 的核心 API。
 */
class MessageBag
{
    /** @var array<string, array<string>> 字段 → 错误消息列表 */
    protected array $messages = [];

    /**
     * 创建 MessageBag
     *
     * @param  array<string, string|array<string>>  $messages  初始消息
     */
    public function __construct(array $messages = [])
    {
        foreach ($messages as $key => $value) {
            $this->messages[$key] = is_array($value) ? $value : [$value];
        }
    }

    /**
     * 添加错误消息
     */
    public function add(string $key, string $message): static
    {
        $this->messages[$key][] = $message;

        return $this;
    }

    /**
     * 获取指定字段的所有错误
     *
     * @return array<string>
     */
    public function get(string $key): array
    {
        return $this->messages[$key] ?? [];
    }

    /**
     * 获取指定字段的第一条错误
     */
    public function first(string $key): string
    {
        return $this->messages[$key][0] ?? '';
    }

    /**
     * 是否有错误（可指定字段）
     */
    public function has(?string $key = null): bool
    {
        if ($key === null) {
            return $this->messages !== [];
        }

        // 支持通配符 key.*
        if (str_contains($key, '*')) {
            $pattern = '/^' . str_replace('\*', '[^.]+', preg_quote($key, '/')) . '$/';
            foreach ($this->messages as $k => $_) {
                if (preg_match($pattern, $k)) {
                    return true;
                }
            }
            return false;
        }

        return isset($this->messages[$key]);
    }

    /**
     * 是否没有任何错误
     */
    public function isEmpty(): bool
    {
        return $this->messages === [];
    }

    /**
     * 是否有错误（isEmpty 的反向）
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * 获取所有错误消息
     *
     * @return array<string, array<string>>
     */
    public function all(): array
    {
        return $this->messages;
    }

    /**
     * 获取所有唯一的错误消息（扁平化）
     *
     * @return array<string>
     */
    public function uniqueMessages(): array
    {
        $all = [];
        foreach ($this->messages as $messages) {
            $all = array_merge($all, $messages);
        }

        return array_values(array_unique($all));
    }

    /**
     * 获取有错误的字段名列表
     *
     * @return array<string>
     */
    public function keys(): array
    {
        return array_keys($this->messages);
    }

    /**
     * 错误数量
     */
    public function count(): int
    {
        $total = 0;
        foreach ($this->messages as $messages) {
            $total += count($messages);
        }

        return $total;
    }

    /**
     * 合并另一个 MessageBag
     */
    public function merge(self $bag): static
    {
        foreach ($bag->all() as $key => $messages) {
            foreach ($messages as $message) {
                $this->add($key, $message);
            }
        }

        return $this;
    }

    /**
     * 清空所有消息
     */
    public function clear(): static
    {
        $this->messages = [];

        return $this;
    }

    /**
     * 转为数组
     */
    public function toArray(): array
    {
        return $this->messages;
    }
}
