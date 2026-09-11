<?php

declare(strict_types=1);

namespace Bin\Database\Relations;

use Bin\Database\Collection;
use Bin\Database\Model;
use Bin\Database\Pivot;
use Bin\Database\QueryBuilder;

/**
 * Belongs To Many 关系
 */
class BelongsToMany extends Relation
{
    /**
     * 相关模型类
     */
    protected string $related;

    /**
     * 中间表
     */
    protected string $table;

    /**
     * 中间表外键（当前模型）
     */
    protected string $foreignPivotKey;

    /**
     * 中间表相关键（相关模型）
     */
    protected string $relatedPivotKey;

    /**
     * 父键
     */
    protected string $parentKey;

    /**
     * 相关键
     */
    protected string $relatedKey;

    /**
     * 中间表额外的属性
     */
    protected array $pivotColumns = [];

    /**
     * 构造函数
     */
    public function __construct(
        QueryBuilder $query,
        Model $parent,
        string $table,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey,
        string $relatedKey
    ) {
        $this->table = $table;
        $this->parentKey = $parentKey;
        $this->relatedKey = $relatedKey;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;
        $this->related = $query->getModelClass();

        parent::__construct($query, $parent);
    }

    /**
     * 是否使用时间戳
     */
    protected bool $withTimestamps = false;

    /**
     * 自定义 Pivot 模型类
     */
    protected ?string $pivotClass = null;

    /**
     * 添加约束
     */
    protected function addConstraints(): void
    {
        $this->performJoin();

        if ($this->constraints) {
            $this->query->where($this->table . '.' . $this->foreignPivotKey, '=', $this->parent->getKey());
        }
    }

    /**
     * 执行 JOIN
     */
    protected function performJoin(): void
    {
        $relatedInstance = new $this->related();
        $relatedTable = $relatedInstance->getTable();

        $this->query->join(
            $this->table,
            "{$relatedTable}.{$this->relatedKey}",
            '=',
            "{$this->table}.{$this->relatedPivotKey}"
        );
    }

    /**
     * 添加渴望加载约束
     */
    public function addEagerConstraints(array $models): void
    {
        $keys = $this->getKeys($models, $this->parentKey);

        $this->query->whereIn("{$this->table}.{$this->foreignPivotKey}", $keys);
    }

    /**
     * 获取键
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
    public function getResults(): mixed
    {
        $this->selectPivotColumns();

        $results = $this->query->get();

        return $this->hydratePivot($results);
    }

    /**
     * 执行查询（覆盖基类以支持 pivot 列选择和水合）
     */
    public function get(): mixed
    {
        return $this->getResults();
    }

    /**
     * 选择 pivot 列
     */
    protected function selectPivotColumns(): void
    {
        $columns = array_merge(
            [$this->foreignPivotKey, $this->relatedPivotKey],
            $this->pivotColumns
        );

        foreach ($columns as $column) {
            $this->query->selectRaw("{$this->table}.{$column} as pivot_{$column}");
        }
    }

    /**
     * 水合 pivot 属性到模型
     */
    protected function hydratePivot($results): mixed
    {
        $pivotClass = $this->pivotClass ?? Pivot::class;

        foreach ($results as $result) {
            $pivotAttributes = [];

            foreach ($result->getAttributes() as $key => $value) {
                if (str_starts_with($key, 'pivot_')) {
                    $pivotAttributes[substr($key, 6)] = $value;
                }
            }

            // 移除 pivot_ 前缀属性
            $cleanAttributes = array_filter(
                $result->getAttributes(),
                fn($key) => !str_starts_with($key, 'pivot_'),
                ARRAY_FILTER_USE_KEY
            );
            $result->setRawAttributes($cleanAttributes);

            // 创建 Pivot 模型实例
            $pivot = new $pivotClass($pivotAttributes, $this->table);
            $pivot->exists = true;

            if ($pivot instanceof Pivot) {
                $pivot->setPivotParent($this->parent)
                    ->setForeignKey($this->foreignPivotKey)
                    ->setRelatedKey($this->relatedPivotKey);
            }

            $result->setRelation('pivot', $pivot);
        }

        return $results;
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
            $key = $model->getAttribute($this->parentKey);

            if (isset($dictionary[$key])) {
                $model->setRelation($relation, new Collection($dictionary[$key]));
            }
        }

