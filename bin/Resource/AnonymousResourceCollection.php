<?php

declare(strict_types=1);

namespace Bin\Resource;

/**
 * 匿名资源集合
 *
 * 用于不需要定义专门资源类的简单场景
 */
class AnonymousResourceCollection extends ResourceCollection
{
    /**
     * 资源转换回调
     */
    protected $transformer = null;

    /**
     * 构造函数
     */
    public function __construct(mixed $resource, $transformer = null)
    {
        parent::__construct($resource);

        $this->transformer = $transformer;
    }

    /**
     * 创建匿名资源集合
     */
    public static function make(mixed $resource, $transformer = null): static
    {
        return new static($resource, $transformer);
    }

    /**
     * 收集资源
     */
    public function collect(): array
    {
        if ($this->transformer !== null) {
            $results = [];

            foreach ($this->resource as $item) {
                $results[] = call_user_func($this->transformer, $item);
            }

            return $results;
        }

        // 默认直接返回资源
        return is_array($this->resource) ? $this->resource : iterator_to_array($this->resource);
    }
}
