<?php

declare(strict_types=1);

namespace Bin\Database\Relations;

use Bin\Database\Collection;

/**
 * 多态一对多关系
 */
class MorphMany extends MorphOneOrMany
{
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
}
