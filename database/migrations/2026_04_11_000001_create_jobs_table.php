<?php

declare(strict_types=1);

use Bin\Database\Schema\Schema;

/**
 * 创建 jobs 表
 */
return new class {
    public function up(Schema $schema): void
    {
        $schema->create('jobs', function ($table) {
            $table->increments('id');
            $table->string('queue', 255);
            $table->text('payload');
            $table->integer('attempts')->default(0);
            $table->integer('reserved_at')->nullable();
            $table->integer('available_at');
            $table->integer('created_at');
            $table->index(['queue', 'reserved_at', 'available_at'], 'jobs_queue_reserved_available_index');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->dropIfExists('jobs');
    }
};
