<?php

declare(strict_types=1);

namespace Bin\Database\Relations;

use Bin\Database\Model;
use Bin\Database\QueryBuilder;

/**
 * 多态多对多关系
 */
class MorphToMany extends BelongsToMany
{
    protected string $morphName;
    protected string $morphType;
    protected bool $inverse;

    public function __construct(
        QueryBuilder $query,
        Model $parent,
        string $name,
        string $table,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey,
        string $relatedKey,
        bool $inverse = false
    ) {
        $this->morphName = $name;
        $this->morphType = $name . '_type';
        $this->inverse = $inverse;

        parent::__construct($query, $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey);
    }

    protected function addConstraints(): void
    {
        $this->performJoin();

        if ($this->constraints) {
            $this->query
                ->where("{$this->table}.{$this->foreignPivotKey}", '=', $this->parent->getKey())
                ->where("{$this->table}.{$this->morphType}", '=', $this->morphClassValue());
        }
    }

    public function addEagerConstraints(array $models): void
    {
        $keys = $this->getKeys($models, $this->parentKey);

        $this->query
            ->whereIn("{$this->table}.{$this->foreignPivotKey}", $keys)
            ->where("{$this->table}.{$this->morphType}", '=', $this->morphClassValue());
    }

    /**
     * 中间表存储的多态类型值（考虑 morphMap 别名）
     *
     * 正向（morphToMany）存父模型类型；反向（morphedByMany）存相关模型类型。
     */
    protected function morphClassValue(): string
    {
        if ($this->inverse) {
            $relatedClass = $this->query->getModelClass();

            return (new $relatedClass())->getMorphClass();
        }

        return $this->parent->getMorphClass();
    }

    public function attach(int $id, array $pivotData = []): bool
    {
        $insert = [
            $this->foreignPivotKey => $this->parent->getKey(),
            $this->relatedPivotKey => $id,
            $this->morphType => $this->morphClassValue(),
        ];

        $insert = array_merge($insert, $this->timestampPivotColumns(), $pivotData);

        return $this->newPivotQuery()->insert($insert);
    }

    /**
     * 中间表查询必须带多态类型约束，
     * 否则共享同一中间表的其他类型行会被 sync/detach 误删
     */
    protected function newPivotQuery(): QueryBuilder
    {
        $morphClass = str_replace("'", "''", $this->morphClassValue());

        return parent::newPivotQuery()
            ->where("{$this->table}.{$this->morphType}", '=', $morphClass);
    }

    /**
     * 关系聚合子查询：附加多态类型条件
     */
    public function getAggregateSubQuery(string $parentTable, string $column, string $function): string
    {
        $base = parent::getAggregateSubQuery($parentTable, $column, $function);

        $morphClass = str_replace("'", "''", $this->morphClassValue());

        return $base . " AND {$this->table}.{$this->morphType} = '{$morphClass}'";
    }

    /**
     * 为 whereHas 生成 EXISTS 子查询：附加多态类型条件
     */
    public function getExistenceQuery(string $parentTable): array
    {
        [$sql, $bindings] = parent::getExistenceQuery($parentTable);

        $morphClass = str_replace("'", "''", $this->morphClassValue());

        return [$sql . " AND {$this->table}.{$this->morphType} = '{$morphClass}'", $bindings];
    }

    public function getForeignKeyName(): string
    {
        return $this->foreignPivotKey;
    }

    public function getLocalKey(): string
    {
        return $this->parentKey;
    }
}
