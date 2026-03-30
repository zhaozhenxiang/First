<?php

declare(strict_types=1);

namespace Bin\Database;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;

/**
 * 集合类 - 提供数组操作的便捷方法
 */
class Collection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    protected array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * 创建新集合
     */
    public static function make(array $items = []): self
    {
        return new self($items);
    }

    /**
     * 获取所有项目
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * 获取指定列的值
     */
    public function pluck(string $column, ?string $key = null): array
    {
        $results = [];

        foreach ($this->items as $item) {
            $value = is_array($item) ? ($item[$column] ?? null) : ($item->{$column} ?? null);

            if ($key !== null) {
                $id = is_array($item) ? ($item[$key] ?? null) : ($item->{$key} ?? null);
                $results[$id] = $value;
            } else {
                $results[] = $value;
            }
        }

        return $results;
    }

    /**
     * 按 key 分组
     */
    public function groupBy(string|callable $key): array
    {
        $results = [];

        foreach ($this->items as $item) {
            $groupKey = is_callable($key) ? $key($item) : (is_array($item) ? ($item[$key] ?? null) : ($item->{$key} ?? null));

            $results[$groupKey][] = $item;
        }

        return $results;
    }

    /**
     * 按 key 键值对
     */
    public function keyBy(string|callable $key): array
    {
        $results = [];

        foreach ($this->items as $item) {
            $resolvedKey = is_callable($key) ? $key($item) : (is_array($item) ? ($item[$key] ?? null) : ($item->{$key} ?? null));
            $results[$resolvedKey] = $item;
        }

        return $results;
    }

    /**
     * 获取第一个元素
     */
    public function first(callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return empty($this->items) ? $default : reset($this->items);
        }

        foreach ($this->items as $item) {
            if ($callback($item)) {
                return $item;
            }
        }

        return $default;
    }

    /**
     * 获取最后一个元素
     */
    public function last(callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return empty($this->items) ? $default : end($this->items);
        }

        $result = $default;

        foreach ($this->items as $item) {
            if ($callback($item)) {
                $result = $item;
            }
        }

        return $result;
    }

    /**
     * 弹出最后一个元素
     */
    public function pop(): mixed
    {
        return array_pop($this->items);
    }

    /**
     * Map 遍历
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->items);
    }

    /**
     * Filter 过滤
     */
    public function filter(callable $callback): array
    {
        return array_values(array_filter($this->items, $callback));
    }

    /**
     * Reject 拒绝
     */
    public function reject(callable $callback): array
    {
        return array_values(array_filter($this->items, fn($item) => !$callback($item)));
    }

    /**
     * Each 遍历
     */
    public function each(callable $callback): self
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Reduce 归约
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * Slice 切片
     */
    public function slice(int $offset, ?int $length = null): array
    {
        return array_slice($this->items, $offset, $length);
    }

    /**
     * Take 取前 N 个
     */
    public function take(int $limit): array
    {
        return array_slice($this->items, 0, $limit);
    }

    /**
     * Skip 跳过 N 个
     */
    public function skip(int $offset): array
    {
        return array_slice($this->items, $offset);
    }

    /**
     * Chunk 分块
     */
    public function chunk(int $size): array
    {
        return array_chunk($this->items, $size);
    }

    /**
     * Sort 排序
     */
    public function sort(callable $callback = null): array
    {
        $items = $this->items;

        if ($callback === null) {
            sort($items);
        } else {
            usort($items, $callback);
        }

        return $items;
    }

    /**
     * SortBy 按 key 排序
     */
    public function sortBy(string|callable $key, bool $descending = false): array
    {
        $results = $this->items;

        usort($results, function ($a, $b) use ($key) {
            $aValue = is_callable($key) ? $key($a) : (is_array($a) ? $a[$key] : $a->{$key});
            $bValue = is_callable($key) ? $key($b) : (is_array($b) ? $b[$key] : $b->{$key});

            return $aValue <=> $bValue;
        });

        return $descending ? array_reverse($results) : $results;
    }

    /**
     * Reverse 反转
     */
    public function reverse(): array
    {
        return array_reverse($this->items);
    }

    /**
     * Shuffle 随机打乱
     */
    public function shuffle(): array
    {
        $items = $this->items;
        shuffle($items);
        return $items;
    }

    /**
     * Count 计数
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Sum 求和
     */
    public function sum(string|callable|null $callback = null): int|float
    {
        if ($callback === null) {
            return array_sum($this->items);
        }

        return array_reduce($this->items, function ($sum, $item) use ($callback) {
            return $sum + (is_callable($callback) ? $callback($item) : (is_array($item) ? $item[$callback] : $item->{$callback}));
        }, 0);
    }

    /**
     * Avg 平均值
     */
    public function avg(string|callable|null $callback = null): int|float|null
    {
        $count = $this->count();

        if ($count === 0) {
            return null;
        }

        return $this->sum($callback) / $count;
    }

    /**
     * Max 最大值
     */
    public function max(string|callable|null $callback = null): mixed
    {
        if ($callback === null) {
            return empty($this->items) ? null : max($this->items);
        }

        $values = $this->pluck($callback);
        return empty($values) ? null : max($values);
    }

    /**
     * Min 最小值
     */
    public function min(string|callable|null $callback = null): mixed
    {
        if ($callback === null) {
            return empty($this->items) ? null : min($this->items);
        }

        $values = $this->pluck($callback);
        return empty($values) ? null : min($values);
    }

    /**
     * Contains 是否包含
     */
    public function contains(mixed $key, mixed $operator = null, mixed $value = null): bool
    {
        if (func_num_args() === 1) {
            if (is_callable($key)) {
                foreach ($this->items as $item) {
                    if ($key($item)) {
                        return true;
                    }
                }
                return false;
            }

            return in_array($key, $this->items, true);
        }

        return $this->where($key, $operator, $value)->isNotEmpty();
    }

    /**
     * Where 筛选
     */
    public function where(string|callable $key, mixed $operator = null, mixed $value = null): array
    {
        if (is_callable($key)) {
            return array_filter($this->items, $key);
        }

        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        return array_filter($this->items, function ($item) use ($key, $operator, $value) {
            $itemValue = is_array($item) ? ($item[$key] ?? null) : ($item->{$key} ?? null);

            return match ($operator) {
                '=' => $itemValue == $value,
                '==' => $itemValue == $value,
                '!=' => $itemValue != $value,
                '<>' => $itemValue != $value,
                '<' => $itemValue < $value,
                '>' => $itemValue > $value,
                '<=' => $itemValue <= $value,
                '>=' => $itemValue >= $value,
                '===' => $itemValue === $value,
                '!==' => $itemValue !== $value,
                default => false,
            };
        });
    }

    /**
     * WhereIn 筛选
     */
    public function whereIn(string $key, array $values): array
    {
        return array_filter($this->items, function ($item) use ($key, $values) {
            $itemValue = is_array($item) ? ($item[$key] ?? null) : ($item->{$key} ?? null);
            return in_array($itemValue, $values, true);
        });
    }

    /**
     * WhereNull 筛选空值
     */
    public function whereNull(string $key): array
    {
        return array_filter($this->items, function ($item) use ($key) {
            $itemValue = is_array($item) ? ($item[$key] ?? null) : ($item->{$key} ?? null);
            return $itemValue === null;
        });
    }

    /**
     * WhereNotNull 筛选非空值
     */
    public function whereNotNull(string $key): array
    {
        return array_filter($this->items, function ($item) use ($key) {
            $itemValue = is_array($item) ? ($item[$key] ?? null) : ($item->{$key} ?? null);
            return $itemValue !== null;
        });
    }

    /**
     * FirstWhere 第一个匹配
     */
    public function firstWhere(string $key, mixed $operator, mixed $value = null): mixed
    {
        $items = $this->where($key, $operator, $value);
        return empty($items) ? null : reset($items);
    }

    /**
     * IsEmpty 是否为空
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * IsNotEmpty 是否不为空
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Unique 去重
     */
    public function unique(string|callable|null $key = null): array
    {
        if ($key === null) {
            return array_values(array_unique($this->items, SORT_REGULAR));
        }

        $unique = [];
        $exists = [];

        foreach ($this->items as $item) {
            $id = is_callable($key) ? $key($item) : (is_array($item) ? ($item[$key] ?? null) : ($item->{$key} ?? null));

            if (!in_array($id, $exists, true)) {
                $exists[] = $id;
                $unique[] = $item;
            }
        }

        return $unique;
    }

    /**
     * Collapse 折叠多维数组
     */
    public function collapse(): array
    {
        $results = [];

        foreach ($this->items as $values) {
            if (!is_array($values)) {
                continue;
            }

            $results = array_merge($results, $values);
        }

        return $results;
    }

    /**
     * Flatten 扁平化
     */
    public function flatten(int $depth = PHP_INT_MAX): array
    {
        $result = [];

        foreach ($this->items as $item) {
            if (!is_array($item)) {
                $result[] = $item;
            } elseif ($depth === 1) {
                $result = array_merge($result, $item);
            } else {
                $result = array_merge($result, (new self($item))->flatten($depth - 1));
            }
        }

        return $result;
    }

    /**
     * Flip 交换键值
     */
    public function flip(): array
    {
        return array_flip($this->items);
    }

    /**
     * Keys 获取所有键
     */
    public function keys(): array
    {
        return array_keys($this->items);
    }

    /**
     * Values 获取所有值
     */
    public function values(): array
    {
        return array_values($this->items);
    }

    /**
     * Combine 组合键值
     */
    public function combine(array $values): array
    {
        return array_combine($this->items, $values);
    }

    /**
     * Merge 合并
     */
    public function merge(array $items): array
    {
        return array_merge($this->items, $items);
    }

    /**
     * Union 联合
     */
    public function union(array $items): array
    {
        return $this->items + $items;
    }

    /**
     * Diff 差集
     */
    public function diff(array $items): array
    {
        return array_diff($this->items, $items);
    }

    /**
     * Intersect 交集
     */
    public function intersect(array $items): array
    {
        return array_intersect($this->items, $items);
    }

    /**
     * Nth 取第 n 个元素
     */
    public function nth(int $step, int $offset = 0): array
    {
        $new = [];

        foreach ($this->items as $key => $item) {
            if ($key % $step === $offset) {
                $new[] = $item;
            }
        }

        return $new;
    }

    /**
     * JsonSerialize
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }

    /**
     * ToJson 转为 JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->items, $options);
    }

    /**
     * OffsetExists
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * OffsetGet
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    /**
     * OffsetSet
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * OffsetUnset
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    /**
     * GetIterator
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * ToArray 转为数组
     */
    public function toArray(): array
    {
        return $this->items;
    }

    /**
     * __toString
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * __debugInfo
     */
    public function __debugInfo(): array
    {
        return $this->items;
    }
}
