<?php

declare(strict_types=1);

use Bin\Database\Schema\Schema;

/**
 * Add status to posts
 */
return new class
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        Schema::table('posts', function ($table) {
            // $table->string('column')->nullable();
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::table('posts', function ($table) {
            // $table->dropColumn('column');
        });
    }
};
