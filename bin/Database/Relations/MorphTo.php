<?php

declare(strict_types=1);

namespace Bin\Database\Relations;

use Bin\Database\Collection;
use Bin\Database\Model;
use Bin\Database\QueryBuilder;

/**
 * 多态逆向关系（MorphTo）
 *
 * 当子模型包含 commentable_type 和 commentable_id 列时，
 * morphTo() 动态解析父模型。
 *
 * 例如 Comment 模型：
 *   public function commentable(): MorphTo
 *   {
 *       return $this->morphTo();
 *   }
 */
class MorphTo extends Relation
{
    /**
     * 多态类型列名
     */
    protected string $morphType;

    /**
     * 外键列名（如 commentable_id）
     */
    protected string $foreignKey;

    /**
     * 父模型的主键列名
     */
    protected string $ownerKey;

    /**
     * 关系名称
     */
    protected string $relation;

    /**
     * 构造函数
     *
     * @param QueryBuilder $query 查询构建器
     * @param Model $parent 父模型（持有 morph_type 和 morph_id 的模型）
     * @param string $morphType 多态类型列名
     * @param string $foreignKey 外键列名
     * @param string $ownerKey 被引用模型的主键列名
     * @param string $relation 关系名称
     */
    public function __construct(
        QueryBuilder $query,
        Model $parent,
        string $morphType,
        string $foreignKey,
        string $ownerKey,
        string $relation
    ) {
        $this->morphType = $morphType;
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;
        $this->relation = $relation;

        parent::__construct($query, $parent);
    }

    /**
     * 添加约束（用于延迟加载）
     */
    protected function addConstraints(): void
    {
        if ($this->constraints) {
            $type = $this->parent->getAttribute($this->morphType);
            $id = $this->parent->getAttribute($this->foreignKey);

            if ($type !== null && $id !== null) {
                $modelClass = $this->resolveMorphClass($type);
                $model = new $modelClass();
                $this->query->from($model->getTable());
                $this->query->where($this->ownerKey, '=', $id);
            } else {
                $this->query->whereRaw('1 = 0');
            }
        }
    }

    /**
     * 添加渴望加载约束（MorphTo 不使用标准流程）
     */
    public function addEagerConstraints(array $models): void
    {
        // MorphTo 的渴望加载由 eagerLoad() 方法单独处理
        // 这里不执行任何操作，避免标准流程产生错误查询
    }

    /**
     * 获取关系结果（延迟加载时使用）
     */
    public function getResults(): ?Model
    {
        $type = $this->parent->getAttribute($this->morphType);

        if ($type === null) {
            return null;
        }

        $id = $this->parent->getAttribute($this->foreignKey);

        if ($id === null) {
            return null;
        }

        $modelClass = $this->resolveMorphClass($type);
        $model = new $modelClass();
        $this->query->from($model->getTable());

        return $this->query->first();
    }

    /**
     * 初始化关系（将所有模型的关系设为 null）
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }

    /**
     * 匹配关系（由 eagerLoad() 单独处理，此方法为空操作）
     */
    public function match(array $models, iterable $results, string $relation): array
    {
        return $models;
    }

    /**
     * 渴望加载 MorphTo 关系
     *
     * 按 morph_type 分组，对每种类型分别批量查询，然后匹配回模型。
     * 这是 MorphTo 的核心渴望加载逻辑，绕过标准的 addEagerConstraints/getEager/match 流程。
     *
     * @param array $models 需要加载关系的模型列表
     * @param string $name 关系名称
     * @return array 更新后的模型列表
     */
    public function eagerLoad(array $models, string $name): array
    {
        // 初始化关系为 null
        $this->initRelation($models, $name);

        // 按 morph_type 分组，收集每组的 ID
        $grouped = [];
        foreach ($models as $model) {
            $type = $model->getAttribute($this->morphType);
            $id = $model->getAttribute($this->foreignKey);

            if ($type !== null && $id !== null) {
                $grouped[$type][$id] = true;
            }
        }

        if (empty($grouped)) {
            return $models;
        }

        // 对每种 morph_type 分别查询
        $allResults = [];
        foreach ($grouped as $type => $idMap) {
            $modelClass = $this->resolveMorphClass($type);

            if (!class_exists($modelClass)) {
                continue;
            }

            $instance = new $modelClass();
            $ids = array_keys($idMap);

            $query = $instance->newQuery();
            $query->from($instance->getTable());
            $results = $query->whereIn($this->ownerKey, $ids)->get();

            foreach ($results as $result) {
                $allResults[$modelClass][$result->getKey()] = $result;
            }
        }

        // 将结果匹配回模型
        foreach ($models as $model) {
            $type = $model->getAttribute($this->morphType);
            $id = $model->getAttribute($this->foreignKey);

            if ($type !== null && $id !== null) {
                $modelClass = $this->resolveMorphClass($type);

                if (isset($allResults[$modelClass][$id])) {
                    $model->setRelation($name, $allResults[$modelClass][$id]);
                }
            }
        }

        return $models;
    }

    /**
     * 获取渴望加载结果（覆盖基类，MorphTo 使用 eagerLoad 代替）
     */
    public function getEager(): mixed
    {
        return $this->get();
    }

    /**
     * 执行查询
     */
    public function get(): mixed
    {
        return $this->query->get();
    }

    /**
     * 解析多态类型到模型类名（考虑全局 morphMap 别名）
     */
    protected function resolveMorphClass(string $type): string
    {
        $map = Relation::getMorphMap();

        return $map[$type] ?? $type;
    }

    /**
     * 关联模型到父模型
     *
     * @param Model $model 要关联的模型
     * @return Model 父模型
     */
    public function associate(Model $model): Model
    {
        $this->parent->setAttribute($this->foreignKey, $model->getKey());
        // 存 morphClass（别名优先），与查询侧的解析保持一致
        $this->parent->setAttribute($this->morphType, $model->getMorphClass());

        return $this->parent;
    }

    /**
     * 取消关联
     *
     * @return Model 父模型
     */
    public function dissociate(): Model
    {
        $this->parent->setAttribute($this->foreignKey, null);
        $this->parent->setAttribute($this->morphType, null);

        return $this->parent;
    }

    /**
     * 获取外键列名
     */
    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    /**
     * 获取本地键名（MorphTo 中等同于外键）
     */
    public function getLocalKey(): string
    {
        return $this->foreignKey;
    }

    /**
     * 获取多态类型列名
     */
    public function getMorphType(): string
    {
        return $this->morphType;
    }

    /**
     * 获取父模型主键列名
     */
    public function getOwnerKey(): string
    {
        return $this->ownerKey;
    }

    /**
     * 获取关系名称
     */
    public function getRelationName(): string
    {
        return $this->relation;
    }
}
