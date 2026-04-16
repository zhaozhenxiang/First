<?php

declare(strict_types=1);

namespace Bin\Route;

/**
 * 路由模型绑定解析器
 *
 * 支持显式绑定（Route::model/Route::bind）和隐式绑定（类型提示）。
 */
class RouteBinding
{
    /** @var array<string, callable> 自定义绑定解析器 */
    protected static array $binders = [];

    /** @var array<string, array{class: class-string, callback: ?callable}> 模型绑定 */
    protected static array $models = [];

    /**
     * 注册自定义绑定解析器
     *
     * @param string $key 路由参数名
     * @param callable(mixed): mixed $resolver 解析回调
     */
    public static function bind(string $key, callable $resolver): void
    {
        static::$binders[$key] = $resolver;
    }

    /**
     * 注册模型绑定
     *
     * @param string $key 路由参数名
     * @param class-string $class 模型类名
     * @param callable|null $callback 自定义查找回调（默认用 findOrFail）
     */
    public static function model(string $key, string $class, ?callable $callback = null): void
    {
        static::$models[$key] = [
            'class' => $class,
            'callback' => $callback,
        ];
    }

    /**
     * 解析路由参数值
     */
    public static function resolve(string $key, mixed $value): mixed
    {
        if (isset(static::$binders[$key])) {
            return (static::$binders[$key])($value);
        }

        if (isset(static::$models[$key])) {
            $model = static::$models[$key];

            if ($model['callback'] !== null) {
                return ($model['callback'])($value);
            }

            return static::resolveFromClass($model['class'], $value);
        }

        return $value;
    }

    /**
     * 通过类名隐式解析模型
     */
    public static function resolveForClass(string $class, mixed $value): mixed
    {
        return static::resolveFromClass($class, $value);
    }

    /**
     * 从类名解析模型实例（共享逻辑）
     */
    protected static function resolveFromClass(string $class, mixed $value): mixed
    {
        if (method_exists($class, 'findOrFail')) {
            $value = static::normalizeFinderValue($class, 'findOrFail', $value);

            try {
                return $class::findOrFail($value);
            } catch (\InvalidArgumentException $e) {
                if (str_starts_with($e->getMessage(), 'No query results for model [')) {
                    throw new \Bin\Exception\NotFoundHttpException($e->getMessage(), $e);
                }

                throw $e;
            }
        }

        if (method_exists($class, 'find')) {
            $value = static::normalizeFinderValue($class, 'find', $value);
            $result = $class::find($value);

            if ($result === null) {
                throw new \Bin\Exception\NotFoundHttpException("{$class} with ID {$value} not found");
            }

            return $result;
        }

        return new $class();
    }

    protected static function normalizeFinderValue(string $class, string $method, mixed $value): mixed
    {
        $reflection = new \ReflectionMethod($class, $method);
        $parameters = $reflection->getParameters();

        if ($parameters === []) {
            return $value;
        }

        $type = $parameters[0]->getType();

        if (!$type instanceof \ReflectionNamedType) {
            return $value;
        }

        if ($type->getName() === 'int' && is_string($value)) {
            $normalized = filter_var($value, FILTER_VALIDATE_INT);

            if ($normalized !== false) {
                return $normalized;
            }
        }

        return $value;
    }

    /**
     * 检查是否有绑定注册
     */
    public static function hasBinding(string $key): bool
    {
        return isset(static::$binders[$key]) || isset(static::$models[$key]);
    }

    /**
     * 清除所有绑定（用于测试）
     */
    public static function clear(): void
    {
        static::$binders = [];
        static::$models = [];
    }
}
