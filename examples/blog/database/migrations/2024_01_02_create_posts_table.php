<?php

declare(strict_types=1);

use Bin\Database\Schema\Schema;
use Bin\Database\Migrations\Migration;

class CreatePostsTable extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function ($table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
}
