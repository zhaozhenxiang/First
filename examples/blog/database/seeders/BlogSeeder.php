<?php

declare(strict_types=1);

namespace Database\Seeders;

use Bin\Database\Seeders\Seeder;
use App\Models\User;
use App\Models\Post;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        // 创建用户
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => password_hash('password', PASSWORD_BCRYPT),
        ]);

        // 创建文章
        Post::create([
            'title' => 'First Post',
            'content' => 'This is the first post in our blog.',
            'user_id' => $user->id,
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
        ]);

        Post::create([
            'title' => 'Second Post',
            'content' => 'This is the second post.',
            'user_id' => $user->id,
            'status' => 'draft',
        ]);
    }
}
