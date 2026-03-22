<?php

declare(strict_types=1);

namespace Database\Seeders;

use Bin\Database\Seeders\Seeder;
use App\Model\User;

/**
 * User Seeder
 *
 * 填充用户表数据
 */
class UserSeeder extends Seeder
{
    /**
     * 执行填充
     */
    public function run(): void
    {
        // 创建管理员用户
        $this->create(User::class, [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'role' => 'admin',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // 创建测试用户（使用工厂模式）
        $this->factory(User::class)
            ->state('user', fn() => [
                'role' => 'user',
                'password' => password_hash('password', PASSWORD_DEFAULT),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ])
            ->createMany(10);

        // 创建编辑用户
        $this->factory(User::class)
            ->state('editor', fn() => [
                'role' => 'editor',
                'password' => password_hash('password', PASSWORD_DEFAULT),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ])
            ->createMany(5);
    }
}
