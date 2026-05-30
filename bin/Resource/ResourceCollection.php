<?php

declare(strict_types=1);

namespace Bin\Resource;

use Bin\App\App;
use Bin\Response\Response;
use Bin\Response\ResponseFactory;
use Countable;
use IteratorAggregate;
use JsonSerializable;

/**
 * 资源集合
 *
 * 用于处理多个资源的集合，支持分页和额外元数据
 */
class ResourceCollection implements JsonSerializable, Countable, IteratorAggregate
{
    /**
     * 原始资源集合
     */
    protected mixed $resource;

    /**
     * 资源类名
     */
    protected string $collects;

    /**
     * 附加的元数据
     */
    protected array $with = [];

    /**
     * 需要包含的资源
     */
    protected array $includes = [];

    /**
     * 需要隐藏的字段
     */
    protected array $hidden = [];

    /**
     * 可见字段（白名单）
     */
    protected ?array $only = null;

    /**
     * 分页信息
     */
    protected ?array $pagination = null;

    /**
     * 构造函数
     */
    public function __construct(mixed $resource, string $collects = JsonResource::class)
    {
        $this->resource = $resource;
        $this->collects = $collects;
    }

    /**
     * 创建资源集合
     */
    public static function make(mixed $resource, string $collects = JsonResource::class): static
    {
        return new static($resource, $collects);
    }

    /**
     * 添加附加元数据
     */
    public function with(array $data): static
    {
        $this->with = array_merge($this->with, $data);

        return $this;
    }

    /**
     * 设置需要包含的资源
     */
    public function includes(array $includes): static
    {
        $this->includes = $includes;

        return $this;
    }

    /**
     * 设置需要隐藏的字段
     */
    public function hide(array $fields): static
    {
        $this->hidden = array_merge($this->hidden, $fields);

        return $this;
    }

    /**
     * 设置只显示指定字段
     */
    public function only(array $fields): static
    {
        $this->only = $fields;

        return $this;
    }

    /**
     * 设置分页信息
     */
    public function pagination(array $pagination): static
    {
        $this->pagination = $pagination;

        return $this;
    }

    /**
     * 指定 JSON 序列化时如何表示
     */
    public function jsonSerialize(): array
    {
        $data = $this->collect();

        // 应用 includes
        foreach ($this->includes as $include) {
            if (method_exists($this->collects, $include)) {
                $data[$include] = $this->$include();
            }
        }

        // 合并附加数据
        $response = array_merge($this->with, ['data' => $data]);

        // 添加分页信息
        if ($this->pagination !== null) {
            $response['meta'] = array_merge($response['meta'] ?? [], [
                'pagination' => $this->pagination,
            ]);
        }

        return $response;
    }

    /**
     * 收集资源
     */
    public function collect(): array
    {
        $resources = [];

        foreach ($this->resource as $item) {
            $resource = new $this->collects($item);

            // 应用过滤
            if ($this->only !== null) {
                $resource->only($this->only);
            }

            if (!empty($this->hidden)) {
                $resource->hide($this->hidden);
            }

            if (!empty($this->includes)) {
                $resource->includes($this->includes);
            }

            // 使用 jsonSerialize() 以应用过滤器
            $resources[] = $resource->jsonSerialize();
        }

        return $resources;
    }

    /**
     * 转换为 JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this, JSON_THROW_ON_ERROR | $options);
    }

    /**
     * 转换为响应
     */
    public function toResponse(int $status = 200): Response
    {
        return App::getInstance()
            ->make(ResponseFactory::class)
            ->json($this, $status);
    }

    /**
     * 获取原始资源
     */
    public function getResource(): mixed
    {
        return $this->resource;
    }

    /**
     * 获取资源数量
     */
    public function count(): int
    {
        if (is_array($this->resource) || $this->resource instanceof \Countable) {
            return count($this->resource);
        }

        return 0;
    }

    /**
     * 获取迭代器
     */
    public function getIterator(): \Traversable
    {
        foreach ($this->resource as $item) {
            yield new $this->collects($item);
        }
    }

    /**
     * 获取第一个资源
     */
    public function first(): ?JsonResource
    {
        foreach ($this->resource as $item) {
            return new $this->collects($item);
        }

        return null;
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return $this->collect();
    }

    /**
     * 检查集合是否为空
     */
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * 检查集合是否不为空
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * 映射集合中的每个项目
     */
    public function map(callable $callback): array
    {
        $results = [];

        foreach ($this->resource as $item) {
            $results[] = $callback(new $this->collects($item));
        }

        return $results;
    }

    /**
     * 过滤集合
     */
    public function filter(callable $callback): array
    {
        $results = [];

        foreach ($this->resource as $item) {
            $resource = new $this->collects($item);

            if ($callback($resource)) {
                $results[] = $resource;
            }
        }

        return $results;
    }

    /**
     * 获取指定范围的资源
     */
    public function slice(int $offset, ?int $length = null): array
    {
        $items = is_array($this->resource)
            ? array_slice($this->resource, $offset, $length)
            : array_slice(iterator_to_array($this->resource), $offset, $length);

        $results = [];

        foreach ($items as $item) {
            $results[] = new $this->collects($item);
        }

        return $results;
    }

    /**
     * 获取前 N 个资源
     */
    public function take(int $limit): array
    {
        return $this->slice(0, $limit);
    }

    /**
     * 跳过前 N 个资源
     */
    public function skip(int $count): array
    {
        return $this->slice($count);
    }

    /**
     * 字符串输出
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}
