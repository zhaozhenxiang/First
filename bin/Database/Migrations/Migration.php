<?php

declare(strict_types=1);

namespace Bin\Database\Migrations;

use Bin\Database\Schema\Schema;

/**
 * 迁移基类
 */
abstract class Migration
{
    /**
     * 迁移名称
     */
    protected string $name = '';

    /**
     * 获取迁移名称
     */
    public function getName(): string
    {
        return $this->name ?: static::class;
    }

    /**
     * 执行迁移
     */
    abstract public function up(): void;

    /**
     * 回滚迁移
     */
    abstract public function down(): void;
}
