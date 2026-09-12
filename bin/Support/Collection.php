<?php

declare(strict_types=1);

namespace Bin\Support;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;

/**
 * 基础集合类 - 提供数组操作的便捷方法
 *
 * 通用层：不感知模型。ORM 的模型集合（Bin\Database\Collection）继承本类
 * 并叠加模型专属方法。方法语义与拆分前保持一致（pluck/groupBy/keyBy/combine
 * 返回原生数组等），行为对齐另行独立进行。
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
        return new static($items);
    }

    /**
     * 获取所有项目
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * 追加元素到末尾
     */
    public function push(mixed $value): self
    {
        $this->items[] = $value;

        return $this;
    }

    /**
     * 按 key 设置元素
     */
    public function put(mixed $key, mixed $value): self
    {
        $this->items[$key] = $value;

        return $this;
    }

    /**
     * 按 key 获取元素
     */
    public function get(mixed $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    /**
     * 按 key 移除元素
     */
    public function forget(mixed $key): self
    {
        unset($this->items[$key]);

        return $this;
    }

    /**
     * 只保留指定 key
     */
    public function only(array|string $keys): self
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        return new static(array_intersect_key($this->items, array_flip($keys)));
    }

    /**
     * 排除指定 key
     */
    public function except(array|string $keys): self
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        return new static(array_diff_key($this->items, array_flip($keys)));
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
    public function first(?callable $callback = null, mixed $default = null): mixed
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
    public function last(?callable $callback = null, mixed $default = null): mixed
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
    public function pop(): self
    {
        return new static(array_values(array_slice($this->items, 0, -1)));
    }

    /**
     * Map 遍历
     */
    public function map(callable $callback): self
    {
        return new static(array_map($callback, $this->items));
    }

    /**
     * Filter 过滤
     */
    public function filter(callable $callback): self
    {
        return new static(array_values(array_filter($this->items, $callback)));
    }

    /**
     * Reject 拒绝
     */
    public function reject(callable $callback): self
    {
        return new static(array_values(array_filter($this->items, fn($item) => !$callback($item))));
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
    public function slice(int $offset, ?int $length = null): self
    {
        return new static(array_slice($this->items, $offset, $length));
    }

    /**
     * Take 取前 N 个
     */
    public function take(int $limit): self
    {
        return new static(array_slice($this->items, 0, $limit));
    }

    /**
     * Skip 跳过 N 个
     */
    public function skip(int $offset): self
    {
        return new static(array_slice($this->items, $offset));
    }

    /**
     * Chunk 分块
     */
    public function chunk(int $size): self
    {
        return new static(array_map(fn($chunk) => new static($chunk), array_chunk($this->items, $size)));
    }

    /**
     * Sort 排序
     */
    public function sort(?callable $callback = null): self
    {
        $items = $this->items;

        if ($callback === null) {
            sort($items);
        } else {
            usort($items, $callback);
        }

        return new static($items);
    }

    /**
     * SortBy 按 key 排序
     */
    public function sortBy(string|callable $key, bool $descending = false): self
    {
        $results = $this->items;

        usort($results, function ($a, $b) use ($key) {
            $aValue = is_callable($key) ? $key($a) : (is_array($a) ? $a[$key] : $a->{$key});
            $bValue = is_callable($key) ? $key($b) : (is_array($b) ? $b[$key] : $b->{$key});

            return $aValue <=> $bValue;
        });

        return new static($descending ? array_reverse($results) : $results);
    }

    /**
     * Reverse 反转
     */
    public function reverse(): self
    {
        return new static(array_reverse($this->items));
    }

    /**
     * Shuffle 随机打乱
     */
    public function shuffle(): self
    {
        $items = $this->items;
        shuffle($items);
        return new static($items);
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
    public function where(string|callable $key, mixed $operator = null, mixed $value = null): self
    {
        if (is_callable($key)) {
            return new static(array_filter($this->items, $key));
        }

        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        return new static(array_filter($this->items, function ($item) use ($key, $operator, $value) {
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
        }));
    }

    /**
     * WhereIn 筛选
     */
    public function whereIn(string $key, array $values): self
    {
        return new static(array_filter($this->items, function ($item) use ($key, $values) {
            $itemValue = is_array($item) ? ($item[$key] ?? null) : ($item->{$key} ?? null);
            return in_array($itemValue, $values, true);
        }));
    }

    /**
     * WhereNull 筛选空值
     */
    public function whereNull(string $key): self
    {
        return new static(array_filter($this->items, function ($item) use ($key) {
            $itemValue = is_array($item) ? ($item[$key] ?? null) : ($item->{$key} ?? null);
            return $itemValue === null;
        }));
    }

    /**
     * WhereNotNull 筛选非空值
     */
    public function whereNotNull(string $key): self
    {
        return new static(array_filter($this->items, function ($item) use ($key) {
            $itemValue = is_array($item) ? ($item[$key] ?? null) : ($item->{$key} ?? null);
            return $itemValue !== null;
        }));
    }

    /**
     * FirstWhere 第一个匹配
     */
    public function firstWhere(string $key, mixed $operator, mixed $value = null): mixed
    {
        $items = $this->where($key, $operator, $value);
        return $items->isEmpty() ? null : reset($items->all());
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
    public function unique(string|callable|null $key = null): self
    {
        if ($key === null) {
            return new static(array_values(array_unique($this->items, SORT_REGULAR)));
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

        return new static($unique);
    }

    /**
     * Collapse 折叠多维数组
     */
    public function collapse(): self
    {
        $results = [];

        foreach ($this->items as $values) {
            if (!is_array($values)) {
                continue;
            }

            $results = array_merge($results, $values);
        }

        return new static($results);
    }

    /**
     * Flatten 扁平化
     */
    public function flatten(int $depth = PHP_INT_MAX): self
    {
        $result = [];

        foreach ($this->items as $item) {
            if (!is_array($item)) {
                $result[] = $item;
            } elseif ($depth === 1) {
                $result = array_merge($result, $item);
            } else {
                $result = array_merge($result, (new static($item))->flatten($depth - 1)->all());
            }
        }

        return new static($result);
    }

    /**
     * Flip 交换键值
     */
    public function flip(): self
    {
        return new static(array_flip($this->items));
    }

    /**
     * Keys 获取所有键
     */
    public function keys(): self
    {
        return new static(array_keys($this->items));
    }

    /**
     * Values 获取所有值
     */
    public function values(): self
    {
        return new static(array_values($this->items));
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
    public function merge(array $items): self
    {
        return new static(array_merge($this->items, $items));
    }

    /**
     * Union 联合
     */
    public function union(array $items): self
    {
        return new static($this->items + $items);
    }

    /**
     * Diff 差集
     */
    public function diff(array $items): self
    {
        return new static(array_values(array_diff($this->items, $items)));
    }

    /**
     * Intersect 交集
     */
    public function intersect(array $items): self
    {
        return new static(array_values(array_intersect($this->items, $items)));
    }

    /**
     * Nth 取第 n 个元素
     */
    public function nth(int $step, int $offset = 0): self
    {
        $new = [];

        foreach ($this->items as $key => $item) {
            if ($key % $step === $offset) {
                $new[] = $item;
            }
        }

        return new static($new);
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
