<?php

declare(strict_types=1);

namespace Bin\Database\Seeders;

use Bin\Database\Model;

/**
 * Seeder 基类
 *
 * 用于填充数据库表数据
 */
abstract class Seeder
{
    /**
     * 执行填充
     */
    abstract public function run(): void;

    /**
     * 调用其他 Seeder
     */
    protected function call(string|array $classes): void
    {
        $classes = (array) $classes;

        foreach ($classes as $class) {
            if (!class_exists($class)) {
                // 尝试自动加载
                $seederPath = basePath('database/seeders/' . basename(str_replace('\\', '/', $class)) . '.php');

                if (file_exists($seederPath)) {
                    require_once $seederPath;
                }
            }

            if (class_exists($class)) {
                $seeder = new $class();
                $seeder->run();
            }
        }
    }

    /**
     * 创建指定模型的实例
     */
    protected function create(string $model, array $attributes = []): mixed
    {
        if (!class_exists($model)) {
            throw new \RuntimeException("Model class not found: {$model}");
        }

        return $model::create($attributes);
    }

    /**
     * 批量创建模型实例
     */
    protected function createMany(string $model, int $count, callable|array $callback = []): array
    {
        $models = [];
        $isCallable = is_callable($callback);

        for ($i = 0; $i < $count; $i++) {
            if ($isCallable) {
                $attributes = $callback($i + 1);
            } else {
                $attributes = $callback;
            }

            $models[] = $this->create($model, $attributes);
        }

        return $models;
    }

    /**
     * 工厂方法 - 使用预定义的数据模式创建模型
     */
    protected function factory(string $model): SeederFactory
    {
        return new SeederFactory($model);
    }
}
