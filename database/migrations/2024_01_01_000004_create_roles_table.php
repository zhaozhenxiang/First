<?php

declare(strict_types=1);

use Bin\Database\Migrations\Migration;
use Bin\Database\Schema\Schema;

return new class extends Migration
{
    /**
     * 创建角色表
     */
    public function up(): void
    {
        Schema::create('roles', function ($table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug', 50)->unique();
            $table->text('description')->nullable();
            $table->json('permissions')->nullable();
            $table->timestamps();

            $table->index('slug');
        });
    }

    /**
     * 回滚角色表
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
