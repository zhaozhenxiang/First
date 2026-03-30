<?php

declare(strict_types=1);

namespace Bin\Database\Relations;

use Bin\Database\Model;
use Bin\Database\QueryBuilder;

/**
 * Has Many Through 远层一对多关系
 *
 * Country hasMany Posts through Users
 */
class HasManyThrough extends Relation
{
    protected string $throughParentClass;
    protected string $firstKey;
    protected string $secondKey;
    protected string $localKey;
    protected string $secondLocalKey;

    public function __construct(
        QueryBuilder $query,
        Model $parent,
        string $throughParentClass,
        string $firstKey,
        string $secondKey,
        string $localKey,
        string $secondLocalKey
    ) {
        $this->throughParentClass = $throughParentClass;
        $this->firstKey = $firstKey;
        $this->secondKey = $secondKey;
        $this->localKey = $localKey;
        $this->secondLocalKey = $secondLocalKey;

        parent::__construct($query, $parent);
    }

    protected function addConstraints(): void
    {
        if ($this->constraints) {
            $throughTable = (new $this->throughParentClass())->getTable();
            $farTable = $this->query->getTable();

            $this->query
                ->select("{$farTable}.*")
                ->selectRaw("{$throughTable}.{$this->firstKey} AS __through_key")
                ->join(
                    $throughTable,
                    "{$farTable}.{$this->secondKey}",
                    '=',
                    "{$throughTable}.{$this->secondLocalKey}"
                )
                ->where(
                    "{$throughTable}.{$this->firstKey}",
                    '=',
                    $this->parent->getAttribute($this->localKey)
                );
        }
    }

    public function addEagerConstraints(array $models): void
    {
        // 重置查询状态（清除 addConstraints 添加的 select/join/where）
        $this->query->resetSelect();

        $throughTable = (new $this->throughParentClass())->getTable();
        $farTable = $this->query->getTable();
        $keys = $this->getKeys($models, $this->localKey);

        $this->query
            ->select("{$farTable}.*")
            ->selectRaw("{$throughTable}.{$this->firstKey} AS __through_key")
            ->join(
                $throughTable,
                "{$farTable}.{$this->secondKey}",
                '=',
                "{$throughTable}.{$this->secondLocalKey}"
            )
            ->whereIn("{$throughTable}.{$this->firstKey}", $keys);
    }

    public function getResults(): mixed
    {
        return $this->query->get();
    }

    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, []);
        }
        return $models;
    }

    public function match(array $models, array $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key]);
            }
        }

        return $models;
    }

    protected function buildDictionary(array $results): array
    {
        $dictionary = [];
        foreach ($results as $model) {
            $throughKey = $model->getAttribute('__through_key');
            if ($throughKey !== null) {
                $model->offsetUnset('__through_key');
                $dictionary[$throughKey][] = $model;
            }
        }
        return $dictionary;
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

    public function getForeignKeyName(): string
    {
        return $this->firstKey;
    }

    public function getLocalKey(): string
    {
        return $this->localKey;
    }

    /**
     * 为 whereHas 生成 EXISTS 子查询 SQL
     */
    public function getExistenceQuery(string $parentTable): array
    {
        $throughTable = (new $this->throughParentClass())->getTable();
        $farTable = $this->query->getTable();

        $sql = "SELECT 1 FROM {$farTable} "
             . "INNER JOIN {$throughTable} ON {$farTable}.{$this->secondKey} = {$throughTable}.{$this->secondLocalKey} "
             . "WHERE {$throughTable}.{$this->firstKey} = {$parentTable}.{$this->localKey}";

        return [$sql, []];
    }
}
