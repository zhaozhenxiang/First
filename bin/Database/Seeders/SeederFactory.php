<?php

declare(strict_types=1);

namespace Bin\Database\Seeders;

/**
 * Seeder 工厂类
 *
 * 用于定义数据模式和批量创建
 */
class SeederFactory
{
    private string $model;
    private array $states = [];
    private array $afterMaking = [];
    private array $afterCreating = [];

    public function __construct(string $model)
    {
        $this->model = $model;
    }

    /**
     * 定义状态
     */
    public function state(string $name, callable $callback): self
    {
        $this->states[$name] = $callback;
        return $this;
    }

    /**
     * 创建后回调
     */
    public function afterMaking(callable $callback): self
    {
        $this->afterMaking[] = $callback;
        return $this;
    }

    /**
     * 创建后回调（已保存）
     */
    public function afterCreating(callable $callback): self
    {
        $this->afterCreating[] = $callback;
        return $this;
    }

    /**
     * 创建单个实例
     */
    public function make(array $attributes = []): mixed
    {
        $instance = new $this->model();
        $rawAttributes = $this->getRawAttributes($attributes);

        // 如果模型有 setRawAttributes 方法，使用它
        if (method_exists($instance, 'setRawAttributes')) {
            $instance->setRawAttributes($rawAttributes);
        } else {
            // 否则直接设置属性
            foreach ($rawAttributes as $key => $value) {
                $instance->$key = $value;
            }
        }

        // 执行 afterMaking 回调
        foreach ($this->afterMaking as $callback) {
            $callback($instance);
        }

        return $instance;
    }

    /**
     * 创建并保存单个实例
     */
    public function create(array $attributes = []): mixed
    {
        $instance = $this->make($attributes);

        // 如果模型有 save 方法，调用它
        if (method_exists($instance, 'save')) {
            $instance->save();
        }

        // 执行 afterCreating 回调
        foreach ($this->afterCreating as $callback) {
            $callback($instance);
        }

        return $instance;
    }

    /**
     * 批量创建实例
     */
    public function createMany(int $count, array $attributes = []): array
    {
        $instances = [];

        for ($i = 0; $i < $count; $i++) {
            $instances[] = $this->create($attributes);
        }

        return $instances;
    }

    /**
     * 应用状态
     */
    public function withStates(string|array $states): self
    {
        $factory = clone $this;
        $factory->states = [];

        foreach ((array) $states as $state) {
            if (isset($this->states[$state])) {
                $factory->states[$state] = $this->states[$state];
            }
        }

        return $factory;
    }

    /**
     * 获取原始属性
     */
    protected function getRawAttributes(array $attributes): array
    {
        // 应用所有状态
        $rawAttributes = [];

        foreach ($this->states as $state) {
            $stateAttributes = $state();
            $rawAttributes = array_merge($rawAttributes, $stateAttributes);
        }

        return array_merge($rawAttributes, $attributes);
    }

    /**
     * 获取模型类名
     */
    public function getModel(): string
    {
        return $this->model;
    }
}
