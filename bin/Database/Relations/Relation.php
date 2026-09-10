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
     * 匹配关系
     */
    abstract public function match(array $models, array $results, string $relation): array;

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
     * 禁用约束
     */
    public function withoutConstraints(): self
    {
        $relation = clone $this;
        $relation->constraints = false;
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
     * 检查约束是否启用
     */
    public static function isConstraintsEnabled(): bool
    {
        return static::$constraintsEnabled;
    }

    /**
     * 获取关系
     */
    public function getEager(): mixed
    {
        $result = $this->get();

        if ($result instanceof Collection) {
            return $result->toArray();
        }

        return $result;
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
