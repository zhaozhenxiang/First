<?php

declare(strict_types=1);

namespace Bin\Database\Relations;

use Bin\Database\Model;
use Bin\Database\QueryBuilder;

/**
 * 多态一对一/一对多关系基类
 */
abstract class MorphOneOrMany extends Relation
{
    protected string $morphType;
    protected string $morphId;
    protected string $localKey;

    public function __construct(
        QueryBuilder $query,
        Model $parent,
        string $morphType,
        string $morphId,
        string $localKey
    ) {
        $this->morphType = $morphType;
        $this->morphId = $morphId;
        $this->localKey = $localKey;

        parent::__construct($query, $parent);
    }

    protected function addConstraints(): void
    {
        if ($this->constraints) {
            $this->query
                ->where($this->morphType, '=', $this->parent->getMorphClass())
                ->where($this->morphId, '=', $this->parent->getAttribute($this->localKey));
        }
    }

    public function addEagerConstraints(array $models): void
    {
        $this->query
            ->where($this->morphType, '=', $models[0]->getMorphClass())
            ->whereIn($this->morphId, $this->getKeys($models, $this->localKey));
    }

    protected function getKeys(array $models, string $key): array
    {
        $keys = [];
        foreach ($models as $model) {
            $value = $model->getAttribute($key);
            if ($value !== null) {
                $keys[] = $value;
            }
        }
        return array_unique($keys);
    }

    protected function buildDictionary(iterable $results): array
    {
        $dictionary = [];
        foreach ($results as $result) {
            $key = $result->getAttribute($this->morphId);
            if ($key !== null) {
                $dictionary[$key][] = $result;
            }
        }
        return $dictionary;
    }

    /**
     * 关系聚合子查询：直接实现（不能调用 Relation 基类——那是抛异常的占位），
     * 且必须带多态类型条件，否则会跨类型误计
     */
    public function getAggregateSubQuery(string $parentTable, string $column, string $function): string
    {
        $col = $function === 'count' ? '*' : $column;
        $relatedTable = $this->query->getTable();
        $morphClass = str_replace("'", "''", $this->parent->getMorphClass());

        return "SELECT {$function}({$col}) FROM {$relatedTable}"
            . " WHERE {$relatedTable}.{$this->morphId} = {$parentTable}.{$this->localKey}"
            . " AND {$relatedTable}.{$this->morphType} = '{$morphClass}'";
    }

    /**
     * 为 whereHas 生成 EXISTS 子查询：带多态类型条件
     *
     * 此前走 hasInternal 的通用回退（只比对 morphId），同 id 不同类型的行会
     * 跨类型泄漏进来。
     */
    public function getExistenceQuery(string $parentTable): array
    {
        $relatedTable = $this->query->getTable();
        $morphClass = str_replace("'", "''", $this->parent->getMorphClass());

        return [
            "SELECT 1 FROM {$relatedTable}"
                . " WHERE {$relatedTable}.{$this->morphId} = {$parentTable}.{$this->localKey}"
                . " AND {$relatedTable}.{$this->morphType} = '{$morphClass}'",
            [],
        ];
    }

    /**
     * 实例化未保存的相关模型（morphId 与 morphType 已接线）
     */
    public function make(array $attributes = []): Model
    {
        $modelClass = $this->query->getModelClass();
        $model = new $modelClass($attributes);

        $model->setAttribute($this->morphId, $this->parent->getAttribute($this->localKey));
        $model->setAttribute($this->morphType, $this->parent->getMorphClass());

        return $model;
    }

    /**
     * 保存相关模型（接线 morphId 与 morphType）
     */
    public function save(Model $model): bool
    {
        $model->setAttribute($this->morphId, $this->parent->getAttribute($this->localKey));
        $model->setAttribute($this->morphType, $this->parent->getMorphClass());

        return $model->save();
    }

    /**
     * 保存多个相关模型
     */
    public function saveMany(array $models): array
    {
        foreach ($models as $model) {
            $this->save($model);
        }

        return $models;
    }

    /**
     * 创建相关模型
     */
    public function create(array $attributes): Model
    {
        $model = $this->make($attributes);

        if (!$this->save($model)) {
            throw new \RuntimeException('Failed to create [' . $this->query->getModelClass() . '] via morph relation.');
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

    public function getForeignKeyName(): string
    {
        return $this->morphId;
    }

    public function getLocalKey(): string
    {
        return $this->localKey;
    }

    public function getMorphType(): string
    {
        return $this->morphType;
    }

    public function getMorphId(): string
    {
        return $this->morphId;
    }
}
