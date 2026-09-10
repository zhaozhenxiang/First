<?php

declare(strict_types=1);

namespace Bin\Database\Relations;

use Bin\Database\Collection;
use Bin\Database\Model;
use Bin\Database\QueryBuilder;

/**
 * 关系基类
 */
abstract class Relation
{
    /**
     * 父模型
     */
    protected Model $parent;

    /**
     * 查询构建器
     */
    protected QueryBuilder $query;

    /**
     * 外键
     */
    protected string $foreignKey;

    /**
     * 相关键
     */
    protected string $relatedKey;

    /**
     * 关系约束
     */
    protected bool $constraints = true;

    /**
     * 构造函数
     */
    public function __construct(QueryBuilder $query, Model $parent)
    {
        $this->query = $query;
        $this->parent = $parent;

        if (static::$constraintsEnabled) {
            $this->addConstraints();
        }
    }

    /**
     * 添加关系约束
     */
    abstract protected function addConstraints(): void;

    /**
     * 添加渴望加载约束
     */
    abstract public function addEagerConstraints(array $models): void;

    /**
     * 获取关系结果
     */
    abstract public function getResults(): mixed;

    /**
     * 初始化关系
     */
    abstract public function initRelation(array $models, string $relation): array;

    /**
     * 匹配关系（$results 为模型集合，可能是 Collection 或数组）
     */
    abstract public function match(array $models, iterable $results, string $relation): array;

    /**
     * 生成 withCount/withSum 等关系聚合的关联子查询 SQL
     *
     * 子类按自身的连接语义覆写（hasMany 直接关联、belongsToMany 需要
     * JOIN 中间表等），返回形如：
     *   SELECT COUNT(*) FROM related WHERE related.fk = {parentTable}.lk
     */
    public function getAggregateSubQuery(string $parentTable, string $column, string $function): string
    {
        throw new \BadMethodCallException(
            static::class . ' does not support aggregate sub-queries.'
        );
    }

    /**
     * 获取查询构建器
     */
    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    /**
     * 获取父模型
     */
    public function getParent(): Model
    {
        return $this->parent;
    }

    /**
     * 获取外键
     */
    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    /**
     * 获取相关键
     */
    public function getRelatedKey(): string
    {
        return $this->relatedKey;
    }

    /**
     * 获取无约束版本的关系对象
     *
     * 约束在构造函数中已经应用，仅克隆并翻转标志是无效的：
     * 必须重置查询条件后让子类按"无约束"语义重建必要的 JOIN。
     */
    public function withoutConstraints(): self
    {
        $relation = clone $this;
        $relation->constraints = false;

        $relation->query = $relation->query->clone();
        $relation->query->resetSelect();

        $relation->addConstraints();

        return $relation;
    }

    /**
     * 无约束创建关系对象（静态调用）
     */
    public static function noConstraints(callable $callback): mixed
    {
        // 临时设置静态标志
        static::$constraintsEnabled = false;

        try {
            return $callback();
        } finally {
            static::$constraintsEnabled = true;
        }
    }

    /**
     * @var bool 是否启用约束（默认 true）
     */
    protected static bool $constraintsEnabled = true;

    /**
     * 全局 morph 别名映射（alias => 模型类名）
     *
     * @var array<string, class-string>
     */
    protected static array $morphMap = [];

    /**
     * 设置/合并全局 morph 别名映射
     *
     * @param array<string, class-string> $map
     * @param bool $merge false 时整体替换（用于重置/测试清理）
     */
    public static function enforceMorphMap(array $map, bool $merge = true): void
    {
        static::$morphMap = $merge ? array_merge(static::$morphMap, $map) : $map;
    }

    /**
     * 获取全局 morph 别名映射
     *
     * @return array<string, class-string>
     */
    public static function getMorphMap(): array
    {
        return static::$morphMap;
    }

    /**
     * 清空全局 morph 别名映射（用于测试）
     */
    public static function flushMorphMap(): void
    {
        static::$morphMap = [];
    }

    /**
     * 检查约束是否启用
     */
    public static function isConstraintsEnabled(): bool
    {
        return static::$constraintsEnabled;
    }

    /**
     * 获取渴望加载结果（保持 Collection 类型，与懒加载一致；
     * match 内部按需迭代）
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
     * 获取单个结果
     */
    public function first(): ?Model
    {
        return $this->query->first();
    }

    /**
     * 查找相关模型
     */
    public function find(mixed $id): ?Model
    {
        return $this->query->find($id);
    }

    /**
     * 添加基本条件
     */
    public function where(callable|array|string $column, mixed $operator = null, mixed $value = null): self
    {
        if (is_array($column)) {
            foreach ($column as $key => $value) {
                $this->where($key, '=', $value);
            }
            return $this;
        }

        // 两参数形式在转发前归一化，避免查询构建器的操作符白名单误判
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->query->where($column, $operator, $value);

        return $this;
    }

    /**
     * 排序
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->query->orderBy($column, $direction);
        return $this;
    }

    /**
     * 限制
     */
    public function limit(int $value): self
    {
        $this->query->limit($value);
        return $this;
    }

    /**
     * 分页
     */
    public function take(int $value): self
    {
        return $this->limit($value);
    }

    /**
     * 跳过
     */
    public function skip(int $value): self
    {
        $this->query->offset($value);
        return $this;
    }

    /**
     * 魔术调用
     */
    public function __call(string $method, array $parameters): mixed
    {
        $result = $this->query->$method(...$parameters);

        if ($result === $this->query) {
            return $this;
        }

        return $result;
    }
}
