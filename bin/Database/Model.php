<?php

declare(strict_types=1);

namespace Bin\Database;

use Bin\Database\Model\HasAttributes;
use Bin\Database\Model\HasEvents;
use Bin\Database\Model\HasRelationships;
use Bin\Database\Model\HasTimestamps;
use Bin\Database\Model\HasSerialization;
use Bin\Database\Model\HasStrictMode;
use Bin\Database\ConnectionManager;
use Bin\Model\Model as BaseModel;
use PDO;
use InvalidArgumentException;

/**
 * Eloquent 风格的 ORM 模型基类
 */
abstract class Model extends BaseModel implements \ArrayAccess, \JsonSerializable
{
    use HasAttributes;
    use HasEvents;
    use HasRelationships;
    use HasTimestamps;
    use HasSerialization;
    use HasStrictMode;

    /**
     * 数据库连接
     */
    protected static ?PDO $connection = null;

    /**
     * 模型级连接名（用于多连接场景）
     */
    protected ?string $connectionName = null;

    /**
     * 表名
     */
    protected string $table;

    /**
     * 是否使用时间戳
     */
    protected bool $timestamps = true;

    /**
     * 创建时间字段
     */
    public const CREATED_AT = 'created_at';

    /**
     * 更新时间字段
     */
    public const UPDATED_AT = 'updated_at';

    /**
     * 全局作用域（per-class 存储）
     */
    protected static array $globalScopes = [];

    /**
     * 是否已引导（per-class 存储）
     */
    protected static array $bootedModels = [];

    /**
     * 多态映射（per-class 存储）
     */
    protected static array $morphMap = [];

    /**
     * 引导模型
     */
    public static function boot(): void
    {
        $class = static::class;

        if (isset(static::$bootedModels[$class])) {
            return;
        }

        static::$bootedModels[$class] = true;

        // 调用 trait 的 boot 方法
        static::bootTraits();
    }

    /**
     * 初始化模型实例（每次 new 都调用）
     * 调用 trait 的 initialize 方法
     */
    public function initialize(): void
    {
        $this->initializeTraits();
    }

    /**
     * 引导所有 traits 的 initialize 方法
     */
    protected function initializeTraits(): void
    {
        foreach (class_uses(static::class) as $trait) {
            $method = 'initialize' . static::getClassBasename($trait);

            if (method_exists($this, $method)) {
                $this->$method();
            }
        }
    }

    /**
     * 引导所有 traits
     */
    protected static function bootTraits(): void
    {
        $class = static::class;

        foreach (class_uses($class) as $trait) {
            $method = 'boot' . static::getClassBasename($trait);

            if (method_exists($class, $method)) {
                static::$method();
            }
        }
    }

    /**
     * 获取类的基础名称
     */
    protected static function getClassBasename(string $class): string
    {
        return substr($class, strrpos($class, '\\') + 1);
    }

    /**
     * 获取表名
     */
    public function getTable(): string
    {
        return $this->table ?? $this->guessTableName();
    }

