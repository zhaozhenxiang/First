<?php

declare(strict_types=1);

use Bin\Database\Schema\Schema;

/**
 * Create orders table
 */
return new class
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        Schema::create('orders', function ($table) {
            $table->increments('id');
            // $table->string('name');
            $table->timestamps();
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
