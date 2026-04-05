<?php

declare(strict_types=1);

namespace Bin\Database;

use Bin\Database\Debug\DatabaseDebugger;
use Bin\Database\Debug\QueryLog;
use Bin\Database\Model;
use Bin\Database\QueryBuilder\CompilesQueries;
use Bin\Database\Relations\Relation;
use InvalidArgumentException;
use PDO;
use PDOStatement;

/**
 * 查询构建器 - 类似 Laravel 的 Query Builder
 */
class QueryBuilder
{
    use CompilesQueries;

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

    /** @var string|null 悲观锁类型 */
    protected ?string $lock = null;

    /** @var array<string, \Closure> 渴望加载的关系 */
    protected array $eagerLoads = [];

    /** @var array<string, \Closure> 全局作用域 */
    protected array $scopes = [];

    /** @var array<string> 已移除的全局作用域 */
    protected array $removedScopes = [];

    /** @var \Closure|null 页码解析回调（替代直接读取 $_GET） */
    protected static ?\Closure $pageResolver = null;

    public function __construct(PDO $connection, string $modelClass = '')
    {
        $this->connection = $connection;
        $this->modelClass = $modelClass;
    }

    /**
     * 记录查询日志到 DatabaseDebugger
     */
    protected function logQuery(string $sql, array $bindings, float $timeMs, int $rowCount = 0, bool $success = true, ?string $error = null): void
    {
        if (!DatabaseDebugger::isEnabled()) {
            return;
        }

        DatabaseDebugger::log(new QueryLog(
            $sql,
            $bindings,
            $timeMs,
            $this->from ?: 'default',
            $rowCount,
            $success,
            $error
        ));
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
     * 获取查询表名
     */
    public function getTable(): string
    {
        return $this->from;
    }

    /**
     * 获取模型类名
     */
    public function getModelClass(): string
    {
        return $this->modelClass;
    }

    /**
     * 重置查询状态（清除 select/join/where 等）
     */
    public function resetSelect(): self
    {
        $this->columns = ['*'];
        $this->joins = [];
        $this->wheres = [];
        $this->bindings = [];
        $this->orders = [];
        $this->groups = [];
        $this->havings = [];
        $this->limit = null;
        $this->offset = null;
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
     * 添加原始 SQL 到 SELECT 列
     */
    public function selectRaw(string $expression): self
    {
        $this->columns[] = $expression;
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
     * ORDER BY 原始 SQL
     */
    public function orderByRaw(string $sql, array $bindings = []): self
    {
        $this->orders[] = ['type' => 'Raw', 'sql' => $sql];

        foreach ($bindings as $binding) {
            $this->addBinding($binding, 'order');
        }

        return $this;
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
     * GROUP BY 原始 SQL
     */
    public function groupByRaw(string $sql): self
    {
        $this->groups[] = $sql;
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
     * HAVING 原始 SQL
     */
    public function havingRaw(string $sql, array $bindings = []): self
    {
        $this->havings[] = ['type' => 'Raw', 'sql' => $sql, 'boolean' => 'and'];

        foreach ($bindings as $binding) {
            $this->addBinding($binding, 'having');
        }

        return $this;
    }

    /**
     * OR HAVING 原始 SQL
     */
    public function orHavingRaw(string $sql, array $bindings = []): self
    {
        $this->havings[] = ['type' => 'Raw', 'sql' => $sql, 'boolean' => 'or'];

        foreach ($bindings as $binding) {
            $this->addBinding($binding, 'having');
        }

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
            $results->toArray(),
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

        $hasMore = $results->count() > $perPage;

        if ($hasMore) {
            $results = $results->pop();
        }

        return new Paginator(
            $results->toArray(),
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

        $hasMore = $results->count() > $perPage;

        if ($hasMore) {
            $results = $results->pop();
        }

        // 生成下一个游标
        $nextCursor = null;
        if ($hasMore && !$results->isEmpty()) {
            $lastItem = $results->last();
            $id = $lastItem instanceof Model ? $lastItem->id : ($lastItem['id'] ?? null);
            if ($id !== null) {
                $nextCursor = base64_encode(json_encode(['id' => $id]));
            }
        }

        return new CursorPaginator(
            $results->toArray(),
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
     * 设置页码解析回调
     */
    public static function setPageResolver(\Closure $resolver): void
    {
        static::$pageResolver = $resolver;
    }

    /**
     * 恢复默认页码解析
     */
    public static function disablePageResolver(): void
    {
        static::$pageResolver = null;
    }

    /**
     * 解析当前页码
     */
    protected function resolveCurrentPage(string $pageName = 'page'): int
    {
        if (static::$pageResolver !== null) {
            return (static::$pageResolver)($pageName);
        }

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
        if (static::$pageResolver !== null) {
            return '/';
        }

        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    /**
     * 解析查询参数
     */
    protected function resolveQuery(): array
    {
        if (static::$pageResolver !== null) {
            return [];
        }

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

        return $results->isEmpty() ? null : $results->first();
    }

    /**
     * 获取第一条记录或执行回调
     */
    public function firstOr(callable $callback): mixed
    {
        $result = $this->first();
        return $result !== null ? $result : $callback();
    }

    /**
     * 查找或执行回调
     */
    public function findOr(int $id, callable $callback): mixed
    {
        $result = $this->find($id);
        return $result !== null ? $result : $callback();
    }

    /**
     * 查找或抛出异常
     */
    public function findOrFail(int $id): mixed
    {
        $result = $this->find($id);

        if ($result === null) {
            throw new InvalidArgumentException("No query results for model [{$id}]");
        }

        return $result;
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
    public function findMany(array $ids): Collection
    {
        if (empty($ids)) {
            return new Collection();
        }

        return $this->whereIn('id', $ids)->get();
    }

    /**
     * 获取所有记录
     */
    public function get(): Collection
    {
        // 应用全局作用域
        $this->applyScopes();

        // 处理 withAggregate（withCount, withSum 等）
        $withAggregates = $this->withAggregates;
        if (!empty($withAggregates)) {
            $this->applyWithAggregateSelects($withAggregates);
        }

        $sql = $this->toSql();
        $bindings = $this->getBindings();

        $startTime = microtime(true);
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($bindings);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);            // 如果有模型类，则转换为模型实例
            if ($this->modelClass && class_exists($this->modelClass)) {
                $models = array_map(fn($item) => $this->hydrateModel($item), $results);

                // 处理 withAggregate 结果
                if (!empty($withAggregates)) {
                    $models = $this->hydrateWithAggregates($models, $results, $withAggregates);
                }

                // 渴望加载关系
                if (!empty($this->eagerLoads)) {
                    $models = $this->eagerLoadRelations($models);
                }

                return new Collection($models);
            }
        } catch (\Throwable $e) {
            $timeMs = (microtime(true) - $startTime) * 1000;
            $this->logQuery($sql, $bindings, $timeMs, 0, false, $e->getMessage());
            throw $e;
        }

        return new Collection($results);
    }

    /**
     * 应用 withAggregate 子查询到 SELECT 列
     */

    /**
     * 应用 withAggregate 子查询到 SELECT 列
     */
    protected function applyWithAggregateSelects(array $aggregates): void
    {
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

            $relationTable = $relationObj->getQuery()->getTable();
            $foreignKey = $relationObj->getForeignKeyName();
            $localKey = $relationObj->getLocalKey();

            // 构建子查询：SELECT function(column) FROM relation_table WHERE fk = parent.lk
            if ($function === 'count') {
                $subSelect = "SELECT COUNT(*) FROM {$relationTable} WHERE {$relationTable}.{$foreignKey} = {$parentTable}.{$localKey}";
            } else {
                $subSelect = "SELECT {$function}({$column}) FROM {$relationTable} WHERE {$relationTable}.{$foreignKey} = {$parentTable}.{$localKey}";
            }

            // 如果有约束，添加到子查询
            if ($config['constraints'] !== null) {
                $subQuery = new self($this->connection, $relationObj->getQuery()->modelClass);
                $subQuery->from($relationTable);
                $config['constraints']($subQuery);

                $constraintSql = $subQuery->compileWheres();
                if (!empty($constraintSql)) {
                    $conditions = substr($constraintSql, 7);
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
     * 获取纯数组结果（不包装为 Collection）
     */
    public function getArray(): array
    {
        $collection = $this->get();
        return $collection->toArray();
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
     */
    protected function eagerLoadRelationNested(array $models, string $name, ?\Closure $constraints): array
    {
        if (empty($models)) {
            return $models;
        }

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
        // 创建模型实例获取关系
        $model = new $this->modelClass();

        // 获取无约束的关系对象
        $relationObj = Relation::noConstraints(function () use ($model, $relation) {
            return $model->$relation();
        });

        $parentTable = $this->getTable();

        // 让关系对象自己生成 EXISTS 子查询
        if (method_exists($relationObj, 'getExistenceQuery')) {
            [$subSql, $subBindings] = $relationObj->getExistenceQuery($parentTable);
        } else {
            $relationTable = $relationObj->getQuery()->getTable();
            $foreignKey = $relationObj->getForeignKeyName();
            $localKey = $relationObj->getLocalKey();

            $subSql = "SELECT 1 FROM {$relationTable} WHERE {$relationTable}.{$foreignKey} = {$parentTable}.{$localKey}";
            $subBindings = [];
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
                $conditions = substr($constraintSql, 7); // strlen(' WHERE ') = 7
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

    /** @var array<string, array{relation: string, column: string, alias: string, constraints: ?\Closure}> withAggregate 统计 */
    protected array $withAggregates = [];

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

        if ($results->isEmpty()) {
            return 0;
        }

        $result = $results->first();

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

        $startTime = microtime(true);
        try {
            $stmt = $this->connection->prepare($sql);

            foreach ($values as $record) {
                $stmt->execute($record);
            }

            $timeMs = (microtime(true) - $startTime) * 1000;
            $this->logQuery($sql, $values, $timeMs, count($values));

            return true;
        } catch (\Throwable $e) {
            $timeMs = (microtime(true) - $startTime) * 1000;
            $this->logQuery($sql, [], $timeMs, 0, false, $e->getMessage());
            throw $e;
        }
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

        $startTime = microtime(true);
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($bindings);
            $rowCount = $stmt->rowCount();

            $timeMs = (microtime(true) - $startTime) * 1000;
            $this->logQuery($sql, $bindings, $timeMs, $rowCount);

            return $rowCount;
        } catch (\Throwable $e) {
            $timeMs = (microtime(true) - $startTime) * 1000;
            $this->logQuery($sql, $bindings, $timeMs, 0, false, $e->getMessage());
            throw $e;
        }
    }

    /**
     * 删除记录
     */
    public function delete(): int
    {
        $sql = "DELETE FROM {$this->from} {$this->compileWheres()}";

        $bindings = $this->getBindings();

        $startTime = microtime(true);
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($bindings);
            $rowCount = $stmt->rowCount();

            $timeMs = (microtime(true) - $startTime) * 1000;
            $this->logQuery($sql, $bindings, $timeMs, $rowCount);

            return $rowCount;
        } catch (\Throwable $e) {
            $timeMs = (microtime(true) - $startTime) * 1000;
            $this->logQuery($sql, $bindings, $timeMs, 0, false, $e->getMessage());
            throw $e;
        }
    }

    /**
     * 增加列值
     */
    public function increment(string $column, int $amount = 1, array $extra = []): int
    {
        return $this->buildIncrementQuery($column, '+', $amount, $extra);
    }

    /**
     * 减少列值
     */
    public function decrement(string $column, int $amount = 1, array $extra = []): int
    {
        return $this->buildIncrementQuery($column, '-', $amount, $extra);
    }

    /**
     * 构建增量/减量查询
     */
    private function buildIncrementQuery(string $column, string $operator, int $amount, array $extra): int
    {
        $sql = "UPDATE {$this->from} SET {$column} = {$column} {$operator} ?";

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
     * 调试输出 SQL 和绑定参数（不终止程序）
     */
    public function dump(): self
    {
        $sql = $this->toSql();
        $bindings = $this->getBindings();

        dump(['sql' => $sql, 'bindings' => $bindings]);

        return $this;
    }

    /**
     * 调试输出 SQL 和绑定参数并终止程序
     */
    public function dd(): never
    {
        $sql = $this->toSql();
        $bindings = $this->getBindings();

        dd(['sql' => $sql, 'bindings' => $bindings]);
    }

    /**
     * 获取绑定参数
     */
    public function getBindings(): array
    {
        return array_merge(
            $this->bindings['where'] ?? [],
            $this->bindings['having'] ?? [],
            $this->bindings['order'] ?? []
        );
    }

    /**
     * 添加绑定参数
     */
    public function addBinding(mixed $value, string $type = 'where'): self
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
     * UNION 联合查询
     */
    public function union(self|\Closure $query, bool $all = false): self
    {
        if ($query instanceof \Closure) {
            $query($query = $this->forNestedWhere());
        }

        $this->unionQueries[] = ['query' => $query, 'all' => $all];

        return $this;
    }

    /**
     * UNION ALL 联合查询
     */
    public function unionAll(self|\Closure $query): self
    {
        return $this->union($query, true);
    }

    /**
     * 悲观锁 — FOR UPDATE
     */
    public function lockForUpdate(): self
    {
        $this->lock = 'FOR UPDATE';
        return $this;
    }

    /**
     * 悲观锁 — LOCK IN SHARE MODE
     */
    public function sharedLock(): self
    {
        $this->lock = 'LOCK IN SHARE MODE';
        return $this;
    }

    /**
     * 分块处理查询结果
     */
    public function chunk(int $count, callable $callback): bool
    {
        $page = 1;

        do {
            $results = $this->forPage($page, $count)->get();

            if ($results->isEmpty()) {
                break;
            }

            if ($callback($results) === false) {
                return false;
            }

            unset($results);
            $page++;
        } while (true);

        return true;
    }

    /**
     * 按 ID 分块处理（更高效，不会偏移遗漏）
     */
    public function chunkById(int $count, callable $callback, string $column = 'id'): bool
    {
        $lastId = 0;

        do {
            $results = $this->where($column, '>', $lastId)
                ->orderBy($column)
                ->limit($count)
                ->get();

            if ($results->isEmpty()) {
                break;
            }

            if ($callback($results) === false) {
                return false;
            }

            $lastItem = $results->last();

            if ($lastItem instanceof Model) {
                $lastId = $lastItem->getKey();
            } else {
                $lastId = $lastItem[$column] ?? 0;
            }

            unset($results);
        } while (true);

        return true;
    }

    /**
     * 逐条迭代处理
     */
    public function each(callable $callback, int $count = 1000): bool
    {
        return $this->chunk($count, function (Collection $results) use ($callback) {
            foreach ($results as $key => $item) {
                if ($callback($item, $key) === false) {
                    return false;
                }
            }
        });
    }

    /**
     * 按 ID 逐条迭代处理
     */
    public function eachById(callable $callback, int $count = 1000, string $column = 'id'): bool
    {
        return $this->chunkById($count, function (Collection $results) use ($callback) {
            foreach ($results as $key => $item) {
                if ($callback($item, $key) === false) {
                    return false;
                }
            }
        }, $column);
    }

    /**
     * 链式回调
     */
    public function tap(callable $callback): self
    {
        $callback($this);
        return $this;
    }

    /**
     * 执行 EXPLAIN
     */
    public function explain(): array
    {
        $sql = "EXPLAIN {$this->toSql()}";
        $bindings = $this->getBindings();

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        $this->lock = $this->lock;
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
