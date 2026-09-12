<?php

declare(strict_types=1);

namespace Bin\Database;

use Bin\Database\Model;
use Bin\Support\Collection as BaseCollection;

/**
 * 模型集合 - 通用集合之上叠加模型专属方法
 *
 * ORM 的查询与关系返回统一使用本类（懒加载与 eager 加载同类的
 * 不变量由测试保证）。通用方法全部继承自 Bin\Support\Collection。
 */
class Collection extends BaseCollection
{
    /**
     * 获取所有模型的主键（非模型元素映射为 null）
     */
    public function modelKeys(): array
    {
        return array_map(
            fn ($item) => $item instanceof Model ? $item->getKey() : null,
            $this->items
        );
    }

    /**
     * 按主键在集合内查找模型
     */
    public function find(mixed $key, ?string $column = null): ?Model
    {
        foreach ($this->items as $item) {
            if (!$item instanceof Model) {
                continue;
            }

            $column ??= $item->getKeyName();

            if ($item->getAttribute($column) === $key) {
                return $item;
            }
        }

        return null;
    }

    /**
     * 为集合内所有模型延迟加载关系（支持点号嵌套，委托给 Model::load()）
     */
    public function load(string|array $relations): self
    {
        $relations = is_array($relations) ? $relations : func_get_args();

        foreach ($this->items as $item) {
            if ($item instanceof Model) {
                $item->load($relations);
            }
        }

        return $this;
    }

    /**
     * 为集合内所有模型延迟加载关系计数
     */
    public function loadCount(string|array $relations): self
    {
        $relations = is_array($relations) ? $relations : func_get_args();

        foreach ($this->items as $item) {
            if ($item instanceof Model) {
                $item->loadCount($relations);
            }
        }

        return $this;
    }
}