        return $models;
    }

    /**
     * 构建字典
     */
    protected function buildDictionary(iterable $results): array
    {
        $dictionary = [];

        $foreign = $this->foreignPivotKey;

        foreach ($results as $result) {
            $pivotValue = $result->pivot->{$foreign} ?? null;

            if ($pivotValue !== null) {
                $dictionary[$pivotValue][] = $result;
            }
        }

        return $dictionary;
    }

    /**
     * 添加中间表列
     */
    public function withPivot(string|array $columns): self
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        $this->pivotColumns = array_merge($this->pivotColumns, $columns);

        return $this;
    }

    /**
     * 使用时间戳
     */
    public function withTimestamps(): self
    {
        $this->withTimestamps = true;

        return $this->withPivot(['created_at', 'updated_at']);
    }

    /**
     * 指定自定义 Pivot 模型类
     */
    public function using(string $class): self
    {
        $this->pivotClass = $class;

        return $this;
    }

    /**
     * 附加模型到关系
     */
    public function attach(int $id, array $pivotData = []): bool
    {
        $insert = [
            $this->foreignPivotKey => $this->parent->getKey(),
            $this->relatedPivotKey => $id,
        ];

        $insert = array_merge($insert, $this->timestampPivotColumns(), $pivotData);

        return $this->newPivotQuery()->insert($insert);
    }

    /**
     * 从关系中分离模型
     */
    public function detach(int|array|null $ids = null): int
    {
        $query = $this->newPivotQuery();

        if (!is_null($ids)) {
            $ids = is_array($ids) ? $ids : [$ids];
            $query->whereIn($this->relatedPivotKey, $ids);
        }

        return $query->delete();
    }

    /**
     * 更新中间表记录
     */
    public function updateExistingPivot(int $id, array $attributes): bool
    {
        $attributes = array_merge($this->timestampPivotColumns(updatedOnly: true), $attributes);

        return $this->newPivotQuery()
            ->where($this->relatedPivotKey, $id)
            ->update($attributes) > 0;
    }

    /**
     * withTimestamps 时生成需要写入的 pivot 时间戳列
     *
     * @return array<string, string>
     */
    protected function timestampPivotColumns(bool $updatedOnly = false): array
    {
        if (!$this->withTimestamps) {
            return [];
        }

        $now = date('Y-m-d H:i:s');

        return $updatedOnly ? ['updated_at' => $now] : ['created_at' => $now, 'updated_at' => $now];
    }

    /**
     * 实例化未保存的相关模型（不接线 pivot——attach 时才建立关联）
     */
    public function make(array $attributes = []): Model
    {
        return new $this->related($attributes);
    }

    /**
     * 同步关系
     *
     * $ids 支持两种形式：
     * - [1, 2, 3]：同步关联集合；
     * - [1 => ['note' => 'x'], 2 => []]：同时写入/更新中间表附加列。
     * $detach 为 false 时保留不在 $ids 中的现有关联（syncWithoutDetaching 语义）。
     */
    public function sync(array $ids, bool $detach = true): array
    {
        $records = $this->normalizeSyncRecords($ids);

        $current = $this->newPivotQuery()->pluck($this->relatedPivotKey);

        $detached = $detach ? array_values(array_diff($current, array_keys($records))) : [];

        if (!empty($detached)) {
            $this->detach($detached);
        }

        $attached = [];
        $updated = [];

        foreach ($records as $id => $pivotData) {
            if (in_array($id, $current, true)) {
                // 已关联且带附加列时更新中间表（无附加列则不动）
                if (!empty($pivotData)) {
                    $this->updateExistingPivot($id, $pivotData);
                    $updated[] = $id;
                }

                continue;
            }

            $this->attach($id, $pivotData);
            $attached[] = $id;
        }

        return [
            'attached' => $attached,
            'detached' => $detached,
            'updated' => $updated,
        ];
    }

    /**
     * 同步但保留未列出的关联
     */
    public function syncWithoutDetaching(array $ids): array
    {
        return $this->sync($ids, detach: false);
    }

    /**
     * 同步并为每个关联写入相同的中间表附加列
     */
    public function syncWithPivotValues(array $ids, array $values, bool $detach = true): array
    {
        $records = [];

        foreach ($ids as $id) {
            $records[$id] = $values;
        }

        return $this->sync($records, $detach);
    }

    /**
     * 切换关联：已关联的解除，未关联的建立
     */
    public function toggle(array $ids): array
    {
        $records = $this->normalizeSyncRecords($ids);

        $current = $this->newPivotQuery()->pluck($this->relatedPivotKey);

        $detached = array_values(array_intersect($current, array_keys($records)));

        $attached = [];

        foreach ($records as $id => $pivotData) {
            if (!in_array($id, $current, true)) {
                $this->attach($id, $pivotData);
                $attached[] = $id;
            }
        }

        if (!empty($detached)) {
            $this->detach($detached);
        }

        return [
            'attached' => $attached,
            'detached' => $detached,
            'updated' => [],
        ];
    }

    /**
     * 归一化 sync/toggle 输入为 [id => pivotData] 映射
     *
     * @return array<int|string, array<string, mixed>>
     */
    protected function normalizeSyncRecords(array $ids): array
    {
        $records = [];

        foreach ($ids as $key => $value) {
            if (is_array($value)) {
                $records[$key] = $value;
            } else {
                $records[$value] = [];
            }
        }

        return $records;
    }

    /**
     * 创建新的中间表查询
     */
    protected function newPivotQuery(): QueryBuilder
    {
        return (new QueryBuilder($this->query->getConnection()))
            ->from($this->table)
            ->where($this->foreignPivotKey, $this->parent->getKey());
    }

    /**
     * 获取外键
     */
    public function getForeignKeyName(): string
    {
        return $this->foreignPivotKey;
    }

    /**
     * 获取本地键
     */
    public function getLocalKey(): string
    {
        return $this->parentKey;
    }

    /**
     * 关系聚合子查询：需要 JOIN 中间表
     */
    public function getAggregateSubQuery(string $parentTable, string $column, string $function): string
    {
        $relatedTable = $this->query->getTable();
        $pivot = $this->table;
        $col = $function === 'count' ? '*' : $column;

        return "SELECT {$function}({$col}) FROM {$relatedTable}"
            . " INNER JOIN {$pivot} ON {$relatedTable}.{$this->relatedKey} = {$pivot}.{$this->relatedPivotKey}"
            . " WHERE {$pivot}.{$this->foreignPivotKey} = {$parentTable}.{$this->parentKey}";
    }

    /**
     * 为 whereHas 生成 EXISTS 子查询（需要 JOIN 中间表）
     */
    public function getExistenceQuery(string $parentTable): array
    {
        $relatedTable = $this->query->getTable();
        $pivot = $this->table;

        return [
            "SELECT 1 FROM {$relatedTable}"
                . " INNER JOIN {$pivot} ON {$relatedTable}.{$this->relatedKey} = {$pivot}.{$this->relatedPivotKey}"
                . " WHERE {$pivot}.{$this->foreignPivotKey} = {$parentTable}.{$this->parentKey}",
            [],
        ];
    }

    /**
     * 获取中间表名
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * 获取外键
     */
    public function getForeignPivotKeyName(): string
    {
        return $this->foreignPivotKey;
    }

    /**
     * 获取相关键
     */
    public function getRelatedPivotKeyName(): string
    {
        return $this->relatedPivotKey;
    }

    /**
     * 获取父键
     */
    public function getParentKeyName(): string
    {
        return $this->parentKey;
    }

    /**
     * 获取相关键
     */
    public function getRelatedKeyName(): string
    {
        return $this->relatedKey;
    }
}
