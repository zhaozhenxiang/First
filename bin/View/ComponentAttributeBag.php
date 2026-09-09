<?php

declare(strict_types=1);

namespace Bin\View;

use ArrayAccess;

/**
 * 组件属性包
 *
 * 匿名组件 `<x-alert type="error">` 的属性集合：
 *   {{ $attributes->get('type') }}
 *   <div {{ $attributes->merge(['class' => 'base']) }}>
 */
class ComponentAttributeBag implements ArrayAccess
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        protected array $attributes = []
    ) {
    }

    /**
     * 获取属性值
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * 属性是否存在
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * 合并默认属性（class 值合并去重，其余属性覆盖默认）
     */
    public function merge(array $defaults = []): static
    {
        $merged = $defaults;

        foreach ($this->attributes as $key => $value) {
            if ($key === 'class' && isset($merged['class']) && is_string($value) && is_string($merged['class'])) {
                $merged['class'] = implode(' ', array_values(array_unique(array_merge(
                    preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [],
                    preg_split('/\s+/', trim($merged['class']), -1, PREG_SPLIT_NO_EMPTY) ?: []
                ))));
                continue;
            }

            $merged[$key] = $value;
        }

        return new static($merged);
    }

    /**
     * 仅保留指定属性
     */
    public function only(array $keys): static
    {
        return new static(array_intersect_key($this->attributes, array_flip($keys)));
    }

    /**
     * 排除指定属性
     */
    public function except(array $keys): static
    {
        return new static(array_diff_key($this->attributes, array_flip($keys)));
    }

    /**
     * 全部属性
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->attributes;
    }

    /**
     * 输出为 HTML 属性字符串（跳过 null 与 true 值输出裸属性名）
     */
    public function __toString(): string
    {
        $parts = [];

        foreach ($this->attributes as $key => $value) {
            if ($value === null) {
                continue;
            }

            if ($value === true) {
                $parts[] = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
                continue;
            }

            $parts[] = htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8')
                . '="'
                . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8')
                . '"';
        }

        return implode(' ', $parts);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->attributes[(string) $offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[(string) $offset]);
    }
}
