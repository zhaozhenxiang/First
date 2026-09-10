<?php

declare(strict_types=1);

namespace Bin\Database\Relations;

use Bin\Database\Collection;
use Bin\Database\Model;
use Bin\Database\QueryBuilder;
use RuntimeException;

/**
 * Has Many 关系
 */
class HasMany extends HasOneOrMany
{
    /**
     * 获取结果
     */
    public function getResults(): mixed
    {
        return $this->query->get();
    }

    /**
     * 初始化关系
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, new Collection());
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
                $model->setRelation($relation, new Collection($dictionary[$key]));
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
            throw new RuntimeException('Failed to create [' . $this->related . '] via hasMany relation.');
        }

        return $model;
    }

    /**
     * 创建多个相关模型
     */
    public function createMany(array $records): array
    {
        $models = [];

        foreach ($records as $record) {
            $models[] = $this->create($record);
        }

        return $models;
    }
}
