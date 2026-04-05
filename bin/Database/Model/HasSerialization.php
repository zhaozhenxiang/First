<?php

declare(strict_types=1);

namespace Bin\Database\Model;

/**
 * 序列化 Trait
 */
trait HasSerialization
{
    /**
     * 转为数组
     */
    public function toArray(): array
    {
        $array = $this->attributes;

        // 隐藏属性
        if (!empty($this->hidden)) {
            $array = array_diff_key($array, array_flip($this->hidden));
        }

        // 只显示指定属性
        if (!empty($this->visible)) {
            $array = array_intersect_key($array, array_flip($this->visible));
        }

        // 追加计算属性
        foreach ($this->appends as $key) {
            $array[$key] = $this->getAttribute($key);
        }

        return $array;
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
}
