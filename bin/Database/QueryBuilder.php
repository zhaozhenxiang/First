<?php

declare(strict_types=1);

namespace Bin\Database;

use Bin\Database\Model;
use PDO;
use PDOStatement;

/**
 * 查询构建器 - 类似 Laravel 的 Query Builder
 */
class QueryBuilder
{
    protected PDO $connection;

    protected string $from = '';

    protected array $columns = ['*'];

    protected array $wheres = [];

    protected array $bindings = [];

    protected array $orders = [];

    protected ?int $limit = null;

    protected ?int $offset = null;

    protected array $joins = [];

    protected array $groups = [];

    protected array $havings = [];

    protected ?array $aggregate = null;

    protected bool $distinct = false;

    protected string $modelClass = '';

    protected array $unionQueries = [];

    protected string $unionOrder = '';

    protected ?int $unionLimit = null;

    protected ?int $unionOffset = null;

    /** @var array<string, \Closure> 渴望加载的关系 */
    protected array $eagerLoads = [];

    /** @var array<string, \Closure> 全局作用域 */
    protected array $scopes = [];

    /** @var array<string> 已移除的全局作用域 */
    protected array $removedScopes = [];

    public function __construct(PDO $connection, string $modelClass = '')
    {
        $this->connection = $connection;
        $this->modelClass = $modelClass;
    }

    /**
     * 添加全局作用域
     */
    public function withGlobalScope(string $identifier, \Closure $callback): self
    {
        $this->scopes[$identifier] = $callback;
        return $this;
    }

    /**
     * 移除全局作用域
     */
    public function withoutGlobalScope(string $identifier): self
    {
        $this->removedScopes[] = $identifier;
        return $this;
    }

    /**
     * 移除多个全局作用域
     */
    public function withoutGlobalScopes(array $identifiers): self
    {
        $this->removedScopes = array_merge($this->removedScopes, $identifiers);
        return $this;
    }

    /**
     * 应用全局作用域
     */
    public function applyScopes(): self
    {
        foreach ($this->scopes as $identifier => $callback) {
            if (!in_array($identifier, $this->removedScopes)) {
                $callback($this);
            }
        }

        return $this;
    }

    /**
     * 获取所有全局作用域
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /**
     * 设置查询表
     */
    public function from(string $table, ?string $as = null): self
    {
        $this->from = $as ? "{$table} as {$as}" : $table;
        return $this;
    }

    /**
     * 设置查询列
     */
    public function select(array|string $columns = ['*']): self
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    /**
     * 添加 DISTINCT
     */
    public function distinct(): self
    {
        $this->distinct = true;
        return $this;
    }

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
     * WHERE IN 条件
     */
    public function whereIn(string $column, array $values, string $boolean = 'and', bool $not = false): self
    {
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
    public function whereNotIn(string $column, array $values): self
    {
        return $this->whereIn($column, $values, 'and', true);
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

        $this->bindings['where'] = array_merge($this->bindings['where'] ?? [], $values);

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
     * JOIN 连接
     */
    public function join(string $table, string $first, string $operator, string $second, string $type = 'inner'): self
    {
        $this->joins[] = compact('type', 'table', 'first', 'operator', 'second');
        return $this;
    }

    /**
     * LEFT JOIN
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    /**
     * RIGHT JOIN
     */
    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'right');
    }

