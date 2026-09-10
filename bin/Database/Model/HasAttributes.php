<?php

declare(strict_types=1);

namespace Bin\Database\Model;

use Bin\Database\Attribute;
use InvalidArgumentException;

trait HasAttributes
{
    /**
     * 是否临时禁止 mass assignment 保护
     */
    protected static bool $unguarded = false;

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
     * 追加到数组/JSON 的计算属性
     */
    protected array $appends = [];

    /**
     * 每页显示数量（分页默认值）
     */
    protected int $perPage = 15;

    /**
     * 默认 eager load 的关系
     */
    protected array $with = [];

    /**
     * 默认加载的关系计数
     */
    protected array $withCount = [];

    /**
     * 是否存在
     */
    public bool $exists = false;

    /**
     * 是否已被删除
     */
    public bool $wasRecentlyCreated = false;

    /**
     * 批量赋值
     */
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            } elseif ($this->isGuarded($key) && method_exists($this, 'handleDiscardedAttribute')) {
                $this->handleDiscardedAttribute($key);
            }
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
        // 1. 检查 Attribute 类风格的访问器（如 name() 方法返回 Attribute）
        $attribute = $this->getAttributeClassAccessor($key);
        if ($attribute !== null && $attribute->get !== null) {
            $value = $this->attributes[$key] ?? null;
            return ($attribute->get)($value, $this->attributes);
        }

        // 2. 检查命名约定风格的访问器（如 getNameAttribute, getAvatarUrlAttribute）
        $studlyKey = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $key)));
        $accessor = 'get' . $studlyKey . 'Attribute';
        if (method_exists($this, $accessor)) {
            return $this->$accessor($this->attributes[$key] ?? null);
        }

        if (!$this->hasAttribute($key)) {
            // 检查关系方法 — 动态加载关系
            if (method_exists($this, 'getRelationValue') && method_exists($this, $key)) {
                return $this->getRelationValue($key);
            }

            // 如果关系已加载但方法不存在（通过 setRelation 手动设置）
            if (method_exists($this, 'relationLoaded') && $this->relationLoaded($key)) {
                return $this->getRelation($key);
            }

            // 严格模式下访问不存在的属性时报告
            if (method_exists($this, 'handleMissingAttributeViolation')) {
                $this->handleMissingAttributeViolation($key);
            }

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
     * Attribute 类访问器反射结果缓存
     *
     * @var array<string, array<string, bool>> class => method => 是否返回 Attribute
     */
    protected static array $attributeAccessorCache = [];

    /**
     * 获取 Attribute 类风格的访问器
     */
    protected function getAttributeClassAccessor(string $key): ?Attribute
    {
        // snake_case 转为 camelCase：full_name → fullName
        $method = lcfirst(str_replace('_', '', ucwords($key, '_')));

        $class = static::class;

        // 反射结果按类缓存：属性访问是热路径，不能每次都做反射
        if (!isset(static::$attributeAccessorCache[$class][$method])) {
            if (!method_exists($this, $method)) {
                static::$attributeAccessorCache[$class][$method] = false;

                return null;
            }

            try {
                // 使用反射检查返回类型
                $reflection = new \ReflectionMethod($this, $method);

                $returnType = $reflection->getReturnType();
                static::$attributeAccessorCache[$class][$method] =
                    $returnType !== null && $returnType->getName() === Attribute::class;
            } catch (\ReflectionException) {
                static::$attributeAccessorCache[$class][$method] = false;
            }
        }

        if (static::$attributeAccessorCache[$class][$method] !== true) {
            return null;
        }

        return $this->$method();
    }

    /**
     * 设置属性
     */
    public function setAttribute(string $key, mixed $value): self
    {
        // 1. 检查 Attribute 类风格的修改器
        $attribute = $this->getAttributeClassAccessor($key);
        if ($attribute !== null && $attribute->set !== null) {
            $result = ($attribute->set)($value, $this->attributes);
            // 修改器可以返回数组 [key => value] 来设置属性
            if (is_array($result)) {
                foreach ($result as $k => $v) {
                    $this->attributes[$k] = $v;
                }
            } else {
                $this->attributes[$key] = $result;
            }
            return $this;
        }

        // 2. 检查命名约定风格的修改器（如 setNameAttribute）
        $mutator = 'set' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $key))) . 'Attribute';
        if (method_exists($this, $mutator)) {
            $this->$mutator($value);
            return $this;
        }

        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * 强制批量赋值（绕过 fillable/guarded 检查）
     */
    public function forceFill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * 检查属性是否可批量赋值
     */
    public function isFillable(string $key): bool
    {
        // 如果在 unguard 状态，所有属性都可赋值
        if (static::$unguarded) {
            return true;
        }

        // 如果属性在 fillable 中
        if (in_array($key, $this->fillable, true)) {
            return true;
        }

        // 如果 fillable 非空但属性不在其中，不可赋值
        if (!empty($this->fillable)) {
            return false;
        }

        // fillable 为空时，检查是否在 guarded 中
        return !$this->isGuarded($key);
    }

    /**
     * 检查属性是否被保护
     */
    public function isGuarded(string $key): bool
    {
        // guarded 包含 '*' 时，所有属性被保护
        if (in_array('*', $this->guarded, true)) {
            return true;
        }

        return in_array($key, $this->guarded, true);
    }

    /**
     * 检查模型是否完全被保护（fillable 为空且 guarded 包含 '*'）
     */
    public function totallyGuarded(): bool
    {
        return empty($this->fillable) && in_array('*', $this->guarded, true);
    }

    /**
     * 临时禁止 mass assignment 保护
     */
    public static function unguard(bool $state = true): void
    {
        static::$unguarded = $state;
    }

    /**
     * 重新启用 mass assignment 保护
     */
    public static function reguard(): void
    {
        static::$unguarded = false;
    }

    /**
     * 在回调中临时禁止 mass assignment 保护
     */
    public static function unguarded(callable $callback): mixed
    {
        $previousState = static::$unguarded;

        static::$unguarded = true;

        try {
            return $callback();
        } finally {
            static::$unguarded = $previousState;
        }
    }

    /**
     * 检查属性是否存在
     */
    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
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
        // 已加载的关系也算存在（不触发懒加载），与 Eloquent 一致
        return $this->hasAttribute($key)
            || (method_exists($this, 'relationLoaded') && $this->relationLoaded($key));
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
     * 获取每页数量
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * 设置每页数量
     */
    public function setPerPage(int $perPage): self
    {
        $this->perPage = $perPage;
        return $this;
    }

    /**
     * 获取默认 eager load 关系
     */
    public function getWith(): array
    {
        return $this->with;
    }

    /**
     * 设置默认 eager load 关系
     */
    public function setWith(array $with): self
    {
        $this->with = $with;
        return $this;
    }

    /**
     * 获取默认关系计数
     */
    public function getWithCount(): array
    {
        return $this->withCount;
    }

    /**
     * 获取主键类型
     */
    public function getKeyType(): string
    {
        return $this->keyType;
    }

    /**
     * 获取自增设置
     */
    public function getIncrementing(): bool
    {
        return $this->incrementing;
    }
}
