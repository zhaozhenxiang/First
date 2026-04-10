<?php

declare(strict_types=1);

use Bin\Database\Schema\Schema;

/**
 * 创建 failed_jobs 表
 */
return new class {
    public function up(Schema $schema): void
    {
        $schema->create('failed_jobs', function ($table) {
            $table->increments('id');
            $table->string('connection', 255);
            $table->string('queue', 255);
            $table->text('payload');
            $table->text('exception');
            $table->integer('failed_at');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->dropIfExists('failed_jobs');
    }
};
