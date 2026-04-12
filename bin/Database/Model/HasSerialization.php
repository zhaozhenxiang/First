<?php

declare(strict_types=1);

namespace Bin\Database\Model;

/**
 * 序列化 Trait
 */
trait HasSerialization
{
    /**
     * 转为数组（包含已加载的关系和 append 属性）
     */
    public function toArray(): array
    {
        // 1. 处理模型属性
        $array = $this->attributesToArray();

        // 2. 追加计算属性
        foreach ($this->appends as $key) {
            $array[$key] = $this->getAttribute($key);
        }

        // 3. 序列化已加载的关系
        $array = array_merge($array, $this->relationsToArray());

        return $array;
    }

    /**
     * 属性转数组（应用 hidden/visible 过滤和 cast）
     */
    public function attributesToArray(): array
    {
        $array = $this->attributes;

        // 应用类型转换
        foreach ($array as $key => $value) {
            if ($this->hasCast($key)) {
                $array[$key] = $this->castAttribute($key, $value);
            }
        }

        // 隐藏属性
        if (!empty($this->hidden)) {
            $array = array_diff_key($array, array_flip($this->hidden));
        }

        // 只显示指定属性
        if (!empty($this->visible)) {
            $array = array_intersect_key($array, array_flip($this->visible));
        }

        return $array;
    }

    /**
     * 关系转数组
     */
    public function relationsToArray(): array
    {
        $result = [];

        $relations = property_exists($this, 'relations') ? $this->relations : [];

        foreach ($relations as $key => $value) {
            if ($value instanceof self) {
                $result[$key] = $value->toArray();
            } elseif ($value instanceof \Bin\Database\Collection) {
                $result[$key] = $value->map(fn($item) => $item instanceof self ? $item->toArray() : $item)->toArray();
            } elseif (is_array($value)) {
                $result[$key] = array_map(fn($item) => $item instanceof self ? $item->toArray() : $item, $value);
            } elseif ($value !== null) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * 追加计算属性到数组输出
     */
    public function append(string|array $attributes): self
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();

        foreach ($attributes as $attribute) {
            if (!in_array($attribute, $this->appends, true)) {
                $this->appends[] = $attribute;
            }
        }

        return $this;
    }

    /**
     * 设置 appends 属性
     */
    public function setAppends(array $appends): self
    {
        $this->appends = $appends;
        return $this;
    }

    /**
     * 转为 JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * JsonSerializable
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * __toString
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * 仅获取属性数组（不含关系，向后兼容）
     */
    public function attributesOnly(): array
    {
        return $this->attributesToArray();
    }
}
