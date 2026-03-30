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
                ->where($this->morphType, '=', get_class($this->parent))
                ->where($this->morphId, '=', $this->parent->getAttribute($this->localKey));
        }
    }

    public function addEagerConstraints(array $models): void
    {
        $this->query
            ->where($this->morphType, '=', get_class($models[0]))
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

    protected function buildDictionary(array $results): array
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
