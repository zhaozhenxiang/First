<?php

declare(strict_types=1);

use Bin\Database\Migrations\Migration;
use Bin\Database\Schema\Schema;

return new class extends Migration
{
    /**
     * 创建文章表
     */
    public function up(): void
    {
        Schema::create('posts', function ($table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('作者ID');
            $table->string('title');
            $table->string('slug', 191)->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('featured_image')->nullable();
            $table->boolean('published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->integer('view_count')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // 索引
            $table->index(['user_id', 'published']);
            $table->index('published_at');
            $table->fullText(['title', 'content']);
        });
    }

    /**
     * 回滚文章表
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
