<?php

declare(strict_types=1);

namespace Bin\Database;

use Bin\Database\Debug\DatabaseDebugger;
use Bin\Database\Debug\QueryLog;
use Bin\Database\Model;
use Bin\Database\QueryBuilder\BuildsRelationships;
use Bin\Database\QueryBuilder\BuildsWhereClauses;
use Bin\Database\QueryBuilder\ChunksResults;
use Bin\Database\QueryBuilder\CompilesQueries;
use Bin\Database\QueryBuilder\PaginatesResults;
use InvalidArgumentException;
use PDO;

/**
 * 查询构建器 - 类似 Laravel 的 Query Builder
 */
class QueryBuilder
{
    use CompilesQueries;
    use BuildsWhereClauses;
    use BuildsRelationships;
    use PaginatesResults;
    use ChunksResults;

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

    /** @var array<string, array{relation: string, column: string, alias: string, constraints: ?\Closure}> withAggregate 统计 */
    protected array $withAggregates = [];

    /** @var bool 全局作用域是否已应用（保证幂等，避免复用 builder 时重复叠加条件） */
    protected bool $scopesApplied = false;

    /** @var \Closure|null 查询级 delete() 的替换行为（如软删除转 update） */
    protected ?\Closure $onDelete = null;

    /** @var string|null 连接名（用于查询日志；null 表示默认连接） */
    protected ?string $connectionName = null;

    public function __construct(PDO $connection, string $modelClass = '')
    {
        $this->connection = $connection;
        $this->modelClass = $modelClass;
    }

    /**
     * 设置连接名（查询日志与调试报告按名归类）
     */
    public function setConnectionName(?string $name): self
    {
        $this->connectionName = $name;

        return $this;
    }

