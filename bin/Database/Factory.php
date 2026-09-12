<?php

declare(strict_types=1);

namespace Bin\Database;

/**
 * 模型工厂 - 用于测试数据生成
 */
class Factory
{
    /**
     * 已注册的工厂定义
     * @var array<string, \Closure>
     */
    protected static array $definitions = [];

    /**
     * 已注册的状态
     * @var array<string, array<string, \Closure>>
     */
    protected static array $states = [];

    /**
     * 注册工厂定义
     */
    public static function define(string $model, \Closure $callback): void
    {
        static::$definitions[$model] = $callback;
    }

    /**
     * 注册状态变体
     */
    public static function state(string $model, string $name, \Closure $callback): void
    {
        if (!isset(static::$states[$model])) {
            static::$states[$model] = [];
        }

        static::$states[$model][$name] = $callback;
    }

    /**
     * 创建模型并保存到数据库
     */
    public static function create(string $model, array $attributes = [], array $states = []): Model
    {
        $instance = static::make($model, $attributes, $states);
        $instance->save();
        return $instance;
    }

    /**
     * 创建模型但不保存
     */
    public static function make(string $model, array $attributes = [], array $states = []): Model
    {
        if (!isset(static::$definitions[$model])) {
            throw new \InvalidArgumentException("No factory defined for [{$model}]");
        }

        $definition = (static::$definitions[$model])();

        if (!is_array($definition)) {
            throw new \InvalidArgumentException("Factory definition for [{$model}] must return an array");
        }

        // Apply states
        foreach ($states as $state) {
            if (isset(static::$states[$model][$state])) {
                $stateAttributes = (static::$states[$model][$state])();
                if (is_array($stateAttributes)) {
                    $definition = array_merge($definition, $stateAttributes);
                }
            }
        }

        // Override with provided attributes
        $finalAttributes = array_merge($definition, $attributes);

        return new $model($finalAttributes);
    }

    /**
     * 批量创建
     */
    public static function times(int $count, string $model, array $attributes = [], array $states = []): Collection
    {
        $results = [];

        for ($i = 0; $i < $count; $i++) {
            $results[] = static::create($model, $attributes, $states);
        }

        return new Collection($results);
    }

    /**
     * 批量创建但不保存
     */
    public static function makeTimes(int $count, string $model, array $attributes = [], array $states = []): Collection
    {
        $results = [];

        for ($i = 0; $i < $count; $i++) {
            $results[] = static::make($model, $attributes, $states);
        }

        return new Collection($results);
    }

    /**
     * 重置所有工厂定义
     */
    public static function flush(): void
    {
        static::$definitions = [];
        static::$states = [];
    }

    /**
     * 解析模型定义：define() 闭包优先，缺省回退 Database\Factories\{类名}Factory::definition()
     *
     * @return array<string, mixed>
     */
    public static function resolveDefinition(string $model): array
    {
        if (isset(static::$definitions[$model])) {
            $definition = (static::$definitions[$model])();

            if (!is_array($definition)) {
                throw new \InvalidArgumentException("Factory definition for [{$model}] must return an array");
            }

            return $definition;
        }

        $short = ($pos = strrpos($model, '\\')) === false ? $model : substr($model, $pos + 1);
        $factoryClass = 'Database\\Factories\\' . $short . 'Factory';

        if (class_exists($factoryClass)) {
            $factory = new $factoryClass();

            if (method_exists($factory, 'definition')) {
                $definition = $factory->definition();

                if (!is_array($definition)) {
                    throw new \InvalidArgumentException("Factory definition for [{$model}] must return an array");
                }

                return $definition;
            }
        }

        throw new \InvalidArgumentException(
            "No factory defined for [{$model}]. Register one via Factory::define() or create {$factoryClass}."
        );
    }

    /**
     * 解析命名状态：define 注册表 > 工厂类 states() 方法
     *
     * @return array<string, mixed>|null
     */
    public static function resolveNamedState(string $model, string $state, array $definition): ?array
    {
        if (isset(static::$states[$model][$state])) {
            $attributes = (static::$states[$model][$state])($definition);

            return is_array($attributes) ? $attributes : null;
        }

        $short = ($pos = strrpos($model, '\\')) === false ? $model : substr($model, $pos + 1);
        $factoryClass = 'Database\\Factories\\' . $short . 'Factory';

        if (class_exists($factoryClass) && method_exists($factoryClass, 'states')) {
            $states = (new $factoryClass())->states();

            if (isset($states[$state]) && $states[$state] instanceof \Closure) {
                $attributes = $states[$state]($definition);

                return is_array($attributes) ? $attributes : null;
            }
        }

        return null;
    }

    /**
     * 获取所有定义
     */
    public static function getDefinitions(): array
    {
        return static::$definitions;
    }

    /**
     * 获取所有状态
     */
    public static function getStates(): array
    {
        return static::$states;
    }
}
