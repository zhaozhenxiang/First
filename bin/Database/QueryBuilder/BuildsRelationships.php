<?php

declare(strict_types=1);

namespace Bin\Database\QueryBuilder;

use Bin\Database\Model;
use Bin\Database\Relations\Relation;
use InvalidArgumentException;

/**
 * 关联关系构建 Trait
 *
 * 包含 eager loading、whereHas 关系存在性查询、withAggregate 关系聚合。
 * 使用主类的 $eagerLoads, $withAggregates, $modelClass, $connection 属性。
 */
trait BuildsRelationships
{
    /**
     * 渴望加载关系
     */
    public function with(array|string $relations): self
    {
        $relations = is_array($relations) ? $relations : func_get_args();

        foreach ($relations as $name => $constraints) {
            if (is_numeric($name)) {
                $name = $constraints;
                $constraints = null;
            }

            $this->eagerLoads[$name] = $constraints instanceof \Closure ? $constraints : null;
        }

        return $this;
    }

    // ============================
    // 关系存在性查询
    // ============================

    /**
     * 添加 whereHas 约束 - 检查关系是否存在
     */
    public function whereHas(string $relation, ?\Closure $callback = null, string $boolean = 'and'): self
    {
        return $this->hasInternal($relation, $callback, $boolean, false);
    }

    /**
     * 添加 whereDoesntHave 约束
     */
    public function whereDoesntHave(string $relation, ?\Closure $callback = null): self
    {
        return $this->hasInternal($relation, $callback, 'and', true);
    }

    /**
     * 添加 orWhereHas 约束
     */
    public function orWhereHas(string $relation, ?\Closure $callback = null): self
    {
        return $this->hasInternal($relation, $callback, 'or', false);
    }

    /**
     * 添加 orWhereDoesntHave 约束
     */
    public function orWhereDoesntHave(string $relation, ?\Closure $callback = null): self
    {
        return $this->hasInternal($relation, $callback, 'or', true);
    }

    /**
     * has 内部实现
     */
    protected function hasInternal(string $relation, ?\Closure $callback, string $boolean, bool $negate): self
    {
        if (empty($this->modelClass)) {
            throw new InvalidArgumentException('Relation methods require a model class on the QueryBuilder.');
        }

        // 创建模型实例获取关系
        $model = new $this->modelClass();

        if (!method_exists($model, $relation)) {
            throw new InvalidArgumentException("Relation [{$relation}] does not exist on [{$this->modelClass}].");
        }

        // 获取无约束的关系对象
        $relationObj = Relation::noConstraints(function () use ($model, $relation) {
            return $model->$relation();
        });

        $parentTable = $this->getTable();

        // 让关系对象自己生成 EXISTS 子查询
        if (method_exists($relationObj, 'getExistenceQuery')) {
            [$subSql, $subBindings] = $relationObj->getExistenceQuery($parentTable);
        } elseif (method_exists($relationObj, 'getForeignKeyName') && method_exists($relationObj, 'getLocalKey')) {
            $relationTable = $relationObj->getQuery()->getTable();
            $foreignKey = $relationObj->getForeignKeyName();
            $localKey = $relationObj->getLocalKey();

            $subSql = "SELECT 1 FROM {$relationTable} WHERE {$relationTable}.{$foreignKey} = {$parentTable}.{$localKey}";
            $subBindings = [];
        } else {
            $relationClass = $relationObj::class;
            throw new InvalidArgumentException("Relation [{$relationClass}] does not support existence queries. Implement getExistenceQuery() on the relation class.");
        }

        // 如果有回调约束，需要构建带约束的子查询
        if ($callback !== null) {
            $subQuery = new self($this->connection, $relationObj->getQuery()->modelClass ?? '');
            $subQuery->from($relationObj->getQuery()->getTable());
            $callback($subQuery);

            // 获取约束的 SQL 和 bindings
            $constraintSql = $subQuery->compileWheres();
            $extraBindings = $subQuery->getBindings();

            if (!empty($constraintSql)) {
                // 去掉 WHERE 前缀，只保留条件
                $prefix = ' WHERE ';
                if (str_starts_with($constraintSql, $prefix)) {
                    $conditions = substr($constraintSql, strlen($prefix));
                } else {
                    $conditions = $constraintSql;
                }
                $subSql .= " AND {$conditions}";
                $subBindings = array_merge($subBindings, $extraBindings);
            }
        }

        $operator = $negate ? 'NOT EXISTS' : 'EXISTS';

        $type = 'Raw';
        $sql = "{$operator} ({$subSql})";
        $this->wheres[] = compact('type', 'sql', 'boolean');

        foreach ($subBindings as $binding) {
            $this->addBinding($binding, 'where');
        }

        return $this;
    }

    // ============================
    // 关系聚合查询
    // ============================

    /**
     * 关系统计
     */
    public function withCount(string|array $relations): self
    {
        $relations = is_array($relations) ? $relations : func_get_args();
        return $this->withAggregate($relations, '*', 'count');
    }

    /**
     * 关系求和
     */
    public function withSum(string $relation, string $column): self
    {
        return $this->withAggregate([$relation => $column], $column, 'sum');
    }

    /**
     * 关系平均值
     */
    public function withAvg(string $relation, string $column): self
    {
        return $this->withAggregate([$relation => $column], $column, 'avg');
    }

    /**
     * 关系最小值
     */
    public function withMin(string $relation, string $column): self
    {
        return $this->withAggregate([$relation => $column], $column, 'min');
    }

    /**
     * 关系最大值
     */
    public function withMax(string $relation, string $column): self
    {
        return $this->withAggregate([$relation => $column], $column, 'max');
    }

