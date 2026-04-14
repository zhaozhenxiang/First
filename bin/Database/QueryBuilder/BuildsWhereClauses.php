<?php

declare(strict_types=1);

namespace Bin\Database\QueryBuilder;

/**
 * WHERE 条件构建 Trait
 *
 * 从 QueryBuilder 提取的所有 WHERE 相关方法。
 * 使用主类的 $wheres, $bindings 属性和 addBinding() 方法。
 */
trait BuildsWhereClauses
{
    /**
     * WHERE 条件
     */
    public function where(array|string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): self
    {
        // 数组形式：where(['id' => 1, 'name' => 'foo'])
        if (is_array($column)) {
            foreach ($column as $key => $value) {
                $this->where($key, '=', $value, $boolean);
            }
            return $this;
        }

        // 闭包形式：where(function($query) { ... })
        if ($column instanceof \Closure) {
            return $this->whereNested($column, $boolean);
        }

        // 两参数形式：where('id', 1) => where('id', '=', 1)
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $type = 'Basic';

        $this->wheres[] = compact('type', 'column', 'operator', 'value', 'boolean');

        if (!($value instanceof \Closure)) {
            $this->addBinding($value, 'where');
        }

        return $this;
    }

    /**
     * 嵌套 WHERE
     */
    protected function whereNested(\Closure $callback, string $boolean = 'and'): self
    {
        $callback($query = $this->forNestedWhere());

        return $this->addNestedWhereQuery($query, $boolean);
    }

    /**
     * 创建嵌套查询实例
     */
    protected function forNestedWhere(): self
    {
        $query = new self($this->connection, $this->modelClass);
        return $query->from($this->from);
    }

    /**
     * 添加嵌套查询
     */
    protected function addNestedWhereQuery(self $query, string $boolean = 'and'): self
    {
        if (count($query->wheres) > 0) {
            $type = 'Nested';
            $this->wheres[] = compact('type', 'query', 'boolean');
            $this->bindings = array_merge($this->bindings, $query->bindings);
        }

        return $this;
    }

