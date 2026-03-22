<?php

declare(strict_types=1);

namespace Bin\Resource;

use ArrayObject;
use JsonSerializable;

/**
 * JSON Resource 基类
 *
 * 用于将模型或数据转换为 API JSON 响应格式
 */
abstract class JsonResource implements JsonSerializable
{
    /**
     * 原始资源数据
     */
    protected mixed $resource;

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
     * 构造函数
     */
    public function __construct(mixed $resource)
    {
        $this->resource = $resource;
    }

    /**
     * 创建资源实例
     */
    public static function make(mixed $resource): static
    {
        return new static($resource);
    }

    /**
     * 创建资源集合
     */
    public static function collection(mixed $resource): ResourceCollection
    {
        return new ResourceCollection($resource, static::class);
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
     * 转换资源为数组
     *
     * 子类必须实现此方法
     */
    abstract public function toArray(): array;

    /**
     * 指定 JSON 序列化时如何表示
     */
    public function jsonSerialize(): array
    {
        $data = $this->toArray();

        // 应用 only 过滤
        if ($this->only !== null) {
            $data = array_intersect_key($data, array_flip($this->only));
        }

        // 应用 hidden 过滤
        foreach ($this->hidden as $field) {
            unset($data[$field]);
        }

        // 应用 includes
        foreach ($this->includes as $include) {
            if (method_exists($this, $include)) {
                $data[$include] = $this->$include();
            }
        }

        // 合并附加数据
        if (!empty($this->with)) {
            $data = array_merge($data, $this->with);
        }

        return $data;
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
    public function toResponse(int $status = 200): \Bin\Response\Response
    {
        return new \Bin\Response\Response($this, $status);
    }

    /**
     * 获取原始资源
     */
    public function getResource(): mixed
    {
        return $this->resource;
    }

    /**
     * 动态访问资源属性
     */
    public function __get(string $key): mixed
    {
        if (is_array($this->resource)) {
            return $this->resource[$key] ?? null;
        }

        if (is_object($this->resource)) {
            return $this->resource->$key ?? null;
        }

        return null;
    }

    /**
     * 动态调用资源方法
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (is_object($this->resource) && method_exists($this->resource, $method)) {
            return $this->resource->$method(...$parameters);
        }

        return null;
    }

    /**
     * 资源是否存在
     */
    public function exists(): bool
    {
        if (is_array($this->resource)) {
            return !empty($this->resource);
        }

        if (is_object($this->resource)) {
            return isset($this->resource->id);
        }

        return $this->resource !== null;
    }

    /**
     * 获取资源 ID
     */
    public function id(): mixed
    {
        if (is_array($this->resource)) {
            return $this->resource['id'] ?? null;
        }

        if (is_object($this->resource)) {
            return $this->resource->id ?? null;
        }

        return null;
    }

    /**
     * 将资源转换为数组时调用
     */
    protected function when(callable $condition, mixed $value, mixed $default = null): mixed
    {
        return $condition() ? $value : $default;
    }

    /**
     * 条件合并数据
     */
    protected function mergeWhen(callable $condition, array $data): array
    {
        return $condition() ? $data : [];
    }

    /**
     * 字符串输出
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}
