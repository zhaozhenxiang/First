<?php

declare(strict_types=1);

namespace Bin\Database\Model;

use Bin\Database\Relations;
use Bin\Database\Collection;

/**
 * HasRelationships trait - 从 Model 中提取的关系管理逻辑
 */
trait HasRelationships
{
    /**
     * 已加载的关系
     */
    protected array $relations = [];

    /**
     * 获取关系值（供 getAttribute 调用）
     *
     * 如果关系已加载则直接返回，否则检查是否存在关系方法并动态加载
     */
    public function getRelationValue(string $key): mixed
    {
        // 如果关系已加载，直接返回
        if ($this->relationLoaded($key)) {
            return $this->relations[$key];
        }

        // 检查是否存在关系定义方法
        if (method_exists($this, $key)) {
            // 严格模式下报告懒加载违规
            if (method_exists($this, 'handleLazyLoadingViolation')) {
                $this->handleLazyLoadingViolation($key);
            }

            return $this->getRelationshipFromMethod($key);
        }

        return null;
    }

    /**
     * 从方法加载关系
     */
    protected function getRelationshipFromMethod(string $method): mixed
    {
        $relation = $this->$method();

        if (!$relation instanceof Relations\Relation) {
            return null;
        }

        $results = $relation->getResults();

        $this->setRelation($method, $results);

        return $results;
    }

    /**
     * 设置关系
     */
    public function setRelation(string $relation, mixed $value): self
    {
        $this->relations[$relation] = $value;

        return $this;
    }

    /**
     * 获取关系
     */
    public function getRelation(string $relation): mixed
    {
        return $this->relations[$relation] ?? null;
    }

    /**
     * 获取所有关系
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * 获取多态类名（考虑 morphMap 别名）
     */
    public function getMorphClass(): string
    {
        $className = static::class;

        foreach (Relations\Relation::getMorphMap() as $alias => $class) {
            if ($class === $className) {
                return $alias;
            }
        }

        return $className;
    }

    /**
     * 设置多个关系
     */
    public function setRelations(array $relations): self
    {
        $this->relations = $relations;

        return $this;
    }

    /**
     * 检查关系是否已加载
     */
    public function relationLoaded(string $key): bool
    {
        return isset($this->relations[$key]);
    }

    /**
     * 转为数组（包含关系）— 向后兼容，现在直接委托 toArray()
     *
     * @deprecated toArray() 现在已包含已加载的关系，可直接使用 toArray()
     */
    public function toArrayWithRelations(): array
    {
        return $this->toArray();
    }