    /**
     * WHERE OR 条件
     */
    public function orWhere(array|string $column, mixed $operator = null, mixed $value = null): self
    {
        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * WHERE IN 条件（支持数组和闭包子查询）
     */
    public function whereIn(string $column, array|\Closure $values, string $boolean = 'and', bool $not = false): self
    {
        $type = $not ? 'NotInSub' : 'InSub';

        if ($values instanceof \Closure) {
            $query = $this->forNestedWhere();
            $values($query);

            $this->wheres[] = compact('type', 'column', 'query', 'boolean');
            $this->bindings = array_merge($this->bindings, $query->bindings);
        } else {
            $type = $not ? 'NotIn' : 'In';
            $this->wheres[] = compact('type', 'column', 'values', 'boolean');

            foreach ($values as $value) {
                $this->addBinding($value, 'where');
            }
        }

        return $this;
    }

    /**
     * WHERE NOT IN 条件
     */
    public function whereNotIn(string $column, array|callable $values, string $boolean = 'and'): self
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    /**
     * WHERE NULL 条件
     */
    public function whereNull(string $column, string $boolean = 'and', bool $not = false): self
    {
        $type = $not ? 'NotNull' : 'Null';
        $this->wheres[] = compact('type', 'column', 'boolean');
        return $this;
    }

    /**
     * WHERE NOT NULL 条件
     */
    public function whereNotNull(string $column, string $boolean = 'and'): self
    {
        return $this->whereNull($column, $boolean, true);
    }

    /**
     * WHERE BETWEEN 条件
     */
    public function whereBetween(string $column, array $values, string $boolean = 'and', bool $not = false): self
    {
        $type = $not ? 'NotBetween' : 'Between';
        $this->wheres[] = compact('type', 'column', 'values', 'boolean');

        foreach ($values as $value) {
            $this->addBinding($value, 'where');
        }

        return $this;
    }

    /**
     * WHERE LIKE 条件
     */
    public function whereLike(string $column, string $value, string $boolean = 'and'): self
    {
        return $this->where($column, 'LIKE', $value, $boolean);
    }

    /**
     * WHERE date 条件
     */
    public function whereDate(string $column, string $operator, mixed $value): self
    {
        return $this->where("DATE({$column})", $operator, $value);
    }

    /**
     * WHERE day 条件
     */
    public function whereDay(string $column, string $operator, mixed $value): self
    {
        return $this->where("DAY({$column})", $operator, $value);
    }

    /**
     * WHERE month 条件
     */
    public function whereMonth(string $column, string $operator, mixed $value): self
    {
        return $this->where("MONTH({$column})", $operator, $value);
    }

    /**
     * WHERE year 条件
     */
    public function whereYear(string $column, string $operator, mixed $value): self
    {
        return $this->where("YEAR({$column})", $operator, $value);
    }

    /**
     * WHERE time 条件
     */
    public function whereTime(string $column, string $operator, mixed $value): self
    {
        return $this->where("TIME({$column})", $operator, $value);
    }

    /**
     * WHERE 列比较条件
     */
    public function whereColumn(string $first, string $operator, ?string $second = null, string $boolean = 'and'): self
    {
        if ($second === null) {
            $second = $operator;
            $operator = '=';
        }

        $type = 'Column';
        $this->wheres[] = compact('type', 'first', 'operator', 'second', 'boolean');

        return $this;
    }

    /**
     * OR WHERE 列比较条件
     */
    public function orWhereColumn(string $first, string $operator, ?string $second = null): self
    {
        return $this->whereColumn($first, $operator, $second, 'or');
    }

    /**
     * WHERE 原始 SQL 条件
     */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'and'): self
    {
        $type = 'Raw';
        $this->wheres[] = compact('type', 'sql', 'boolean');

        foreach ($bindings as $binding) {
            $this->addBinding($binding, 'where');
        }

        return $this;
    }

    /**
     * OR WHERE 原始 SQL 条件
     */
    public function orWhereRaw(string $sql, array $bindings = []): self
    {
        return $this->whereRaw($sql, $bindings, 'or');
    }

    /**
     * WHERE NOT 条件
     */
    public function whereNot(string|array $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): self
    {
        return $this->where($column, $operator, $value, $boolean)->not();
    }

    /**
     * 将上一个 where 条件取反
     */
    protected function not(): self
    {
        $index = array_key_last($this->wheres);

        if ($index !== null) {
            $this->wheres[$index]['not'] = true;
        }

        return $this;
    }

    /**
     * WHERE EXISTS 子查询
     */
    public function whereExists(\Closure $callback, string $boolean = 'and', bool $not = false): self
    {
        $query = $this->forNestedWhere();
        $callback($query);

        $type = $not ? 'NotExists' : 'Exists';
        $this->wheres[] = compact('type', 'query', 'boolean');
        $this->bindings = array_merge($this->bindings, $query->bindings);

        return $this;
    }

    /**
     * OR WHERE EXISTS 子查询
     */
    public function orWhereExists(\Closure $callback): self
    {
        return $this->whereExists($callback, 'or');
    }

    /**
     * WHERE NOT EXISTS 子查询
     */
    public function whereNotExists(\Closure $callback, string $boolean = 'and'): self
    {
        return $this->whereExists($callback, $boolean, true);
    }

    /**
     * 条件查询 — 满足条件时执行回调
     */
    public function when(mixed $condition, \Closure $callback, ?\Closure $default = null): self
    {
        if ($condition) {
            $callback($this);
            return $this;
        }

        if ($default) {
            $default($this);
        }

        return $this;
    }

    /**
     * 条件查询 — 不满足条件时执行回调
     */
    public function unless(mixed $condition, \Closure $callback, ?\Closure $default = null): self
    {
        return $this->when(!$condition, $callback, $default);
    }
}
