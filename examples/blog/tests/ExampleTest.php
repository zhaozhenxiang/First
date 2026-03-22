<?php

declare(strict_types=1);

namespace Tests;

/**
 * 示例应用结构测试
 */
class ExampleAppTest extends TestCase
{
    public function testExampleAppStructure(): void
    {
        // 检查目录是否存在
        $this->assertDirectoryExists(basePath('examples/blog'));
        $this->assertDirectoryExists(basePath('examples/blog/app'));
        $this->assertDirectoryExists(basePath('examples/blog/views'));
        $this->assertDirectoryExists(basePath('examples/blog/public'));
        $this->assertDirectoryExists(basePath('examples/blog/database'));
    }

    public function testConfigFileExists(): void
    {
        $this->assertFileExists(basePath('examples/blog/config/app.php'));
    }

    public function modelClassesExist(): void
    {
        $this->assertFileExists(basePath('examples/blog/app/Models/User.php'));
        $this->assertFileExists(basePath('examples/blog/app/Models/Post.php'));
    }

    public function controllerClassesExist(): void
    {
        $this->assertFileExists(basePath('examples/blog/app/Controllers/PostController.php'));
        $this->assertFileExists(basePath('examples/blog/app/Controllers/AuthController.php'));
    }

    public function viewsExist(): void
    {
        $this->assertFileExists(basePath('examples/blog/views/welcome.php'));
        $this->assertFileExists(basePath('examples/blog/views/posts/index.php'));
        $this->assertFileExists(basePath('examples/blog/views/posts/show.php'));
        $this->assertFileExists(basePath('examples/blog/views/auth/login.php'));
        $this->assertFileExists(basePath('examples/blog/views/layouts/app.php'));
    }

    public function migrationsExist(): void
    {
        $this->assertFileExists(basePath('examples/blog/database/migrations/2024_01_01_create_users_table.php'));
        $this->assertFileExists(basePath('examples/blog/database/migrations/2024_01_02_create_posts_table.php'));
    }

    public function seedersExist(): void
    {
        $this->assertFileExists(basePath('examples/blog/database/seeders/BlogSeeder.php'));
    }

    public function entryPointExists(): void
    {
        $this->assertFileExists(basePath('examples/blog/public/index.php'));
    }

    public function readmeExists(): void
    {
        $this->assertFileExists(basePath('examples/blog/README.md'));
    }
}
