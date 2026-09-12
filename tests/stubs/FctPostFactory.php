<?php

declare(strict_types=1);

namespace Database\Factories;

use Tests\FctPost;

/**
 * FctPost 约定工厂（阶段13 测试桩）
 */
class FctPostFactory
{
    public function definition(): array
    {
        return [
            'title' => 'untitled',
            'status' => 'draft',
        ];
    }

    /**
     * 命名状态表（PendingFactory::state('name') 解析用）
     */
    public function states(): array
    {
        return [
            'published' => fn (array $attributes) => ['status' => 'published'],
        ];
    }
}
