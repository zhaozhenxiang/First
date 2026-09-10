<?php

declare(strict_types=1);

namespace Bin\Database\Relations;

use Bin\Database\Model;
use Bin\Database\QueryBuilder;

/**
 * Belongs To 关系
 */
class BelongsTo extends Relation
{
    /**
     * 相关模型类
     */
    protected string $related;

    /**
     * 父键
     */
    protected string $parentKey;

    /**
     * 构造函数
     */
    public function __construct(QueryBuilder $query, Model $parent, string $foreignKey, string $parentKey, string $related)
    {
        $this->related = $related;
        $this->parentKey = $parentKey;
        $this->foreignKey = $foreignKey;

        parent::__construct($query, $parent);
    }

    /**
     * 添加约束
     */
    protected function addConstraints(): void
    {
        if ($this->constraints) {
            $foreignValue = $this->parent->getAttribute($this->foreignKey);

            if (!is_null($foreignValue)) {
                $this->query->where($this->parentKey, '=', $foreignValue);
            } else {
                // 外键为 null 时不存在关联模型；用恒假条件代替"无约束"，
                // 否则 getResults() 会错误地返回目标表的第一行
                $this->query->whereRaw('1 = 0');
            }
        }
    }

    /**
     * 添加渴望加载约束
     */
    public function addEagerConstraints(array $models): void
    {
        $keys = $this->getKeys($models, $this->foreignKey);

        if (!empty($keys)) {
            $this->query->whereIn($this->parentKey, $keys);
        }
    }

    /**
     * 获取所有外键
     */
    protected function getKeys(array $models, string $key): array
    {
        $keys = [];

        foreach ($models as $model) {
            if (!is_null($value = $model->getAttribute($key))) {
                $keys[] = $value;
            }
        }

        return array_unique($keys);
    }

    /**
     * 获取结果
     */
    public function getResults(): ?Model
    {
        return $this->query->first();
    }

    /**
     * 初始化关系
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }

    /**
     * 匹配关系
     */
    public function match(array $models, array $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->foreignKey);

            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key]);
            }
        }

        return $models;
    }

    /**
     * 构建字典
     */
    protected function buildDictionary(array $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $dictionary[$result->getAttribute($this->parentKey)] = $result;
        }

        return $dictionary;
    }

    /**
     * 关联父模型
     */
    public function associate(Model $model): Model
    {
        $this->parent->setAttribute($this->foreignKey, $model->getKey());

        return $this->parent;
    }

    /**
     * 取消关联
     */
    public function dissociate(): Model
    {
        $this->parent->setAttribute($this->foreignKey, null);

        return $this->parent;
    }

    /**
     * 获取父键
     */
    public function getParentKey(): string
    {
        return $this->parentKey;
    }
}
