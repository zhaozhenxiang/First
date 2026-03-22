<?php

declare(strict_types=1);

namespace Bin\Database;

use Bin\Model\Model as BaseModel;
use PDO;
use JsonSerializable;
use ArrayAccess;
use InvalidArgumentException;

/**
 * Eloquent 风格的 ORM 模型基类
 */
abstract class Model extends BaseModel implements ArrayAccess, JsonSerializable
{
    /**
     * 数据库连接
     */
    protected static ?PDO $connection = null;

    /**
     * 表名
     */
    protected string $table;

    /**
     * 主键
     */
    protected string $primaryKey = 'id';

    /**
     * 主键类型
     */
    protected string $keyType = 'int';

    /**
     * 是否自增
     */
    protected bool $incrementing = true;

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
     * 转换为日期的属性
     */
    protected array $dates = [];

    /**
     * 属性类型转换
     */
    protected array $casts = [];

    /**
     * 可批量赋值的属性
     */
    protected array $fillable = [];

    /**
     * 不可批量赋值的属性（黑名单）
     */
    protected array $guarded = ['*'];

    /**
     * 隐藏属性
     */
    protected array $hidden = [];

    /**
     * 可见属性
     */
    protected array $visible = [];

    /**
     * 模型属性
     */
    protected array $attributes = [];

    /**
     * 原始属性
     */
    protected array $original = [];

    /**
     * 变更的属性
     */
    protected array $changes = [];

    /**
     * 是否存在
     */
    public bool $exists = false;

    /**
     * 是否已被删除
     */
    public bool $wasRecentlyCreated = false;

    /**
     * 查询构建器
     */
    protected static ?QueryBuilder $queryBuilder = null;

    /**
     * 全局作用域
     */
    protected static array $globalScopes = [];