    /**
     * 定义 Has One 关系
     */
    protected function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): \Bin\Database\Relations\HasOne
    {
        $instance = new $related();

        $foreignKey = $foreignKey ?? $this->getForeignKey();

        $localKey = $localKey ?? $this->getKeyName();

        $query = $instance->newQuery();

        return new \Bin\Database\Relations\HasOne($query, $this, $foreignKey, $localKey);
    }

    /**
     * 定义 Has Many 关系
     */
    protected function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): \Bin\Database\Relations\HasMany
    {
        $instance = new $related();

        $foreignKey = $foreignKey ?? $this->getForeignKey();

        $localKey = $localKey ?? $this->getKeyName();

        $query = $instance->newQuery();

        return new \Bin\Database\Relations\HasMany($query, $this, $foreignKey, $localKey);
    }

    /**
     * 定义 Belongs To 关系
     */
    protected function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null, ?string $relation = null): \Bin\Database\Relations\BelongsTo
    {
        $relation = $relation ?? $this->guessBelongsToRelation();

        $instance = new $related();

        // 外键默认取关系名的 snake_case + _id（如 user() → user_id）。
        // 不能用当前模型类名推导（那是 hasMany 侧的外键语义）
        $foreignKey = $foreignKey ?? $this->relationForeignKey($relation);

        $ownerKey = $ownerKey ?? $instance->getKeyName();

        $query = $instance->newQuery();

        return new \Bin\Database\Relations\BelongsTo($query, $this, $foreignKey, $ownerKey, $related);
    }

    /**
     * 根据关系名推导外键列（snake_case + _id）
     */
    protected function relationForeignKey(string $relation): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $relation)) . '_id';
    }

    /**
     * 定义 Belongs To Many 关系
     */
    protected function belongsToMany(
        string $related,
        ?string $table = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null
    ): \Bin\Database\Relations\BelongsToMany {
        $instance = new $related();

        $table = $table ?? $this->joiningTable($related);

        $foreignPivotKey = $foreignPivotKey ?? $this->getForeignKey();

        $relatedPivotKey = $relatedPivotKey ?? $instance->getForeignKey();

        $parentKey = $parentKey ?? $this->getKeyName();

        $relatedKey = $relatedKey ?? $instance->getKeyName();

        $query = $instance->newQuery();

        return new \Bin\Database\Relations\BelongsToMany(
            $query,
            $this,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey
        );
    }

    /**
     * 定义 Has One Through 远层一对一关系
     */
    protected function hasOneThrough(
        string $related,
        string $through,
        ?string $firstKey = null,
        ?string $secondKey = null,
        ?string $localKey = null,
        ?string $secondLocalKey = null
    ): \Bin\Database\Relations\HasOneThrough {
        $relatedInstance = new $related();
        $throughInstance = new $through();

        $firstKey = $firstKey ?? $this->getForeignKey();
        $secondKey = $secondKey ?? $throughInstance->getForeignKey();
        $localKey = $localKey ?? $this->getKeyName();
        $secondLocalKey = $secondLocalKey ?? $throughInstance->getKeyName();

        $query = $relatedInstance->newQuery();

        return new \Bin\Database\Relations\HasOneThrough(
            $query, $this, $through, $firstKey, $secondKey, $localKey, $secondLocalKey
        );
    }

    /**
     * 定义 Has Many Through 远层一对多关系
     */
    protected function hasManyThrough(
        string $related,
        string $through,
        ?string $firstKey = null,
        ?string $secondKey = null,
        ?string $localKey = null,
        ?string $secondLocalKey = null
    ): \Bin\Database\Relations\HasManyThrough {
        $relatedInstance = new $related();
        $throughInstance = new $through();

        $firstKey = $firstKey ?? $this->getForeignKey();
        $secondKey = $secondKey ?? $throughInstance->getForeignKey();
        $localKey = $localKey ?? $this->getKeyName();
        $secondLocalKey = $secondLocalKey ?? $throughInstance->getKeyName();

        $query = $relatedInstance->newQuery();

        return new \Bin\Database\Relations\HasManyThrough(
            $query, $this, $through, $firstKey, $secondKey, $localKey, $secondLocalKey
        );
    }

    /**
     * 定义多态一对一关系
     */
    protected function morphOne(string $related, string $name, ?string $type = null, ?string $id = null, ?string $localKey = null): Relations\MorphOne
    {
        $instance = new $related();
        $type = $type ?? $name . '_type';
        $id = $id ?? $name . '_id';
        $localKey = $localKey ?? $this->getKeyName();

        $query = $instance->newQuery();

        return new Relations\MorphOne(
            $query, $this, $type, $id, $localKey
        );
    }

    /**
     * 定义多态一对多关系
     */
    protected function morphMany(string $related, string $name, ?string $type = null, ?string $id = null, ?string $localKey = null): Relations\MorphMany
    {
        $instance = new $related();
        $type = $type ?? $name . '_type';
        $id = $id ?? $name . '_id';
        $localKey = $localKey ?? $this->getKeyName();

        $query = $instance->newQuery();

        return new Relations\MorphMany(
            $query, $this, $type, $id, $localKey
        );
    }

    /**
     * 定义多态多对多关系
     */
    protected function morphToMany(
        string $related,
        string $name,
        ?string $table = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null
    ): Relations\MorphToMany {
        $instance = new $related();
        $table = $table ?? $name . 's';
        $foreignPivotKey = $foreignPivotKey ?? $name . '_id';
        $relatedPivotKey = $relatedPivotKey ?? $instance->getForeignKey();
        $parentKey = $parentKey ?? $this->getKeyName();
        $relatedKey = $relatedKey ?? $instance->getKeyName();

        $query = $instance->newQuery();

        return new Relations\MorphToMany(
            $query, $this, $name, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey
        );
    }

    /**
     * 定义多态逆向关系（MorphTo）
     */
    protected function morphTo(?string $name = null, ?string $type = null, ?string $id = null): Relations\MorphTo
    {
        if ($name === null) {
            $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
            $name = $caller['function'];
        }

        $type = $type ?? $name . '_type';
        $id = $id ?? $name . '_id';

        return new Relations\MorphTo(static::query(), $this, $type, $id, 'id', $name);
    }

    /**
     * 定义多态多对多反向关系
     *
     * 外键默认为当前模型的 FK（如 Tag::posts → 中间表 tag_id），
     * 相关键为 {name}_id（taggable_id）——与 Eloquent 语义一致。
     */
    protected function morphedByMany(
        string $related,
        string $name,
        ?string $table = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null
    ): Relations\MorphToMany {
        $instance = new $related();
        $table = $table ?? $name . 's';
        $foreignPivotKey = $foreignPivotKey ?? $this->getForeignKey();
        $relatedPivotKey = $relatedPivotKey ?? $name . '_id';
        $parentKey = $parentKey ?? $this->getKeyName();
        $relatedKey = $relatedKey ?? $instance->getKeyName();

        $query = $instance->newQuery();

        return new Relations\MorphToMany(
            $query, $this, $name, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey,
            true
        );
    }

    /**
     * 获取外键名（snake_case + _id 后缀）
     */
    protected function getForeignKey(): string
    {
        $className = substr(strrchr(get_class($this), '\\'), 1);

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className)) . '_id';
    }

    /**
     * 猜测 Belongs To 关系名
     */
    protected function guessBelongsToRelation(): string
    {
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2];

        return $caller['function'];
    }

    /**
     * 获取中间表名（按字母排序的 snake_case 拼接）
     */
    protected function joiningTable(string $related): string
    {
        $segments = [
            strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', substr(strrchr(get_class($this), '\\'), 1))),
            strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', substr(strrchr($related, '\\'), 1))),
        ];

        sort($segments);

        return strtolower(implode('_', $segments));
    }

    /**
     * 延迟加载关系计数
     */
    public function loadCount(string|array $relations): self
    {
        $relations = is_array($relations) ? $relations : func_get_args();

        foreach ($relations as $relation) {
            $relationObj = $this->{$relation}();
            $count = $relationObj->getQuery()->count();
            $this->setAttribute("{$relation}_count", $count);
        }

        return $this;
    }

    /**
     * 延迟加载关系聚合
     */
    public function loadSum(string $relation, string $column): self
    {
        $relationObj = $this->{$relation}();
        $sum = $relationObj->getQuery()->sum($column);
        $this->setAttribute("{$relation}_{$column}_sum", $sum);

        return $this;
    }

    /**
     * 获取多态映射（全局）
     */
    public static function getMorphMap(): array
    {
        return Relations\Relation::getMorphMap();
    }

    /**
     * 设置多态映射（全局，alias => 模型类名；$merge=false 时整体替换）
     */
    public static function enforceMorphMap(array $map, bool $merge = true): void
    {
        Relations\Relation::enforceMorphMap($map, $merge);
    }

    /**
     * 动态加载关系（支持点号嵌套）
     */
    public function load(string|array $relations): self
    {
        $relations = is_array($relations) ? $relations : func_get_args();

        foreach ($relations as $relation) {
            if (str_contains($relation, '.')) {
                $this->loadNested($relation);
            } else {
                $this->getRelationValue($relation);
            }
        }

        return $this;
    }

    /**
     * 加载嵌套关系（如 posts.comments）
     */
    protected function loadNested(string $relation): void
    {
        $segments = explode('.', $relation);
        $first = array_shift($segments);

        $this->getRelationValue($first);

        $nested = implode('.', $segments);

        if ($nested !== '' && $this->relations[$first] !== null) {
            if ($this->relations[$first] instanceof self) {
                $this->relations[$first]->load($nested);
            } elseif ($this->relations[$first] instanceof Collection) {
                $this->relations[$first]->each(fn($item) => $item->load($nested));
            }
        }
    }

    /**
     * 加载多个关系（别名，保持向后兼容）
     */
    public function loadMultiple(array $relations): self
    {
        return $this->load($relations);
    }
}
