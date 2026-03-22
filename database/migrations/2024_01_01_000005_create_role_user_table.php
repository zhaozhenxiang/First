<?php

declare(strict_types=1);

use Bin\Database\Migrations\Migration;
use Bin\Database\Schema\Schema;

return new class extends Migration
{
    /**
     * 创建角色用户中间表
     */
    public function up(): void
    {
        Schema::create('role_user', function ($table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('role_id')
                ->constrained('roles')
                ->cascadeOnDelete();
            $table->timestamps();

            // 确保同一用户不会有重复的角色
            $table->unique(['user_id', 'role_id']);
        });
    }

    /**
     * 回滚角色用户中间表
     */
    public function down(): void
    {
        Schema::dropIfExists('role_user');
    }
};
