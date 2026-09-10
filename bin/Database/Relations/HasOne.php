<?php

declare(strict_types=1);

namespace Bin\Database\Relations;

use Bin\Database\Model;
use Bin\Database\QueryBuilder;
use RuntimeException;

/**
 * Has One 关系
 */
class HasOne extends HasOneOrMany
{
    /**
     * 获取单个结果
     */
    public function getResults(): ?Model
    {
        return $this->query->first();
    }

    /**
     * 初始化关系
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }

    /**
     * 匹配关系
     */
    public function match(array $models, iterable $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);

            if (isset($dictionary[$key])) {
                $model->setRelation($relation, reset($dictionary[$key]));
            }
        }

        return $models;
    }

    /**
     * 保存相关模型
     */
    public function save(Model $model): bool
    {
        $model->setAttribute($this->foreignKey, $this->parent->getKey());

        return $model->save();
    }

    /**
     * 创建相关模型
     */
    public function create(array $attributes): Model
    {
        $model = new $this->related($attributes);

        if (!$this->save($model)) {
            throw new RuntimeException('Failed to create [' . $this->related . '] via hasOne relation.');
        }

        return $model;
    }
}
