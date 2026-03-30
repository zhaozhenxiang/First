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
