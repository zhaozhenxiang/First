<?php

declare(strict_types=1);

namespace Bin\Database\Relations;

use Bin\Database\Collection;

/**
 * 多态一对多关系
 */
class MorphMany extends MorphOneOrMany
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
}
