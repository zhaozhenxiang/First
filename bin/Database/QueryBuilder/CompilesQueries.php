<?php

declare(strict_types=1);

namespace Bin\Database\QueryBuilder;

/**
 * SQL 语法编译 Trait
 */
trait CompilesQueries
{
    /**
     * 构建 UPDATE SQL
     */
    protected function grammarUpdate(array $values): string
    {
        $columns = [];

        foreach (array_keys($values) as $key) {
            $columns[] = "{$key} = ?";
        }

        $columns = implode(', ', $columns);

        return "UPDATE {$this->from} SET {$columns} {$this->compileWheres()}";
    }

    /**
     * 构建 SELECT 语句
     */
    protected function grammarSelect(): string
    {
        $columns = $this->columns === ['*'] ? '*' : implode(', ', $this->columns);

        $distinct = $this->distinct ? 'DISTINCT ' : '';

        $sql = "SELECT {$distinct}{$columns} FROM {$this->from}";

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
        $column = $this->aggregate['columns'];

        if ($this->aggregate['function'] !== 'count') {
            $column = "IFNULL({$column}, 0)";
        }

        return "SELECT {$this->aggregate['function']}({$column}) AS aggregate FROM {$this->from} {$this->compileWheres()}";
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
            $table = $join['table'];
            $first = $join['first'];
            $operator = $join['operator'];
            $second = $join['second'];
            $type = strtoupper($join['type']);

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
            'Basic' => ($not ? "NOT " : '') . "{$where['column']} {$where['operator']} ?",
            'Nested' => "({$where['query']->compileWheres()})",
            'Column' => ($not ? "NOT " : '') . "{$where['first']} {$where['operator']} {$where['second']}",
            'Raw' => $where['sql'],
            'In' => "{$where['column']} IN (" . rtrim(str_repeat('?,', count($where['values'])), ',') . ')',
            'NotIn' => "{$where['column']} NOT IN (" . rtrim(str_repeat('?,', count($where['values'])), ',') . ')',
            'InSub' => "{$where['column']} IN ({$where['query']->toSql()})",
            'NotInSub' => "{$where['column']} NOT IN ({$where['query']->toSql()})",
            'Null' => "{$where['column']} IS NULL",
            'NotNull' => "{$where['column']} IS NOT NULL",
            'Between' => "{$where['column']} BETWEEN ? AND ?",
            'NotBetween' => "{$where['column']} NOT BETWEEN ? AND ?",
            'Exists' => "EXISTS ({$where['query']->toSql()})",
            'NotExists' => "NOT EXISTS ({$where['query']->toSql()})",
            default => '',
        };
    }

    /**
     * 编译 GROUP BY
     */
    protected function compileGroups(): string
    {
        return empty($this->groups) ? '' : ' GROUP BY ' . implode(', ', $this->groups);
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

        foreach ($this->havings as $having) {
            if (isset($having['type']) && $having['type'] === 'Raw') {
                $havings[] = "{$having['boolean']} {$having['sql']}";
            } else {
                $havings[] = "{$having['boolean']} {$having['column']} {$having['operator']} ?";
            }
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
            } else {
                $orders[] = "{$order['column']} {$order['direction']}";
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
