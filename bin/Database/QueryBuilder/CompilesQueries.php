<?php

declare(strict_types=1);

namespace Bin\Database\QueryBuilder;

/**
 * SQL 语法编译 Trait
 */
trait CompilesQueries
{
    /**
     * 包裹 SQL 标识符（反引号，MySQL/SQLite 兼容）
     *
     * 只包裹裸标识符；包含括号、空格（别名）、反引号或 "*" 的表达式原样返回，
     * 因此 DATE(col)、RAND()、"users as u"、Raw 表达式都不受影响。
     */
    protected function wrap(string $identifier): string
    {
        if ($identifier === '' || $identifier === '*' || str_contains($identifier, '(') || str_contains($identifier, '`') || str_contains($identifier, ' ')) {
            return $identifier;
        }

        return implode('.', array_map(
            fn (string $part): string => $part === '*' ? $part : '`' . str_replace('`', '', $part) . '`',
            explode('.', $identifier)
        ));
    }

    /**
     * 构建 UPDATE SQL
     */
    protected function grammarUpdate(array $values): string
    {
        $columns = [];

        foreach (array_keys($values) as $key) {
            $columns[] = $this->wrap($key) . " = ?";
        }

        $columns = implode(', ', $columns);

        return "UPDATE {$this->wrap($this->from)} SET {$columns} {$this->compileWheres()}";
    }

    /**
     * 构建 SELECT 语句
     */
    protected function grammarSelect(): string
    {
        $columns = $this->columns === ['*']
            ? '*'
            : implode(', ', array_map(fn ($column) => $this->wrap((string) $column), $this->columns));

        $distinct = $this->distinct ? 'DISTINCT ' : '';

        $sql = "SELECT {$distinct}{$columns} FROM {$this->wrap($this->from)}";

        $sql .= $this->compileJoins();

        $sql .= $this->compileWheres();

        $sql .= $this->compileGroups();

        $sql .= $this->compileHavings();

        $sql .= $this->compileOrders();

        $sql .= $this->compileLimit();

        $sql .= $this->compileOffset();

        $sql .= $this->compileLock();

        // 编译 UNION
        if (!empty($this->unionQueries)) {
            $sql = $this->compileUnions($sql);
        }

        return $sql;
    }

    /**
     * 编译 UNION 查询
     */
    protected function compileUnions(string $sql): string
    {
        foreach ($this->unionQueries as $union) {
            $type = $union['all'] ? ' UNION ALL ' : ' UNION ';
            $sql .= $type . $union['query']->toSql();
        }

        if ($this->unionOrder !== '') {
            $sql .= " ORDER BY {$this->unionOrder}";
        }

        if ($this->unionLimit !== null) {
            $sql .= " LIMIT {$this->unionLimit}";
        }

        if ($this->unionOffset !== null) {
            $sql .= " OFFSET {$this->unionOffset}";
        }

        return $sql;
    }

    /**
     * 编译悲观锁
     */
    protected function compileLock(): string
    {
        return $this->lock !== null ? " {$this->lock}" : '';
    }

    /**
     * 构建聚合查询语句
     */
    protected function grammarAggregate(): string
    {
        $column = $this->wrap($this->aggregate['columns']);

        if ($this->aggregate['function'] !== 'count') {
            $column = "IFNULL({$column}, 0)";
        }

        // JOIN 必须保留：带 join 的 count/sum 若丢掉 join 条件会得到静默错误的聚合值
        return "SELECT {$this->aggregate['function']}({$column}) AS aggregate FROM {$this->wrap($this->from)}"
            . $this->compileJoins()
            . $this->compileWheres()
            . $this->compileGroups()
            . $this->compileHavings();
    }

    /**
     * 编译 JOIN
     */
    protected function compileJoins(): string
    {
        if (empty($this->joins)) {
            return '';
        }

        $joins = [];

        foreach ($this->joins as $join) {
            $type = strtoupper($join['type']);

            // CROSS JOIN 无 ON 条件；其余 JOIN 均为列比较形态
            if ($type === 'CROSS') {
                $joins[] = "CROSS JOIN {$this->wrap($join['table'])}";
                continue;
            }

            $table = $this->wrap($join['table']);
            $first = $this->wrap($join['first']);
            $operator = $join['operator'];
            $second = $this->wrap($join['second']);

            $joins[] = "{$type} JOIN {$table} ON {$first} {$operator} {$second}";
        }

        return ' ' . implode(' ', $joins);
    }

