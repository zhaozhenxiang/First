<?php

declare(strict_types=1);

use Bin\Database\Migrations\Migration;
use Bin\Database\Schema\Schema;

return new class extends Migration
{
    /**
     * 创建评论表
     */
    public function up(): void
    {
        Schema::create('comments', function ($table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('post_id')
                ->constrained('posts')
                ->cascadeOnDelete();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('comments')
                ->cascadeOnDelete();
            $table->text('content');
            $table->integer('like_count')->default(0);
            $table->timestamps();

            $table->index(['post_id', 'parent_id']);
        });
    }

    /**
     * 回滚评论表
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