    /**
     * 是否已引导（每个模型类独立）
     */
    protected static array $bootedModels = [];

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
            self::$connection = parent::getConnection();
        }

        return self::$connection;
    }

    /**
     * 设置数据库连接（用于测试）
     */
    public static function setConnection(?PDO $connection): void
    {
        self::$connection = $connection;
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

        // 应用全局作用域
        foreach (static::$globalScopes as $identifier => $callback) {
            $query->withGlobalScope($identifier, $callback);
        }

        return $query;
    }

    /**
     * 添加全局作用域
     */
    public static function addGlobalScope(string $identifier, \Closure $callback): void
    {
        static::$globalScopes[$identifier] = $callback;
    }

    /**
     * 移除全局作用域
     */
    public static function forgetGlobalScope(string $identifier): void
    {
        unset(static::$globalScopes[$identifier]);
    }

    /**
     * 获取所有全局作用域
     */
    public static function getGlobalScopes(): array
    {
        return static::$globalScopes;
    }

    /**
     * 清除所有全局作用域
     */
    public static function clearGlobalScopes(): void
    {
        static::$globalScopes = [];
    }

    /**
     * 重置引导状态（用于测试）
     */
    public static function resetBooted(): void
    {
        $class = static::class;
        unset(static::$bootedModels[$class]);
        static::$globalScopes = [];
    }

    /**
     * 静态调用转发到查询构建器
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return static::query()->$method(...$parameters);
    }

    /**
     * 动态调用转发到查询构建器
     */
    public function __call(string $method, array $parameters): mixed
    {
        return static::query()->$method(...$parameters);
    }

    /**
     * 根据 ID 查找
     */
    public static function find(int $id): ?self
    {
        return static::query()->find($id);
    }

    /**
     * 根据 ID 数组查找
     */
    public static function findMany(array $ids): array
    {
        return static::query()->findMany($ids);
    }

    /**
     * 查找或失败
     */
    public static function findOrFail(int $id): self
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
    public static function findOrNew(int $id): self
    {
        return static::find($id) ?? new static();
    }

    /**
     * 获取所有记录
     */
    public static function all(): array
    {
        return static::query()->get();
    }

    /**
     * 分页查询
     *
     * @param int $perPage 每页数量
     * @param array $columns 查询列
     * @param string $pageName 页码参数名
     * @param int|null $page 当前页码
     * @return LengthAwarePaginator
     */
    public static function paginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null): LengthAwarePaginator
    {
        return static::query()->paginate($perPage, $columns, $pageName, $page);
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
    public static function simplePaginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null): Paginator
    {
        return static::query()->simplePaginate($perPage, $columns, $pageName, $page);
    }

    /**
     * 游标分页
     *
     * @param int $perPage 每页数量
     * @param array $columns 查询列
     * @param string $cursorName 游标参数名
     * @param string|null $cursor 游标值
     * @return CursorPaginator
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
        $this->fill($attributes);
    }

    /**
     * 批量赋值
     */
    public function fill(array $attributes): self
    {
        foreach ($this->fillableFromArray($attributes) as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * 获取可填充的属性
     */
    protected function fillableFromArray(array $attributes): array
    {
        // 如果定义了 fillable，优先使用 fillable（白名单）
        if (count($this->fillable) > 0) {
            return array_intersect_key($attributes, array_flip($this->fillable));
        }

        // 如果 guarded 是 ['*']，表示禁止所有属性
        if ($this->guarded === ['*']) {
            return [];
        }

        // 排除 guarded 中的属性（黑名单）
        return array_diff_key($attributes, array_flip($this->guarded));
    }

    /**
     * 设置原始属性
     */
    public function setRawAttributes(array $attributes): self
    {
        $this->attributes = $attributes;
        $this->original = $attributes;

        return $this;
    }

    /**
     * 获取属性
     */
    public function getAttribute(string $key): mixed
    {
        if (!$this->hasAttribute($key)) {
            return null;
        }

        $value = $this->attributes[$key];

        // 类型转换
        if ($this->hasCast($key)) {
            return $this->castAttribute($key, $value);
        }

        return $value;
    }

    /**
     * 设置属性
     */
    public function setAttribute(string $key, mixed $value): self
    {
        // 检查是否可赋值
        if ($this->isGuarded($key)) {
            throw new InvalidArgumentException("Property [{$key}] is guarded");
        }

        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * 检查属性是否存在
     */
    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * 检查属性是否被保护
     */
    protected function isGuarded(string $key): bool
    {
        return in_array($key, $this->guarded, true);
    }

    /**
     * 检查是否有类型转换
     */
    protected function hasCast(string $key): bool
    {
        return isset($this->casts[$key]);
    }

    /**
     * 类型转换
     */
    protected function castAttribute(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return $value;
        }

        $castType = $this->casts[$key];

        return match ($castType) {
            'int', 'integer' => (int) $value,
            'real', 'float', 'double' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'array', 'json' => json_decode($value, true),
            'object' => json_decode($value),
            'date', 'datetime' => $value instanceof \DateTime ? $value : new \DateTime($value),
            'timestamp' => (int) $value,
            default => $value,
        };
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

        $id = $query->insertGetId($attributes);

        $this->setAttribute($this->getKeyName(), $id);

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
     * 触发模型事件
     */
    protected function fireModelEvent(string $event): mixed
    {
        return ModelEventDispatcher::dispatchForModel(static::class, $event, $this);
    }

    /**
     * 注册创建事件监听器
     */
    public static function creating(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class . '@creating', $callback);
    }

    /**
     * 注册创建后事件监听器
     */
    public static function created(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class . '@created', $callback);
    }

    /**
     * 注册更新事件监听器
     */
    public static function updating(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class . '@updating', $callback);
    }

    /**
     * 注册更新后事件监听器
     */
    public static function updated(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class . '@updated', $callback);
    }

    /**
     * 注册保存事件监听器
     */
    public static function saving(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class . '@saving', $callback);
    }

    /**
     * 注册保存后事件监听器
     */
    public static function saved(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class . '@saved', $callback);
    }

    /**
     * 注册删除事件监听器
     */
    public static function deleting(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class . '@deleting', $callback);
    }

    /**
     * 注册删除后事件监听器
     */
    public static function deleted(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class . '@deleted', $callback);
    }

    /**
     * 注册恢复事件监听器
     */
    public static function restoring(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class . '@restoring', $callback);
    }

    /**
     * 注册恢复后事件监听器
     */
    public static function restored(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class . '@restored', $callback);
    }

    /**
     * 注册获取事件监听器
     */
    public static function retrieved(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class . '@retrieved', $callback);
    }

    /**
     * 清除模型的所有事件监听器
     */
    public static function flushEventListeners(): void
    {
        $events = ['creating', 'created', 'updating', 'updated', 'saving', 'saved', 'deleting', 'deleted', 'restoring', 'restored', 'retrieved'];

        foreach ($events as $event) {
            ModelEventDispatcher::forget(static::class . '@' . $event);
        }
    }

    /**
     * 获取所有属性
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * 获取原始属性
     */
    public function getOriginal(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->original;
        }

        return $this->original[$key] ?? null;
    }

    /**
     * 获取变更的属性
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $value !== $this->original[$key]) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * 获取干净的属性
     */
    public function getClean(): array
    {
        $clean = [];

        foreach ($this->attributes as $key => $value) {
            if (array_key_exists($key, $this->original) && $value === $this->original[$key]) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    /**
     * 设置创建时间
     */
    protected function setCreatedAt(): void
    {
        $this->setAttribute(self::CREATED_AT, date('Y-m-d H:i:s'));
    }

    /**
     * 设置更新时间
     */
    protected function setUpdatedAt(): void
    {
        $this->setAttribute(self::UPDATED_AT, date('Y-m-d H:i:s'));
    }

    /**
     * 获取创建时间
     */
    public function getCreatedAt(): ?string
    {
        return $this->getAttribute(self::CREATED_AT);
    }

    /**
     * 获取更新时间
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getAttribute(self::UPDATED_AT);
    }

    /**
     * 转为数组
     */
    public function toArray(): array
    {
        $array = $this->attributes;

        // 隐藏属性
        if (!empty($this->hidden)) {
            $array = array_diff_key($array, array_flip($this->hidden));
        }

        // 只显示指定属性
        if (!empty($this->visible)) {
            $array = array_intersect_key($array, array_flip($this->visible));
        }

        return $array;
    }

    /**
     * 转为 JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * JsonSerializable
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * __get
     */
    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    /**
     * __set
     */
    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * __isset
     */
    public function __isset(string $key): bool
    {
        return $this->hasAttribute($key);
    }

    /**
     * __unset
     */
    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }

    /**
     * offsetExists
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->hasAttribute($offset);
    }

    /**
     * offsetGet
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->getAttribute($offset);
    }

    /**
     * offsetSet
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->setAttribute($offset, $value);
    }

    /**
     * offsetUnset
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }

    /**
     * __toString
     */
    public function __toString(): string
    {
        return $this->toJson();
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
     * 检查模型是否被修改
     */
    public function isDirty(?string $attribute = null): bool
    {
        if ($attribute === null) {
            return !empty($this->getDirty());
        }

        return array_key_exists($attribute, $this->getDirty());
    }

    /**
     * 检查模型是否干净
     */
    public function isClean(?string $attribute = null): bool
    {
        if ($attribute === null) {
            return empty($this->getDirty());
        }

        return !array_key_exists($attribute, $this->getDirty());
    }

    /**
     * 检查是否为新创建的
     */
    public function wasRecentlyCreated(): bool
    {
        return $this->wasRecentlyCreated;
    }

    /**
     * 更新时间戳
     */
    public function touch(): bool
    {
        if (!$this->timestamps) {
            return false;
        }

        $this->setUpdatedAt();

        return $this->save();
    }

    /**
     * 软删除 - 如果模型支持的话
     */
    public function trashed(): bool
    {
        return false;
    }

    /**
     * 已加载的关系
     */
    protected array $relations = [];

    /**
     * 设置关系
     */
    public function setRelation(string $relation, mixed $value): self
    {
        $this->relations[$relation] = $value;

        return $this;
    }

    /**
     * 获取关系
     */
    public function getRelation(string $relation): mixed
    {
        return $this->relations[$relation] ?? null;
    }

    /**
     * 获取所有关系
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * 设置多个关系
     */
    public function setRelations(array $relations): self
    {
        $this->relations = $relations;

        return $this;
    }

    /**
     * 检查关系是否已加载
     */
    public function relationLoaded(string $key): bool
    {
        return isset($this->relations[$key]);
    }

    /**
     * 转为数组（包含关系）
     */
    public function toArrayWithRelations(): array
    {
        $array = $this->toArray();

        foreach ($this->relations as $key => $value) {
            if ($value instanceof Model) {
                $array[$key] = $value->toArray();
            } elseif (is_array($value)) {
                $array[$key] = array_map(fn($item) => $item instanceof Model ? $item->toArray() : $item, $value);
            }
        }

        return $array;
    }

    /**
     * 定义 Has One 关系
     */
    protected function hasOne(string $related, string $foreignKey = null, string $localKey = null): \Bin\Database\Relations\HasOne
    {
        $instance = new $related();

        $foreignKey = $foreignKey ?? $this->getForeignKey();

        $localKey = $localKey ?? $this->getKeyName();

        $query = $instance->newQuery();

        return new \Bin\Database\Relations\HasOne($query, $this, $foreignKey, $localKey);
    }

    /**
     * 定义 Has Many 关系
     */
    protected function hasMany(string $related, string $foreignKey = null, string $localKey = null): \Bin\Database\Relations\HasMany
    {
        $instance = new $related();

        $foreignKey = $foreignKey ?? $this->getForeignKey();

        $localKey = $localKey ?? $this->getKeyName();

        $query = $instance->newQuery();

        return new \Bin\Database\Relations\HasMany($query, $this, $foreignKey, $localKey);
    }

    /**
     * 定义 Belongs To 关系
     */
    protected function belongsTo(string $related, string $foreignKey = null, string $ownerKey = null, string $relation = null): \Bin\Database\Relations\BelongsTo
    {
        $relation = $relation ?? $this->guessBelongsToRelation();

        $instance = new $related();

        $foreignKey = $foreignKey ?? $this->getForeignKey();

        $ownerKey = $ownerKey ?? $instance->getKeyName();

        $query = $instance->newQuery();

        return new \Bin\Database\Relations\BelongsTo($query, $this, $foreignKey, $ownerKey, $related);
    }

    /**
     * 定义 Belongs To Many 关系
     */
    protected function belongsToMany(
        string $related,
        string $table = null,
        string $foreignPivotKey = null,
        string $relatedPivotKey = null,
        string $parentKey = null,
        string $relatedKey = null
    ): \Bin\Database\Relations\BelongsToMany {
        $instance = new $related();

        $table = $table ?? $this->joiningTable($related);

        $foreignPivotKey = $foreignPivotKey ?? $this->getForeignKey();

        $relatedPivotKey = $relatedPivotKey ?? $instance->getForeignKey();

        $parentKey = $parentKey ?? $this->getKeyName();

        $relatedKey = $relatedKey ?? $instance->getKeyName();

        $query = $instance->newQuery();

        return new \Bin\Database\Relations\BelongsToMany(
            $query,
            $this,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey
        );
    }

    /**
     * 获取外键名
     */
    protected function getForeignKey(): string
    {
        return strtolower(substr(strrchr(get_class($this), '\\'), 1)) . '_id';
    }

    /**
     * 猜测 Belongs To 关系名
     */
    protected function guessBelongsToRelation(): string
    {
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2];

        return $caller['function'];
    }

    /**
     * 获取中间表名
     */
    protected function joiningTable(string $related): string
    {
        $segments = [
            strtolower(substr(strrchr(get_class($this), '\\'), 1)),
            strtolower(substr(strrchr($related, '\\'), 1)),
        ];

        sort($segments);

        return strtolower(implode('_', $segments));
    }

    /**
     * 创建新的查询构建器
     */
    protected function newQuery(): QueryBuilder
    {
        return static::query();
    }

    /**
     * 动态加载关系
     */
    public function load(string $relation): self
    {
        $this->relations[$relation] = $this->$relation();

        return $this;
    }

    /**
     * 加载多个关系
     */
    public function loadMultiple(array $relations): self
    {
        foreach ($relations as $relation) {
            $this->load($relation);
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