    /**
     * 猜测表名
     */
    protected function guessTableName(): string
    {
        $class = get_class($this);

        // 获取类名（不含命名空间）
        $className = substr($class, strrpos($class, '\\') + 1);

        // 转为蛇形命名并复数化
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className)) . 's';
    }

    /**
     * 获取主键
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * 获取主键值
     */
    public function getKey(): mixed
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * 获取数据库连接
     */
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            self::$connection = ConnectionManager::getConnection();
        }

        return self::$connection;
    }

    /**
     * 设置数据库连接（用于测试）
     */
    public static function setConnection(?PDO $connection): void
    {
        self::$connection = $connection;
        ConnectionManager::setConnection($connection);
    }

    /**
     * 获取查询构建器
     */
    public static function query(): QueryBuilder
    {
        // 确保模型已引导
        static::boot();

        $model = new static();

        $query = new QueryBuilder(self::getConnection(), get_class($model));

        $query->from($model->getTable());

        // 应用当前模型类的全局作用域
        $classScopes = static::$globalScopes[static::class] ?? [];
        foreach ($classScopes as $identifier => $callback) {
            $query->withGlobalScope($identifier, $callback);
        }

        // 应用模型级默认 eager load
        if (!empty($model->getWith())) {
            $query->with($model->getWith());
        }

        return $query;
    }

    /**
     * 添加全局作用域
     */
    public static function addGlobalScope(string $identifier, \Closure $callback): void
    {
        static::$globalScopes[static::class][$identifier] = $callback;
    }

    /**
     * 移除全局作用域
     */
    public static function forgetGlobalScope(string $identifier): void
    {
        unset(static::$globalScopes[static::class][$identifier]);
    }

    /**
     * 获取所有全局作用域
     */
    public static function getGlobalScopes(): array
    {
        return static::$globalScopes[static::class] ?? [];
    }

    /**
     * 清除所有全局作用域
     */
    public static function clearGlobalScopes(): void
    {
        static::$globalScopes[static::class] = [];
    }

    /**
     * 重置引导状态（用于测试）
     */
    public static function resetBooted(): void
    {
        $class = static::class;
        unset(static::$bootedModels[$class]);
        static::$globalScopes[static::class] = [];
    }

    /**
     * 静态调用转发到查询构建器（优先检测本地作用域）
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        // 检查本地作用域：scope{Method}
        $scopeMethod = 'scope' . ucfirst($method);
        if (method_exists(static::class, $scopeMethod)) {
            $query = static::query();
            return static::newStatic()->$scopeMethod($query, ...$parameters);
        }

        return static::query()->$method(...$parameters);
    }

    /**
     * 动态调用转发到查询构建器（优先检测本地作用域）
     */
    public function __call(string $method, array $parameters): mixed
    {
        // 检查本地作用域：scope{Method}
        $scopeMethod = 'scope' . ucfirst($method);
        if (method_exists($this, $scopeMethod)) {
            $query = static::query();
            return $this->$scopeMethod($query, ...$parameters);
        }

        return static::query()->$method(...$parameters);
    }

    /**
     * 创建新的静态实例
     */
    protected static function newStatic(): static
    {
        return new static();
    }

    /**
     * 根据 ID 查找
     */
    public static function find(mixed $id): ?self
    {
        return static::query()->find($id);
    }

    /**
     * 根据 ID 数组查找
     */
    public static function findMany(array $ids): Collection
    {
        return static::query()->findMany($ids);
    }

    /**
     * 查找或失败
     */
    public static function findOrFail(mixed $id): self
    {
        $result = static::find($id);

        if ($result === null) {
            throw new InvalidArgumentException("No query results for model [{$id}]");
        }

        return $result;
    }

    /**
     * 根据 ID 查找或创建
     */
    public static function findOrNew(mixed $id): self
    {
        return static::find($id) ?? new static();
    }

    /**
     * 查找第一条匹配记录，不存在则创建
     */
    public static function firstOrCreate(array $attributes, array $values = []): self
    {
        $instance = static::where($attributes)->first();

        if ($instance !== null) {
            return $instance;
        }

        return static::create(array_merge($attributes, $values));
    }

    /**
     * 查找第一条匹配记录，不存在则返回新实例（不保存）
     */
    public static function firstOrNew(array $attributes, array $values = []): self
    {
        $instance = static::where($attributes)->first();

        if ($instance !== null) {
            return $instance;
        }

        return new static(array_merge($attributes, $values));
    }

    /**
     * 查找并更新，不存在则创建
     */
    public static function updateOrCreate(array $attributes, array $values = []): self
    {
        $instance = static::where($attributes)->first();

        if ($instance !== null) {
            $instance->fill($values)->save();
            return $instance;
        }

        return static::create(array_merge($attributes, $values));
    }

    /**
     * 通过单个列值查找第一条记录
     */
    public static function firstWhere(string $column, mixed $operator = null, mixed $value = null): mixed
    {
        return static::where($column, $operator, $value)->first();
    }

    /**
     * 查找唯一匹配记录（0 或 >1 条时抛出异常）
     */
    public static function sole(array|string $columns = ['*']): self
    {
        $query = static::query();

        if (is_array($columns)) {
            $query->select($columns);
        }

        $results = $query->limit(2)->get();

        if ($results->count() === 0) {
            throw new InvalidArgumentException('No query results for model [' . static::class . ']');
        }

        if ($results->count() > 1) {
            throw new InvalidArgumentException('Multiple query results for model [' . static::class . ']');
        }

        return $results->first();
    }

    /**
     * 获取所有记录
     */
    public static function all(): Collection
    {
        return static::query()->get();
    }

    /**
     * 分页查询
     */
    public static function paginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null): LengthAwarePaginator
    {
        return static::query()->paginate($perPage, $columns, $pageName, $page);
    }

    /**
     * 简单分页（无总数统计）
     */
    public static function simplePaginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null): Paginator
    {
        return static::query()->simplePaginate($perPage, $columns, $pageName, $page);
    }

    /**
     * 游标分页
     */
    public static function cursorPaginate(int $perPage = 15, array $columns = ['*'], string $cursorName = 'cursor', ?string $cursor = null): CursorPaginator
    {
        return static::query()->cursorPaginate($perPage, $columns, $cursorName, $cursor);
    }

    /**
     * 创建新记录
     */
    public static function create(array $attributes): self
    {
        $model = new static($attributes);

        $model->save();

        return $model;
    }

    /**
     * 批量创建
     */
    public static function insert(array $values): bool
    {
        return static::query()->insert($values);
    }

    /**
     * 更新记录
     */
    public static function updateWhere(array $values, array $where): int
    {
        return static::query()->where($where)->update($values);
    }

    /**
     * 删除记录
     */
    public static function deleteWhere(array $where): int
    {
        return static::query()->where($where)->delete();
    }

    /**
     * 构造函数
     */
    public function __construct(array $attributes = [])
    {
        $this->initialize();

        $this->fill($attributes);
    }

    /**
     * 保存模型
     */
    public function save(): bool
    {
        $query = static::query();

        // 触发 saving 事件
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        if ($this->exists) {
            $saved = $this->performUpdate($query);
        } else {
            $saved = $this->performInsert($query);
        }

        if ($saved) {
            $this->original = $this->attributes;
            $this->changes = [];

            // 触发 saved 事件
            $this->fireModelEvent('saved');
        }

        return $saved;
    }

    /**
     * 执行插入
     */
    protected function performInsert(QueryBuilder $query): bool
    {
        // 触发 creating 事件
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        if ($this->timestamps) {
            $this->setCreatedAt();
            $this->setUpdatedAt();
        }

        $attributes = $this->getAttributes();

        if ($this->getIncrementing()) {
            $id = $query->insertGetId($attributes);
            $this->setAttribute($this->getKeyName(), $id);
        } else {
            $query->insert($attributes);
        }

        $this->exists = true;
        $this->wasRecentlyCreated = true;

        // 触发 created 事件
        $this->fireModelEvent('created');

        return true;
    }

    /**
     * 执行更新
     */
    protected function performUpdate(QueryBuilder $query): bool
    {
        // 触发 updating 事件
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        if ($this->timestamps) {
            $this->setUpdatedAt();
        }

        $dirty = $this->getDirty();

        if (empty($dirty)) {
            return true;
        }

        $result = $query->where($this->getKeyName(), $this->getKey())->update($dirty) > 0;

        if ($result) {
            // 触发 updated 事件
            $this->fireModelEvent('updated');
        }

        return $result;
    }

    /**
     * 删除模型
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        // 触发 deleting 事件
        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        $result = static::query()->where($this->getKeyName(), $this->getKey())->delete() > 0;

        if ($result) {
            $this->exists = false;

            // 触发 deleted 事件
            $this->fireModelEvent('deleted');
        }

        return $result;
    }

    /**
     * 创建新的查询构建器
     */
    protected function newQuery(): QueryBuilder
    {
        return static::query();
    }

    /**
     * 根据 ID 删除模型
     */
    public static function destroy(Collection|array|int|string $ids): int
    {
        $ids = $ids instanceof Collection ? $ids->toArray() : (is_array($ids) ? $ids : func_get_args());

        $count = 0;

        foreach ($ids as $id) {
            $model = static::find((int) $id);

            if ($model !== null && $model->delete()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * 复制模型
     */
    public function replicate(): self
    {
        $model = new static($this->attributes);

        $model->exists = false;
        $model->wasRecentlyCreated = false;

        // 移除主键
        $model->setAttribute($this->getKeyName(), null);

        return $model;
    }

    /**
     * 刷新模型
     */
    public function fresh(): ?self
    {
        if (!$this->exists) {
            return null;
        }

        return static::find($this->getKey());
    }

    /**
     * 刷新并更新属性
     */
    public function refresh(): self
    {
        if (!$this->exists) {
            return $this;
        }

        $fresh = $this->fresh();

        if ($fresh !== null) {
            $this->attributes = $fresh->attributes;
            $this->original = $fresh->original;
        }

        return $this;
    }

    /**
     * 渴望加载
     */
    public static function with(array|string $relations): QueryBuilder
    {
        return static::query()->with($relations);
    }

    /**
     * 创建原始 SQL 表达式
     */
    public static function raw(string $expression): Raw
    {
        return new Raw($expression);
    }
}