    /**
     * 编译 WHERE
     */
    protected function compileWheres(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $sql = '';

        foreach ($this->wheres as $index => $where) {
            $condition = $this->compileWhere($where);

            if ($index === 0) {
                $sql .= $condition;
            } else {
                $boolean = strtoupper($where['boolean'] ?? 'and');
                $sql .= " {$boolean} {$condition}";
            }
        }

        return ' WHERE ' . $sql;
    }

    /**
     * 编译单个 WHERE 条件
     */
    protected function compileWhere(array $where): string
    {
        $not = !empty($where['not']) ? 'NOT ' : '';

        return match ($where['type']) {
            'Basic' => ($not ? 'NOT ' : '') . $this->wrap($where['column']) . " {$where['operator']} ?",
            'Nested' => "({$where['query']->compileWheres()})",
            'Column' => ($not ? 'NOT ' : '') . $this->wrap($where['first']) . " {$where['operator']} " . $this->wrap($where['second']),
            'Raw' => $where['sql'],
            'Sub' => $this->wrap($where['column']) . " {$where['operator']} (" . $where['query']->toSql() . ')',
            'In' => $this->wrap($where['column']) . ' IN (' . rtrim(str_repeat('?,', count($where['values'])), ',') . ')',
            'NotIn' => $this->wrap($where['column']) . ' NOT IN (' . rtrim(str_repeat('?,', count($where['values'])), ',') . ')',
            'InSub' => $this->wrap($where['column']) . ' IN (' . $where['query']->toSql() . ')',
            'NotInSub' => $this->wrap($where['column']) . ' NOT IN (' . $where['query']->toSql() . ')',
            'Null' => $this->wrap($where['column']) . ' IS NULL',
            'NotNull' => $this->wrap($where['column']) . ' IS NOT NULL',
            'Between' => $this->wrap($where['column']) . ' BETWEEN ? AND ?',
            'NotBetween' => $this->wrap($where['column']) . ' NOT BETWEEN ? AND ?',
            'Exists' => 'EXISTS (' . $where['query']->toSql() . ')',
            'NotExists' => 'NOT EXISTS (' . $where['query']->toSql() . ')',
            default => '',
        };
    }

    /**
     * 编译 GROUP BY
     */
    protected function compileGroups(): string
    {
        if (empty($this->groups)) {
            return '';
        }

        return ' GROUP BY ' . implode(', ', array_map(fn ($group) => $this->wrap((string) $group), $this->groups));
    }

    /**
     * 编译 HAVING
     */
    protected function compileHavings(): string
    {
        if (empty($this->havings)) {
            return '';
        }

        $havings = [];

        foreach ($this->havings as $index => $having) {
            if (isset($having['type']) && $having['type'] === 'Raw') {
                $condition = $having['sql'];
            } else {
                $condition = $this->wrap($having['column']) . " {$having['operator']} ?";
            }

            // 首个条件前不能带 AND/OR，否则产生非法 SQL（HAVING and total > ?）
            $havings[] = $index === 0
                ? $condition
                : strtoupper($having['boolean'] ?? 'and') . ' ' . $condition;
        }

        return ' HAVING ' . implode(' ', $havings);
    }

    /**
     * 编译 ORDER BY
     */
    protected function compileOrders(): string
    {
        if (empty($this->orders)) {
            return '';
        }

        $orders = [];

        foreach ($this->orders as $order) {
            if (isset($order['type']) && $order['type'] === 'Raw') {
                $orders[] = $order['sql'];
            } elseif (isset($order['type']) && $order['type'] === 'Sub') {
                $orders[] = '(' . $order['query']->toSql() . ') ' . ($order['direction'] ?? 'asc');
            } else {
                $orders[] = $this->wrap($order['column']) . ' ' . $order['direction'];
            }
        }

        return ' ORDER BY ' . implode(', ', $orders);
    }

    /**
     * 编译 LIMIT
     */
    protected function compileLimit(): string
    {
        return $this->limit !== null ? " LIMIT {$this->limit}" : '';
    }

    /**
     * 编译 OFFSET
     */
    protected function compileOffset(): string
    {
        return $this->offset !== null ? " OFFSET {$this->offset}" : '';
    }
}
