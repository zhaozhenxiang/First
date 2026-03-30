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
                ->where("{$this->table}.{$this->morphType}", '=', $this->getMorphClass());
        }
    }

    public function addEagerConstraints(array $models): void
    {
        $keys = $this->getKeys($models, $this->parentKey);

        $this->query
            ->whereIn("{$this->table}.{$this->foreignPivotKey}", $keys)
            ->where("{$this->table}.{$this->morphType}", '=', $this->getMorphClass());
    }

    protected function getMorphClass(): string
    {
        if ($this->inverse) {
            return $this->query->getModelClass();
        }
        return get_class($this->parent);
    }

    public function attach(int $id, array $pivotData = []): bool
    {
        $insert = [
            $this->foreignPivotKey => $this->parent->getKey(),
            $this->relatedPivotKey => $id,
            $this->morphType => $this->getMorphClass(),
        ];

        $insert = array_merge($insert, $pivotData);

        return $this->newPivotQuery()->insert($insert);
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