    /**
     * ORDER BY
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->orders[] = compact('column', 'direction');
        return $this;
    }

    /**
     * ORDER BY DESC
     */
    public function orderByDesc(string $column): self
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * 最新记录
     */
    public function latest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * 最旧记录
     */
    public function oldest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'asc');
    }

    /**
     * 随机排序
     */
    public function inRandomOrder(): self
    {
        return $this->orderBy('RAND()');
    }

    /**
     * GROUP BY
     */
    public function groupBy(array|string $groups): self
    {
        $this->groups = array_merge($this->groups, is_array($groups) ? $groups : [$groups]);
        return $this;
    }

    /**
     * HAVING
     */
    public function having(string $column, string $operator, mixed $value, string $boolean = 'and'): self
    {
        $this->havings[] = compact('column', 'operator', 'value', 'boolean');
        $this->addBinding($value, 'having');
        return $this;
    }

    /**
     * LIMIT
     */
    public function limit(int $value): self
    {
        $this->limit = $value;
        return $this;
    }

    /**
     * OFFSET
     */
    public function offset(int $value): self
    {
        $this->offset = $value;
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
        return $this->offset($value);
    }

    /**
     * 分页查询
     *
     * @param int $perPage 每页数量
     * @param array $columns 查询列
     * @param string $pageName 页码参数名
     * @param int|null $page 当前页码（null 时自动获取）
     * @return LengthAwarePaginator
     */
    public function paginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null): LengthAwarePaginator
    {
        $page = $page ?? $this->resolveCurrentPage($pageName);

        $total = $this->getCountForPagination();

        // 克隆查询构建器，避免修改原实例
        $query = $this->clone();
        $query->columns = $columns;

        $results = $query->forPage($page, $perPage)->get();

        return new LengthAwarePaginator(
            $results,
            $total,
            $perPage,
            $page,
            [
                'path' => $this->resolvePath(),
                'pageName' => $pageName,
                'query' => $this->resolveQuery(),
            ]
        );
    }

    /**
     * 简单分页（无总数统计）
     *
     * @param int $perPage 每页数量
     * @param array $columns 查询列
     * @param string $pageName 页码参数名
     * @param int|null $page 当前页码
     * @return Paginator
     */
    public function simplePaginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null): Paginator
    {
        $page = $page ?? $this->resolveCurrentPage($pageName);

        // 克隆查询构建器，避免修改原实例
        $query = $this->clone();
        $query->columns = $columns;

        // 使用原始 perPage 计算 offset，然后多取一条
        $offset = ($page - 1) * $perPage;
        $results = $query->offset($offset)->limit($perPage + 1)->get();

        $hasMore = count($results) > $perPage;

        if ($hasMore) {
            array_pop($results);
        }

        return new Paginator(
            $results,
            $perPage,
            $page,
            [
                'path' => $this->resolvePath(),
                'pageName' => $pageName,
                'query' => $this->resolveQuery(),
            ],
            $hasMore
        );
    }

    /**
     * 光标分页
     *
     * @param int $perPage 每页数量
     * @param array $columns 查询列
     * @param string $cursorName 游标参数名
     * @param string|null $cursor 游标值
     * @return CursorPaginator
     */
    public function cursorPaginate(int $perPage = 15, array $columns = ['*'], string $cursorName = 'cursor', ?string $cursor = null): CursorPaginator
    {
        // 克隆查询构建器，避免修改原实例
        $query = $this->clone();
        $query->columns = $columns;

        // 如果有游标，添加 WHERE 条件
        if ($cursor !== null) {
            $decoded = json_decode(base64_decode($cursor), true);
            if (isset($decoded['id'])) {
                $query->where('id', '>', $decoded['id']);
            }
        }

        // 多取一条判断是否有下一页
        $results = $query->limit($perPage + 1)->get();

        $hasMore = count($results) > $perPage;

        if ($hasMore) {
            array_pop($results);
        }

        // 生成下一个游标
        $nextCursor = null;
        if ($hasMore && !empty($results)) {
            $lastItem = end($results);
            $id = $lastItem instanceof Model ? $lastItem->id : ($lastItem['id'] ?? null);
            if ($id !== null) {
                $nextCursor = base64_encode(json_encode(['id' => $id]));
            }
        }

        return new CursorPaginator(
            $results,
            $perPage,
            $cursor,
            $nextCursor,
            [
                'path' => $this->resolvePath(),
                'cursorName' => $cursorName,
                'query' => $this->resolveQuery(),
            ]
        );
    }

    /**
     * 设置分页页码
     */
    public function forPage(int $page, int $perPage = 15): self
    {
        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    /**
     * 获取分页总数
     */
    protected function getCountForPagination(): int
    {
        $query = $this->clone();

        // 移除不需要的属性
        $query->columns = ['*'];
        $query->orders = [];
        $query->limit = null;
        $query->offset = null;
        $query->aggregate = null;

        return $query->count();
    }

    /**
     * 解析当前页码
     */
    protected function resolveCurrentPage(string $pageName = 'page'): int
    {
        $page = $_GET[$pageName] ?? 1;

        if (filter_var($page, FILTER_VALIDATE_INT) !== false && (int) $page >= 1) {
            return (int) $page;
        }

        return 1;
    }

    /**
     * 解析当前路径
     */
    protected function resolvePath(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    /**
     * 解析查询参数
     */
    protected function resolveQuery(): array
    {
        $query = $_GET;
        unset($query['page']);

        return $query;
    }

    /**
     * 获取单条记录
     */
    public function first(): mixed
    {
        $this->limit(1);

        $results = $this->get();

        return empty($results) ? null : $results[0];
    }

    /**
     * 根据 ID 查找
     */
    public function find(int $id): mixed
    {
        return $this->where('id', $id)->first();
    }

    /**
     * 根据 ID 数组查找
     */
    public function findMany(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->whereIn('id', $ids)->get();
    }

    /**
     * 获取所有记录
     */
    public function get(): array
    {
        // 应用全局作用域
        $this->applyScopes();

        $sql = $this->toSql();
        $bindings = $this->getBindings();

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 如果有模型类，则转换为模型实例
        if ($this->modelClass && class_exists($this->modelClass)) {
            $models = array_map(fn($item) => $this->hydrateModel($item), $results);

            // 渴望加载关系
            if (!empty($this->eagerLoads)) {
                $models = $this->eagerLoadRelations($models);
            }

            return $models;
        }

        return $results;
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
        $relation = $models[0]->{$name}();

        // 应用约束
        if ($constraints !== null) {
            $constraints($relation);
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
     */
    protected function eagerLoadRelationNested(array $models, string $name, ?\Closure $constraints): array
    {
        $segments = explode('.', $name);

        $first = array_shift($segments);

        // 先加载第一层
        $relation = $models[0]->{$first}();

        if ($constraints !== null) {
            $constraints($relation);
        }

        $models = $relation->initRelation($models, $first);
        $relation->addEagerConstraints($models);
        $results = $relation->getEager();
        $models = $relation->match($models, $results, $first);

        // 递归加载嵌套关系
        if (!empty($segments)) {
            foreach ($models as $model) {
                $related = $model->getRelation($first);

                if ($related instanceof Model) {
                    $this->eagerLoadRelationNested([$related], implode('.', $segments), $constraints);
                } elseif (is_array($related) && !empty($related)) {
                    $this->eagerLoadRelationNested($related, implode('.', $segments), $constraints);
                }
            }
        }

        return $models;
    }

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

    /**
     * 获取单个列的值
     */
    public function value(string $column): mixed
    {
        $result = $this->first();

        if ($result === null) {
            return null;
        }

        if ($this->modelClass && $result instanceof Model) {
            return $result->{$column};
        }

        return $result[$column] ?? null;
    }

    /**
     * 获取指定列的值数组
     */
    public function pluck(string $column, ?string $key = null): array
    {
        $results = $this->get();

        $values = [];

        foreach ($results as $result) {
            if ($result instanceof Model) {
                $value = $result->{$column};
                $id = $key ? $result->{$key} : null;
            } else {
                $value = $result[$column] ?? null;
                $id = $key ? ($result[$key] ?? null) : null;
            }

            if ($key !== null) {
                $values[$id] = $value;
            } else {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * 获取记录数
     */
    public function count(string $columns = '*'): int
    {
        return (int) $this->aggregate(__FUNCTION__, $columns);
    }

    /**
     * 获取最大值
     */
    public function max(string $column): mixed
    {
        return $this->aggregate(__FUNCTION__, $column);
    }

    /**
     * 获取最小值
     */
    public function min(string $column): mixed
    {
        return $this->aggregate(__FUNCTION__, $column);
    }

    /**
     * 获取平均值
     */
    public function avg(string $column): mixed
     {
        return $this->aggregate(__FUNCTION__, $column);
    }

    /**
     * 获取总和
     */
    public function sum(string $column): mixed
    {
        return $this->aggregate(__FUNCTION__, $column);
    }

    /**
     * 聚合查询
     */
    protected function aggregate(string $function, string $columns = '*'): mixed
    {
        $this->aggregate = compact('function', 'columns');

        $previousColumns = $this->columns;

        $results = $this->get();

        $this->aggregate = null;
        $this->columns = $previousColumns;

        if (empty($results)) {
            return 0;
        }

        $result = $results[0];

        if ($result instanceof Model) {
            $key = 'aggregate';
            return $result->{$key};
        }

        return $result['aggregate'] ?? 0;
    }

    /**
     * 插入记录
     */
    public function insert(array $values): bool
    {
        if (empty($values)) {
            return true;
        }

        // 批量插入
        if (!is_array(reset($values))) {
            $values = [$values];
        }

        $keys = array_keys(reset($values));
        $columns = implode(', ', $keys);
        $parameters = implode(', ', array_map(fn($key) => ":{$key}", $keys));

        $sql = "INSERT INTO {$this->from} ({$columns}) VALUES ({$parameters})";

        $stmt = $this->connection->prepare($sql);

        foreach ($values as $record) {
            $stmt->execute($record);
        }

        return true;
    }

    /**
     * 获取最后插入的 ID
     */
    public function insertGetId(array $values): int|string
    {
        $this->insert($values);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * 更新记录
     */
    public function update(array $values): int
    {
        $sql = $this->grammarUpdate($values);

        $bindings = array_values($values);
        $bindings = array_merge($bindings, $this->getBindings());

        $stmt = $this->connection->prepare($sql);

        $stmt->execute($bindings);

        return $stmt->rowCount();
    }

    /**
     * 删除记录
     */
    public function delete(): int
    {
        $sql = "DELETE FROM {$this->from} {$this->compileWheres()}";

        $bindings = $this->getBindings();

        $stmt = $this->connection->prepare($sql);

        $stmt->execute($bindings);

        return $stmt->rowCount();
    }

    /**
     * 增加列值
     */
    public function increment(string $column, int $amount = 1, array $extra = []): int
    {
        $sql = "UPDATE {$this->from} SET {$column} = {$column} + ?";

        if (!empty($extra)) {
            $sets = [];
            foreach (array_keys($extra) as $key) {
                $sets[] = "{$key} = ?";
            }
            $sql .= ', ' . implode(', ', $sets);
        }

        $sql .= ' ' . $this->compileWheres();

        $bindings = array_merge([$amount], array_values($extra), $this->getBindings());

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->rowCount();
    }

    /**
     * 减少列值
     */
    public function decrement(string $column, int $amount = 1, array $extra = []): int
    {
        $sql = "UPDATE {$this->from} SET {$column} = {$column} - ?";

        if (!empty($extra)) {
            $sets = [];
            foreach (array_keys($extra) as $key) {
                $sets[] = "{$key} = ?";
            }
            $sql .= ', ' . implode(', ', $sets);
        }

        $sql .= ' ' . $this->compileWheres();

        $bindings = array_merge([$amount], array_values($extra), $this->getBindings());

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->rowCount();
    }

    /**
     * 检查记录是否存在
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * 检查记录是否不存在
     */
    public function doesntExist(): bool
    {
        return !$this->exists();
    }

    /**
     * 构建 UPDATE SQL
     */
    protected function grammarUpdate(array $values): string
    {
        $columns = [];

        foreach ($values as $key => $value) {
            $columns[] = "{$key} = :{$key}";
        }

        $columns = implode(', ', $columns);

        return "UPDATE {$this->from} SET {$columns} {$this->compileWheres()}";
    }

    /**
     * 构建 SELECT SQL
     */
    public function toSql(): string
    {
        if ($this->aggregate) {
            return $this->grammarAggregate();
        }

        return $this->grammarSelect();
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

        return $sql;
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
        return match ($where['type']) {
            'Basic' => "{$where['column']} {$where['operator']} ?",
            'Nested' => "({$where['query']->compileWheres()})",
            'In' => "{$where['column']} IN (" . rtrim(str_repeat('?,', count($where['values'])), ',') . ')',
            'NotIn' => "{$where['column']} NOT IN (" . rtrim(str_repeat('?,', count($where['values'])), ',') . ')',
            'Null' => "{$where['column']} IS NULL",
            'NotNull' => "{$where['column']} IS NOT NULL",
            'Between' => "{$where['column']} BETWEEN ? AND ?",
            'NotBetween' => "{$where['column']} NOT BETWEEN ? AND ?",
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
            $havings[] = "{$having['boolean']} {$having['column']} {$having['operator']} ?";
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
            $orders[] = "{$order['column']} {$order['direction']}";
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

    /**
     * 获取绑定参数
     */
    public function getBindings(): array
    {
        return array_merge(
            $this->bindings['where'] ?? [],
            $this->bindings['having'] ?? []
        );
    }

    /**
     * 添加绑定参数
     */
    protected function addBinding(mixed $value, string $type = 'where'): self
    {
        if (!isset($this->bindings[$type])) {
            $this->bindings[$type] = [];
        }

        $this->bindings[$type][] = $value;

        return $this;
    }

    /**
     * 水合模型实例
     */
    protected function hydrateModel(array $attributes): Model
    {
        $model = new $this->modelClass();

        $model->setRawAttributes($attributes);

        $model->exists = true;

        return $model;
    }

    /**
     * 开始事务
     */
    public function beginTransaction(): self
    {
        $this->connection->beginTransaction();
        return $this;
    }

    /**
     * 提交事务
     */
    public function commit(): self
    {
        $this->connection->commit();
        return $this;
    }

    /**
     * 回滚事务
     */
    public function rollBack(): self
    {
        $this->connection->rollBack();
        return $this;
    }

    /**
     * 克隆查询构建器
     */
    public function clone(): self
    {
        return clone $this;
    }

    /**
     * 克隆时深拷贝数组属性
     */
    public function __clone(): void
    {
        $this->wheres = $this->deepCloneArray($this->wheres);
        $this->bindings = $this->deepCloneArray($this->bindings);
        $this->orders = $this->deepCloneArray($this->orders);
        $this->joins = $this->deepCloneArray($this->joins);
        $this->groups = $this->deepCloneArray($this->groups);
        $this->havings = $this->deepCloneArray($this->havings);
        $this->columns = $this->deepCloneArray($this->columns);
        $this->eagerLoads = $this->deepCloneArray($this->eagerLoads);
        $this->unionQueries = $this->deepCloneArray($this->unionQueries);
    }

    /**
     * 深拷贝数组
     */
    protected function deepCloneArray(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->deepCloneArray($value);
            } elseif (is_object($value)) {
                $result[$key] = clone $value;
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * 创建新实例
     */
    public function newQuery(): self
    {
        return new self($this->connection, $this->modelClass);
    }

    /**
     * 获取连接
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * 创建原始 SQL 表达式
     */
    public static function raw(string $expression): Raw
    {
        return new Raw($expression);
    }
}
