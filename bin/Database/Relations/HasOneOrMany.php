<?php

declare(strict_types=1);

namespace Bin\Database\Relations;

use Bin\Database\Model;
use Bin\Database\QueryBuilder;

/**
 * Has One Or Many 关系基类
 */
abstract class HasOneOrMany extends Relation
{
    /**
     * 相关模型类
     */
    protected string $related;

    /**
     * 本地键
     */
    protected string $localKey;

    /**
     * 构造函数
     */
    public function __construct(QueryBuilder $query, Model $parent, string $foreignKey, string $localKey)
    {
        $this->localKey = $localKey;
        $this->foreignKey = $foreignKey;

        parent::__construct($query, $parent);
    }

    /**
     * 添加约束
     */
    protected function addConstraints(): void
    {
        if ($this->constraints) {
            $this->query->where($this->foreignKey, '=', $this->parent->getKey());
        }
    }

    /**
     * 添加渴望加载约束
     */
    public function addEagerConstraints(array $models): void
    {
        $keys = $this->getKeys($models, $this->localKey);

        $this->query->whereIn($this->foreignKey, $keys);
    }

    /**
     * 获取所有父模型的键
     */
    protected function getKeys(array $models, ?string $key = null): array
    {
        $keys = [];

        foreach ($models as $model) {
            if (!is_null($value = $model->getAttribute($key ?? $this->localKey))) {
                $keys[] = $value;
            }
        }

        return array_unique($keys);
    }

    /**
     * 构建字典
     */
    protected function buildDictionary(iterable $results): array
    {
        $dictionary = [];

        $foreign = $this->foreignKey;

        foreach ($results as $result) {
            $key = $result->getAttribute($foreign);

            if ($key !== null) {
                $dictionary[$key][] = $result;
            }
        }

        return $dictionary;
    }

    /**
     * 获取本地键
     */
    public function getLocalKey(): string
    {
        return $this->localKey;
    }

    /**
     * 获取外键
     */
    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    /**
     * 关系聚合子查询：直接外键关联
     */
    public function getAggregateSubQuery(string $parentTable, string $column, string $function): string
    {
        $relatedTable = $this->query->getTable();
        $col = $function === 'count' ? '*' : $column;

        return "SELECT {$function}({$col}) FROM {$relatedTable}"
            . " WHERE {$relatedTable}.{$this->foreignKey} = {$parentTable}.{$this->localKey}";
    }

    /**
     * 保存多个模型到关系
     */
    public function saveMany(array $models): array
    {
        foreach ($models as $model) {
            $model->setAttribute($this->foreignKey, $this->parent->getKey());
            $model->save();
        }

        return $models;
    }
}
