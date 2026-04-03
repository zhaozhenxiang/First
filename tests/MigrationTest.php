<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Database\Migrations\Migration;
use Bin\Database\Migrations\MigrationCreator;

class MigrationTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/migration_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // 清理临时目录
        $files = glob($this->tmpDir . '/*.php');
        foreach ($files as $file) {
            unlink($file);
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
        parent::tearDown();
    }

    // === Migration 基类 ===

    public function testMigrationGetNameFromClass(): void
    {
        $migration = new class extends Migration {
            public function up(): void {}
            public function down(): void {}
        };
        $this->assertNotEmpty($migration->getName());
    }

    public function testMigrationGetNameCustom(): void
    {
        $migration = new class extends Migration {
            protected string $name = 'create_users_table';
            public function up(): void {}
            public function down(): void {}
        };
        $this->assertEquals('create_users_table', $migration->getName());
    }

    public function testMigrationGetNameDefaultsToClassName(): void
    {
        $migration = new class extends Migration {
            public function up(): void {}
            public function down(): void {}
        };
        $this->assertStringContainsString('Migration', $migration->getName());
    }

    // === MigrationCreator ===

    public function testCreatorCreatesBlankFile(): void
    {
        $creator = new MigrationCreator($this->tmpDir);
        $path = $creator->create('add custom field');

        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringContainsString('extends Migration', $content);
        $this->assertStringContainsString('up', $content);
        $this->assertStringContainsString('down', $content);
    }

    public function testCreatorCreatesTableFile(): void
    {
        $creator = new MigrationCreator($this->tmpDir);
        $path = $creator->create('create users', 'users');

        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringContainsString('users', $content);
        $this->assertStringContainsString('Schema::create', $content);
        $this->assertStringContainsString('Schema::dropIfExists', $content);
    }

    public function testCreatorFilenameFormat(): void
    {
        $creator = new MigrationCreator($this->tmpDir);
        $path = $creator->create('create_posts_table');

        $filename = basename($path);
        // 格式: YYYY_MM_DD_HHMMSS_create_posts_table.php
        $this->assertMatchesRegularExpression('/^\d{4}_\d{2}_\d{2}_\d{6}_create_posts_table\.php$/', $filename);
    }

    public function testCreatorSpacesToUnderscores(): void
    {
        $creator = new MigrationCreator($this->tmpDir);
        $path = $creator->create('add email field');

        $filename = basename($path);
        $this->assertStringContainsString('add_email_field', $filename);
    }

    public function testCreatorSetNamespace(): void
    {
        $creator = new MigrationCreator($this->tmpDir);
        $creator->setNamespace('App\\Migrations');

        // 匿名类迁移不再包含 namespace 声明，验证 setter 不报错即可
        $path = $creator->create('test migration');
        $this->assertFileExists($path);
    }

    public function testCreatorSetPath(): void
    {
        $altDir = sys_get_temp_dir() . '/migration_alt_' . uniqid();
        mkdir($altDir, 0755, true);

        $creator = new MigrationCreator($this->tmpDir);
        $creator->setPath($altDir);
        $path = $creator->create('alt migration');

        $this->assertFileExists($path);
        $this->assertStringContainsString($altDir, $path);

        // 清理
        unlink($path);
        rmdir($altDir);
    }

    public function testCreatorReturnsPath(): void
    {
        $creator = new MigrationCreator($this->tmpDir);
        $path = $creator->create('test_return');

        $this->assertTrue(is_string($path));
        $this->assertStringEndsWith('.php', $path);
    }

    public function testCreatorTableStubContainsIdAndTimestamps(): void
    {
        $creator = new MigrationCreator($this->tmpDir);
        $path = $creator->create('create products', 'products');

        $content = file_get_contents($path);
        $this->assertStringContainsString('$table->id()', $content);
        $this->assertStringContainsString('$table->timestamps()', $content);
    }
}
