<?php

declare(strict_types=1);

use Bin\Database\Migrations\Migration;
use Bin\Database\Schema\Schema;

return new class extends Migration
{
    /**
     * 创建用户表
     */
    public function up(): void
    {
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('phone', 20)->nullable();
            $table->text('bio')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * 回滚用户表
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