    /**
     * 获取连接名
     */
    public function getConnectionName(): ?string
    {
        return $this->connectionName;
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
            $this->connectionName ?? 'default',
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
     * 应用全局作用域（幂等：同一 builder 只应用一次，克隆后重新应用）
     */
    public function applyScopes(): self
    {
        if ($this->scopesApplied) {
            return $this;
        }

        $this->scopesApplied = true;

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
     * CROSS JOIN（笛卡尔积，无 ON 条件，后续约束用 where 追加）
     */
    public function crossJoin(string $table): self
    {
        $this->joins[] = ['type' => 'cross', 'table' => $table];

        return $this;
    }

    /**
     * 子查询 JOIN：(SELECT ...) as `alias` ON first operator second
     *
     * 子查询的绑定进入 'join' 桶——SELECT 编译顺序中 JOIN 在 WHERE 之前，
     * 绑定顺序必须与占位符出现顺序一致。注意 update()/delete() 不编译 JOIN，
     * 因此 joinSub 只用于 SELECT 场景。
     */
    public function joinSub(self|\Closure $query, string $as, string $first, string $operator, string $second, string $type = 'inner'): self
    {
        if ($query instanceof \Closure) {
            $query($query = $this->forNestedWhere());
        }

        $this->joins[] = [
            'type' => $type,
            'table' => '(' . $query->toSql() . ') as ' . $this->wrap($as),
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        foreach ($query->getBindings() as $binding) {
            $this->addBinding($binding, 'join');
        }

        return $this;
    }

    /**
     * 左连接子查询
     */
    public function leftJoinSub(self|\Closure $query, string $as, string $first, string $operator, string $second): self
    {
        return $this->joinSub($query, $as, $first, $operator, $second, 'left');
    }

    /**
     * ORDER BY
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $direction = strtolower($direction);

        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException("Order direction must be 'asc' or 'desc', got [{$direction}].");
        }

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
    public function findOr(mixed $id, callable $callback): mixed
    {
        $result = $this->find($id);
        return $result !== null ? $result : $callback();
    }

    /**
     * 查找或抛出异常
     */
    public function findOrFail(mixed $id): mixed
    {
        $result = $this->find($id);

        if ($result === null) {
            throw new ModelNotFoundException($this->modelClass ?: Model::class, [$id]);
        }

        return $result;
    }

    /**
     * 根据 ID 查找
     */
    public function find(mixed $id, ?string $column = null): mixed
    {
        return $this->where($column ?? $this->getModelKeyName(), $id)->first();
    }

    /**
     * 根据 ID 数组查找
     */
    public function findMany(array $ids, ?string $column = null): Collection
    {
        if (empty($ids)) {
            return new Collection();
        }

        return $this->whereIn($column ?? $this->getModelKeyName(), $ids)->get();
    }

    private function getModelKeyName(): string
    {
        if ($this->modelClass !== '' && is_subclass_of($this->modelClass, Model::class)) {
            /** @var Model $model */
            $model = new $this->modelClass();
            return $model->getKeyName();
        }

        return 'id';
    }

    /**
     * 按主键约束（单值或数组）
     */
    public function whereKey(mixed $ids): self
    {
        $ids = is_array($ids) ? array_values($ids) : [$ids];

        if (empty($ids)) {
            return $this->whereRaw('1 = 0');
        }

        return $this->whereIn($this->getModelKeyName(), $ids);
    }

    /**
     * 按主键排除（单值或数组）
     */
    public function whereKeyNot(mixed $ids): self
    {
        $ids = is_array($ids) ? array_values($ids) : [$ids];

        if (empty($ids)) {
            return $this;
        }

        return $this->whereNotIn($this->getModelKeyName(), $ids);
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
     * 获取纯数组结果（不包装为 Collection）
     */
    public function getArray(): array
    {
        $collection = $this->get();
        return $collection->toArray();
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
     * 聚合查询（count/max/min/avg/sum）
     *
     * 直接执行聚合 SQL 而不经过模型水合：聚合行不是真实模型数据，
     * 水合会触发伪造的 retrieved 事件并浪费一次模型构造。
     */
    protected function aggregate(string $function, string $columns = '*'): mixed
    {
        $this->applyScopes();

        $this->aggregate = compact('function', 'columns');

        $previousColumns = $this->columns;

        $sql = $this->toSql();
        $bindings = $this->getBindings();

        $this->aggregate = null;
        $this->columns = $previousColumns;

        $startTime = microtime(true);
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($bindings);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->logQuery($sql, $bindings, (microtime(true) - $startTime) * 1000);

            return $row === false ? 0 : ($row['aggregate'] ?? 0);
        } catch (\Throwable $e) {
            $this->logQuery($sql, $bindings, (microtime(true) - $startTime) * 1000, 0, false, $e->getMessage());
            throw $e;
        }
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
        $columns = implode(', ', array_map(fn ($key) => $this->wrap((string) $key), $keys));
        $parameters = implode(', ', array_map(fn ($key) => ":{$key}", $keys));

        $sql = "INSERT INTO {$this->wrap($this->from)} ({$columns}) VALUES ({$parameters})";

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
     *
     * 数字主键转 int（自增场景），非数字值（UUID 等 string 主键）原样返回，
     * 不能无条件强转 int——那会损坏 string 键。
     */
    public function insertGetId(array $values): int|string
    {
        $this->insert($values);

        $id = $this->connection->lastInsertId();

        return $id === false ? 0 : (is_numeric($id) ? (int) $id : $id);
    }

    /**
     * 忽略冲突插入（行已存在时跳过而非报错）
     *
     * MySQL: INSERT IGNORE；SQLite: INSERT OR IGNORE；PostgreSQL: ON CONFLICT DO NOTHING。
     * 返回实际插入的行数（受驱动 rowCount 语义影响，冲突行不计入）。
     */
    public function insertOrIgnore(array $values): int
    {
        if (empty($values)) {
            return 0;
        }

        $verb = match ($this->getDriverName()) {
            'mysql' => 'INSERT IGNORE INTO',
            'sqlite' => 'INSERT OR IGNORE INTO',
            'pgsql' => 'INSERT INTO',
            default => 'INSERT INTO',
        };

        [$sql, $bindings] = $this->buildMultiRowInsert($values, $verb);

        if ($this->getDriverName() === 'pgsql') {
            $sql .= ' ON CONFLICT DO NOTHING';
        }

        $startTime = microtime(true);
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($bindings);
            $rowCount = $stmt->rowCount();

            $this->logQuery($sql, $bindings, (microtime(true) - $startTime) * 1000, $rowCount);

            return $rowCount;
        } catch (\Throwable $e) {
            $this->logQuery($sql, $bindings, (microtime(true) - $startTime) * 1000, 0, false, $e->getMessage());
            throw $e;
        }
    }

    /**
     * 原子 upsert：存在则更新，不存在则插入
     *
     * 驱动方言：MySQL 走 ON DUPLICATE KEY UPDATE（按表索引判重，$uniqueBy 仅作
     * 列集参考）；SQLite/PostgreSQL 走 ON CONFLICT($uniqueBy) DO UPDATE。
     * $update 缺省为更新全部插入列；模型启用时间戳时自动补齐 created_at/updated_at。
     */
    public function upsert(array $values, array $uniqueBy, ?array $update = null): int
    {
        if (empty($values)) {
            return 0;
        }

        [$sql, $bindings] = $this->buildUpsertStatement($values, $uniqueBy, $update);

        $startTime = microtime(true);
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($bindings);
            $rowCount = $stmt->rowCount();

            $this->logQuery($sql, $bindings, (microtime(true) - $startTime) * 1000, $rowCount);

            return $rowCount;
        } catch (\Throwable $e) {
            $this->logQuery($sql, $bindings, (microtime(true) - $startTime) * 1000, 0, false, $e->getMessage());
            throw $e;
        }
    }

    /**
     * 构建 upsert 语句（纯 SQL 构建，便于分方言单测），返回 [sql, bindings]
     *
     * @return array{0: string, 1: list<mixed>}
     */
    protected function buildUpsertStatement(array $values, array $uniqueBy, ?array $update): array
    {
        $this->mergeUpsertTimestamps($values);

        [$sql, $bindings] = $this->buildMultiRowInsert($values, 'INSERT INTO');

        $updateColumns = $update ?? array_keys(reset($values));
        $wrappedUpdate = array_map(fn (string $column): string => $this->wrap($column), $updateColumns);

        if ($this->getDriverName() === 'mysql') {
            $assignments = implode(', ', array_map(
                fn (string $column): string => "{$column} = VALUES({$column})",
                $wrappedUpdate
            ));

            return [$sql . " ON DUPLICATE KEY UPDATE {$assignments}", $bindings];
        }

        $conflict = implode(', ', array_map(
            fn (string $column): string => $this->wrap($column),
            $uniqueBy
        ));

        $assignments = implode(', ', array_map(
            fn (string $column): string => "{$column} = `excluded`.{$column}",
            $wrappedUpdate
        ));

        return [$sql . " ON CONFLICT({$conflict}) DO UPDATE SET {$assignments}", $bindings];
    }

    /**
     * 存在则更新，不存在则插入
     *
     * 返回是否执行了写入（存在且 $values 为空时视为成功，不发 UPDATE）。
     */
    public function updateOrInsert(array $attributes, array $values = []): bool
    {
        if (!$this->clone()->where($attributes)->exists()) {
            return $this->clone()->insert(array_merge($attributes, $values));
        }

        if (empty($values)) {
            return true;
        }

        return $this->clone()->where($attributes)->update($values) >= 0;
    }

    /**
     * 构建多行 INSERT 语句（统一列集，缺列补 null），返回 [sql, bindings]
     *
     * @return array{0: string, 1: list<mixed>}
     */
    private function buildMultiRowInsert(array $values, string $verb): array
    {
        if (!is_array(reset($values))) {
            $values = [$values];
        }

        // 取所有行的列并集，保证每行占位符数量一致
        $keys = [];
        foreach ($values as $row) {
            foreach (array_keys($row) as $key) {
                $keys[$key] = true;
            }
        }
        $keys = array_keys($keys);

        $columns = implode(', ', array_map(fn (string $key): string => $this->wrap($key), $keys));
        $placeholders = '(' . rtrim(str_repeat('?,', count($keys)), ',') . ')';

        $sql = "{$verb} {$this->wrap($this->from)} ({$columns}) VALUES "
            . implode(', ', array_fill(0, count($values), $placeholders));

        $bindings = [];
        foreach ($values as $row) {
            foreach ($keys as $key) {
                $bindings[] = $row[$key] ?? null;
            }
        }

        return [$sql, $bindings];
    }

    /**
     * upsert 前为各行补齐模型时间戳列
     *
     * @param array<int, array<string, mixed>> $values
     */
    private function mergeUpsertTimestamps(array &$values): void
    {
        if (!is_array(reset($values))) {
            $values = [$values];
        }

        if ($this->modelClass === '' || !is_subclass_of($this->modelClass, Model::class)) {
            return;
        }

        /** @var Model $model */
        $model = new $this->modelClass();

        if (!$model->usesTimestamps()) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        foreach ($values as &$row) {
            $row[$model::CREATED_AT] ??= $now;
            $row[$model::UPDATED_AT] ??= $now;
        }
    }

    /**
     * 获取当前 PDO 驱动名（方言分支用）
     */
    private function getDriverName(): string
    {
        return (string) $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * 更新记录
     */
    public function update(array $values): int
    {
        if (empty($values)) {
            throw new InvalidArgumentException('Update requires at least one column.');
        }

        // 写操作必须应用全局作用域：否则软删除/租户隔离等作用域会被静默绕过
        $this->applyScopes();

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
     *
     * 全局作用域会被应用；若作用域注册了 onDelete 替换行为（软删除），
     * 则执行该行为而不是物理 DELETE。
     */
    public function delete(): int
    {
        $this->applyScopes();

        if ($this->onDelete !== null) {
            return ($this->onDelete)($this);
        }

        $sql = "DELETE FROM {$this->wrap($this->from)} {$this->compileWheres()}";

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
        $this->applyScopes();

        $sql = "UPDATE {$this->wrap($this->from)} SET {$this->wrap($column)} = {$this->wrap($column)} {$operator} ?";

        if (!empty($extra)) {
            $sets = [];
            foreach (array_keys($extra) as $key) {
                $sets[] = $this->wrap($key) . ' = ?';
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
     * 注册查询级 delete() 的替换行为
     *
     * 回调接收当前 builder，返回受影响行数。软删除等作用域用它把
     * `Model::where(...)->delete()` 转换为 UPDATE deleted_at。
     */
    public function onDelete(\Closure $callback): self
    {
        $this->onDelete = $callback;

        return $this;
    }

    /**
     * 清空已有排序
     */
    public function resetOrders(): self
    {
        $this->orders = [];

        return $this;
    }

    /**
     * 检查记录是否存在
     */
    public function exists(): bool
    {
        $this->applyScopes();

        $query = $this->clone()->limit(1);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $startTime = microtime(true);
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($bindings);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->logQuery($sql, $bindings, (microtime(true) - $startTime) * 1000);

            return $row !== false;
        } catch (\Throwable $e) {
            $this->logQuery($sql, $bindings, (microtime(true) - $startTime) * 1000, 0, false, $e->getMessage());
            throw $e;
        }
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
     * 获取绑定参数（顺序与占位符在 SQL 中出现的顺序一致：join → where → having → order → union）
     */
    public function getBindings(): array
    {
        return array_merge(
            $this->bindings['join'] ?? [],
            $this->bindings['where'] ?? [],
            $this->bindings['having'] ?? [],
            $this->bindings['order'] ?? [],
            $this->bindings['union'] ?? []
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
        /** @var Model $model */
        $model = new $this->modelClass();

        return $model->newFromBuilder($attributes);
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

        // 子查询的绑定必须并入主查询，否则执行时占位符数量与绑定数不匹配
        foreach ($query->getBindings() as $binding) {
            $this->addBinding($binding, 'union');
        }

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
        // 克隆体允许重新应用全局作用域（幂等标记不随克隆传递）
        $this->scopesApplied = false;
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