    /**
     * 关系聚合查询通用方法
     */
    public function withAggregate(string|array $relations, string $column, string $function): self
    {
        $relations = is_array($relations) ? $relations : [$relations => null];

        foreach ($relations as $key => $value) {
            // 支持两种形式：'posts' 或 ['posts' => fn($q) => ...]
            if (is_numeric($key)) {
                $relation = $value;
                $constraints = null;
                $alias = "{$relation}_{$function}";
                if ($function === 'count') {
                    $alias = "{$relation}_count";
                } else {
                    $alias = "{$relation}_{$function}_{$column}";
                }
            } else {
                $relation = $key;
                $constraints = $value instanceof \Closure ? $value : null;
                if ($function === 'count') {
                    $alias = "{$relation}_count";
                } else {
                    $alias = "{$relation}_{$function}_{$column}";
                }
            }

            $this->withAggregates[$alias] = [
                'relation' => $relation,
                'column' => $column,
                'alias' => $alias,
                'function' => $function,
                'constraints' => $constraints,
            ];
        }

        return $this;
    }

    /**
     * 获取 withAggregate 配置
     */
    public function getWithAggregates(): array
    {
        return $this->withAggregates;
    }

    /**
     * 应用 withAggregate 子查询到 SELECT 列
     */
    protected function applyWithAggregateSelects(array $aggregates): void
    {
        if (empty($this->modelClass)) {
            throw new InvalidArgumentException('withAggregate methods require a model class on the QueryBuilder.');
        }

        $model = new $this->modelClass();
        $parentTable = $this->from;

        foreach ($aggregates as $alias => $config) {
            $relation = $config['relation'];
            $column = $config['column'];
            $function = $config['function'];

            // 获取关系对象
            $relationObj = Relation::noConstraints(function () use ($model, $relation) {
                return $model->$relation();
            });

            // 关系对象按自身语义（直接外键 / 中间表 JOIN / through JOIN）生成聚合子查询
            $subSelect = $relationObj->getAggregateSubQuery($parentTable, $column, $function);

            // 如果有约束，添加到子查询
            if ($config['constraints'] !== null) {
                $subQuery = new self($this->connection, $relationObj->getQuery()->modelClass);
                $subQuery->from($relationObj->getQuery()->getTable());
                $config['constraints']($subQuery);

                $constraintSql = $subQuery->compileWheres();
                if (!empty($constraintSql)) {
                    $prefix = ' WHERE ';
                    if (str_starts_with($constraintSql, $prefix)) {
                        $conditions = substr($constraintSql, strlen($prefix));
                    } else {
                        $conditions = $constraintSql;
                    }
                    $subSelect .= " AND {$conditions}";
                }
            }

            // 添加到 SELECT 列
            $this->selectRaw("({$subSelect}) AS {$alias}");
        }
    }

    /**
     * 将 withAggregate 结果注入模型
     */
    protected function hydrateWithAggregates(array $models, array $results, array $aggregates): array
    {
        foreach ($models as $i => $model) {
            foreach ($aggregates as $alias => $config) {
                if (isset($results[$i][$alias])) {
                    $model->setAttribute($alias, $results[$i][$alias]);
                }
            }
        }

        return $models;
    }

    /**
     * 渴望加载关系
     */
    protected function eagerLoadRelations(array $models): array
    {
        foreach ($this->eagerLoads as $name => $constraints) {
            // 嵌套关系处理: user.profile
            if (str_contains($name, '.')) {
                $models = $this->eagerLoadRelationNested($models, $name, $constraints);
            } else {
                $models = $this->eagerLoadRelationOne($models, $name, $constraints);
            }
        }

        return $models;
    }

    /**
     * 加载单个关系
     */
    protected function eagerLoadRelationOne(array $models, string $name, ?\Closure $constraints): array
    {
        if (empty($models)) {
            return $models;
        }

        $relation = $models[0]->{$name}();

        // 应用约束
        if ($constraints !== null) {
            $constraints($relation);
        }

        // MorphTo 使用自定义 eagerLoad 方法
        if ($relation instanceof \Bin\Database\Relations\MorphTo) {
            return $relation->eagerLoad($models, $name);
        }

        // 初始化关系
        $models = $relation->initRelation($models, $name);

        // 添加渴望加载约束
        $relation->addEagerConstraints($models);

        // 获取结果
        $results = $relation->getEager();

        // 匹配关系
        return $relation->match($models, $results, $name);
    }

    /**
     * 加载嵌套关系
     *
     * 第一层批量加载后，把所有父模型的关联模型收拢成一批统一递归加载——
     * 否则每个父模型一条 SQL，嵌套 with 会退化成 N+1。
     */
    protected function eagerLoadRelationNested(array $models, string $name, ?\Closure $constraints): array
    {
        if (empty($models)) {
            return $models;
        }

        $segments = explode('.', $name);

        $first = array_shift($segments);
        $nested = implode('.', $segments);

        // 先加载第一层（一条 SQL）
        $models = $this->eagerLoadRelationOne($models, $first, $constraints);

        if ($nested === '') {
            return $models;
        }

        // 收拢所有第一层结果，作为下一层的批量输入
        $batch = [];
        foreach ($models as $model) {
            $related = $model->getRelation($first);

            if ($related instanceof Model) {
                $batch[] = $related;
            } elseif ($related instanceof \Bin\Database\Collection) {
                foreach ($related as $item) {
                    if ($item instanceof Model) {
                        $batch[] = $item;
                    }
                }
            }
        }

        if (!empty($batch)) {
            // 关联对象在父模型的关系中被引用，原地加载即可生效
            $this->eagerLoadRelationNested($batch, $nested, $constraints);
        }

        return $models;
    }
}
