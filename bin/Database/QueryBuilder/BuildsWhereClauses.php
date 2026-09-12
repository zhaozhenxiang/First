<?php

declare(strict_types=1);

namespace Bin\Database\QueryBuilder;

use InvalidArgumentException;

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
     *
     * 支持数组、闭包嵌套、两参数/三参数形式。三参数形式下操作符必须是白名单成员；
     * `where('col', '=', null)` 会转换为 IS NULL（避免把 '=' 误当值绑定）。
     */
    public function where(array|string|\Closure $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): self
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
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        // 值为 null 的等值/不等值条件转换为 IS NULL / IS NOT NULL
        if ($value === null && in_array(strtolower((string) $operator), ['=', '<>', '!='], true)) {
            return $this->whereNull($column, $boolean, strtolower((string) $operator) !== '=');
        }

        // 操作符白名单：防止操作符位注入（与 Laravel 保持一致的集合）
        if (!in_array(strtolower((string) $operator), $this->operators(), true)) {
            throw new InvalidArgumentException(
                sprintf('Illegal operator [%s] for column [%s].', (string) $operator, $column)
            );
        }

        // 值为闭包时构建标量子查询：where('id', '=', function($q) { ... })
        if ($value instanceof \Closure) {
            $query = $this->forNestedWhere();
            $value($query);

            $type = 'Sub';
            $this->wheres[] = compact('type', 'column', 'operator', 'query', 'boolean');
            $this->bindings = array_merge($this->bindings, $query->bindings);

            return $this;
        }

        $type = 'Basic';

        $this->wheres[] = compact('type', 'column', 'operator', 'value', 'boolean');

        $this->addBinding($value, 'where');

        return $this;
    }

    /**
     * 合法 SQL 操作符白名单
     *
     * @return list<string>
     */
    protected function operators(): array
    {
        return [
            '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
            'like', 'like binary', 'not like', 'ilike', 'not ilike',
            'rlike', 'not rlike', 'regexp', 'not regexp',
            '~', '~*', '!~', '!~*', 'similar to', 'not similar to',
            'not ilike', '~~*', '!~~*',
            '&', '|', '^', '<<', '>>',
        ];
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
        // 两参数形式在转发前归一化，避免 where() 的操作符白名单误判
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * WHERE IN 条件（支持数组和闭包子查询）
     *
     * 空数组是合法输入：IN () 是非法 SQL，编译为恒假/恒真条件（与 Laravel 一致）。
     */
    public function whereIn(string $column, array|\Closure $values, string $boolean = 'and', bool $not = false): self
    {
        if ($values instanceof \Closure) {
            $type = $not ? 'NotInSub' : 'InSub';

            $query = $this->forNestedWhere();
            $values($query);

            $this->wheres[] = compact('type', 'column', 'query', 'boolean');
            $this->bindings = array_merge($this->bindings, $query->bindings);

            return $this;
        }

        if (empty($values)) {
            $this->wheres[] = [
                'type' => 'Raw',
                'sql' => $not ? '1 = 1' : '0 = 1',
                'boolean' => $boolean,
            ];

            return $this;
        }

        $type = $not ? 'NotIn' : 'In';
        $this->wheres[] = compact('type', 'column', 'values', 'boolean');

        foreach ($values as $value) {
            $this->addBinding($value, 'where');
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
     * JSON 数组包含约束（MySQL: JSON_CONTAINS；SQLite/PG: json_each 展开比对）
     *
     * 语义与 Laravel 一致：值为数组时要求全部包含（ALL）。
     * 列支持 'col->path' 形式的 JSON 路径。
     */
    public function whereJsonContains(string $column, mixed $value, string $boolean = 'and'): self
    {
        [$sql, $bindings] = $this->compileJsonContains($column, $value, not: false);

        return $this->whereRaw($sql, $bindings, $boolean);
    }

    /**
     * JSON 数组不包含约束
     */
    public function whereJsonDoesntContain(string $column, mixed $value, string $boolean = 'and'): self
    {
        [$sql, $bindings] = $this->compileJsonContains($column, $value, not: true);

        return $this->whereRaw($sql, $bindings, $boolean);
    }

    /**
     * 编译 JSON 包含子句，返回 [sql, bindings]
     *
     * @return array{0: string, 1: list<mixed>}
     */
    protected function compileJsonContains(string $column, mixed $value, bool $not): array
    {
        $prefix = $not ? 'NOT ' : '';
        [$wrapped, $path] = $this->jsonColumnAndPath($column);

        if ($this->getDriverName() === 'mysql') {
            $target = $path !== null ? "JSON_EXTRACT({$wrapped}, '{$path}')" : $wrapped;

            return [$prefix . "JSON_CONTAINS({$target}, ?)", [json_encode($value)]];
        }

        // SQLite/PostgreSQL 无 JSON_CONTAINS：按 Laravel 语义展开比对。
        // 列表候选（['a','b']）= 全部包含（ALL）；关联数组候选 = 单个对象文档整体比对；
        // 标量候选 = 数组元素文本比对。
        $isCandidateList = is_array($value) && ($value === [] || array_is_list($value));
        $items = $isCandidateList ? array_values($value) : [$value];

        if ($items === []) {
            return [$not ? '1 = 1' : '1 = 0', []];
        }

        $source = $path !== null ? "json_extract({$wrapped}, '{$path}')" : $wrapped;

        $conditions = [];
        $bindings = [];

        foreach ($items as $item) {
            $conditions[] = "EXISTS (SELECT 1 FROM json_each({$source}) AS je WHERE CAST(je.value AS TEXT) = ?)";
            // 标量按文本比对；非标量（嵌套数组/对象）比对 JSON 文本，避免 (string) array
            $bindings[] = is_scalar($item) || $item === null ? (string) $item : json_encode($item);
        }

        return [$prefix . '(' . implode(' AND ', $conditions) . ')', $bindings];
    }

    /**
     * 解析 JSON 列与路径（'col->a->b' → ['`col`', '$."a"."b"']）
     *
     * @return array{0: string, 1: ?string}
     */
    protected function jsonColumnAndPath(string $column): array
    {
        if (!str_contains($column, '->')) {
            return [$this->wrap($column), null];
        }

        [$name, $path] = explode('->', $column, 2);

        $segments = array_map(
            fn (string $segment): string => is_numeric($segment)
                ? $segment
                : '"' . str_replace('"', '', $segment) . '"',
            explode('->', $path)
        );

        return [$this->wrap($name), '$.' . implode('.', $segments)];
    }

    /**
     * WHERE NOT 条件
     */
    public function whereNot(string|array $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): self
    {
        // 两参数形式：whereNot('col', $value)。在转发前归一化，避免 where() 的
        // 操作符白名单把两参形式的值误判为操作符。
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

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
