<?php

declare(strict_types=1);

namespace Database\Factories;

use Bin\Database\Factory;

/**
 * PostFactory 模型工厂
 */
class PostFactory
{
    /**
     * 注册工厂定义
     */
    public function definition(): void
    {
        Factory::define(Post::class, function () {
            return [
                // 'name' => fake()->name(),
                // 'email' => fake()->email(),
            ];
        });
    }
}
