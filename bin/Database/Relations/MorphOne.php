<?php

declare(strict_types=1);

namespace Bin\Database\Relations;

use Bin\Database\Model;

/**
 * 多态一对一关系
 */
class MorphOne extends MorphMany
{
    public function getResults(): ?Model
    {
        return $this->query->first();
    }

    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }
        return $models;
    }

    public function match(array $models, iterable $results, string $relation): array
    {
        $dictionary = [];
        foreach ($results as $result) {
            $key = $result->getAttribute($this->morphId);
            if ($key !== null) {
                $dictionary[$key] = $result;
            }
        }

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key]);
            }
        }

        return $models;
    }
}
