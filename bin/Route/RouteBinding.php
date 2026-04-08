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
     *
     * @param string $key 参数名
     * @param mixed $value 参数值（通常是从 URL 提取的 ID）
     * @return mixed 解析后的模型实例或原始值
     */
    public static function resolve(string $key, mixed $value): mixed
    {
        // 优先自定义绑定
        if (isset(static::$binders[$key])) {
            return (static::$binders[$key])($value);
        }

        // 模型绑定
        if (isset(static::$models[$key])) {
            $model = static::$models[$key];

            if ($model['callback'] !== null) {
                return ($model['callback'])($value);
            }

            // 默认用 findOrFail
            $class = $model['class'];
            if (method_exists($class, 'findOrFail')) {
                return $class::findOrFail($value);
            }

            if (method_exists($class, 'find')) {
                $result = $class::find($value);
                if ($result === null) {
                    throw new \RuntimeException("{$class} with ID {$value} not found", 404);
                }
                return $result;
            }

            return new $class();
        }

        return $value;
    }

    /**
     * 通过类名隐式解析模型
     *
     * @param class-string $class 模型类名
     * @param mixed $value 参数值
     * @return mixed 解析后的模型实例
     */
    public static function resolveForClass(string $class, mixed $value): mixed
    {
        if (method_exists($class, 'findOrFail')) {
            return $class::findOrFail($value);
        }

        if (method_exists($class, 'find')) {
            $result = $class::find($value);
            if ($result === null) {
                throw new \RuntimeException("{$class} with ID {$value} not found", 404);
            }
            return $result;
        }

        return new $class();
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
