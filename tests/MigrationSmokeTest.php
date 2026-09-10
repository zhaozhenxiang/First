<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Database\Model;
use Bin\Database\Migrations\Migrator;
use Bin\Database\Migrations\Migration;
use Bin\Database\Seeders\SeederRepository;
use Bin\Database\Schema\Blueprint;
use Bin\Database\Schema\SchemaBuilder;

/**
 * 迁移/Seeder 机制冒烟测试
 *
 * 覆盖此前从未被执行的路径：batch 真实递增、runUp 事务性、
 * Seeder 类名推导、foreignId()->constrained() SQL 生成。
 */
class MigrationSmokeTest extends TestCase
{
    protected \PDO $pdo;

    protected string $migrationsDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // sqlite 兼容的台账表（跳过 MySQL 方言的 Schema::create）
        $this->pdo->exec('CREATE TABLE migrations (id INTEGER PRIMARY KEY AUTOINCREMENT, migration TEXT, batch INTEGER)');

        Model::setConnection($this->pdo);

        $this->migrationsDir = sys_get_temp_dir() . '/orm_migrate_smoke_' . uniqid();
        mkdir($this->migrationsDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->migrationsDir . '/*.php') ?: [] as $f) {
            unlink($f);
        }
        rmdir($this->migrationsDir);

        Model::setConnection(null);
    }

    protected function writeMigration(string $name, array $up, array $down = [], string $append = ''): void
    {
        $upCode = var_export($up, true);
        $downCode = var_export($down, true);

        file_put_contents(
            $this->migrationsDir . "/{$name}.php",
            <<<PHP
            <?php
            use Bin\Database\Migrations\Migration;
            return new class extends Migration {
                public function up(): void {
                    foreach ({$upCode} as \$sql) {
                        \Bin\Database\Model::getConnection()->exec(\$sql);
                    }
                    {$append}
                }
                public function down(): void {
                    foreach ({$downCode} as \$sql) {
                        \Bin\Database\Model::getConnection()->exec(\$sql);
                    }
                }
            };
            PHP
        );
    }

    protected function migrator(): Migrator
    {
        $m = new Migrator($this->migrationsDir);

        return $m->setPath($this->migrationsDir);
    }

    public function testRunAssignsSameBatchThenIncrements(): void
    {
        $this->writeMigration(
            '2024_01_01_000001_create_a',
            ['CREATE TABLE a (id INTEGER)'],
            ['DROP TABLE IF EXISTS a']
        );
        $this->writeMigration(
            '2024_01_01_000002_create_b',
            ['CREATE TABLE b (id INTEGER)'],
            ['DROP TABLE IF EXISTS b']
        );

        $this->migrator()->run();

        $batches = $this->pdo->query('SELECT migration, batch FROM migrations ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertSame([['migration' => '2024_01_01_000001_create_a', 'batch' => 1], ['migration' => '2024_01_01_000002_create_b', 'batch' => 1]], $batches);

        // 第二轮 run（有新迁移）batch 递增
        $this->writeMigration(
            '2024_01_01_000003_create_c',
            ['CREATE TABLE c (id INTEGER)']
        );
        $this->migrator()->run();

        $batch3 = $this->pdo->query("SELECT batch FROM migrations WHERE migration LIKE '%create_c'")->fetchColumn();
        $this->assertSame(2, (int) $batch3);
    }

    public function testFailedMigrationLeavesNoLedgerRow(): void
    {
        $this->writeMigration(
            '2024_01_01_000001_bad',
            ['CREATE TABLE x (id INTEGER)'],
            [],
            'throw new RuntimeException("boom");'
        );

        try {
            $this->migrator()->run();
            $this->fail('迁移应抛出异常');
        } catch (\RuntimeException) {
            // 预期失败
        }

        // 台账无记录，DDL 也被回滚（sqlite 支持 DDL 事务）
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn());
        $this->assertFalse($this->pdo->query("SELECT name FROM sqlite_master WHERE name = 'x'")->fetchColumn());
    }

    public function testRollbackReversesLastMigration(): void
    {
        $this->writeMigration(
            '2024_01_01_000001_create_a',
            ['CREATE TABLE a (id INTEGER)'],
            ['DROP TABLE IF EXISTS a']
        );

        $this->migrator()->run();
        $this->migrator()->rollback();

        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn());
        $this->assertFalse($this->pdo->query("SELECT name FROM sqlite_master WHERE name = 'a'")->fetchColumn());
    }

    public function testSeederDiscoveryProducesLoadableClassNames(): void
    {
        // 仓库自带 database/seeders/UserSeeder.php（namespace Database\Seeders）
        $seeders = SeederRepository::discover();

        $this->assertArrayHasKey('user', $seeders);
        $this->assertSame('Database\\Seeders\\UserSeeder', $seeders['user']);

        $instance = SeederRepository::get('user');
        $this->assertNotNull($instance);
        $this->assertInstanceOf(\Bin\Database\Seeders\Seeder::class, $instance);
    }

    public function testForeignIdConstrainedGeneratesForeignKeySql(): void
    {
        $blueprint = new Blueprint('posts', $this->pdo);
        $blueprint->id();
        $blueprint->foreignId('user_id')->constrained('users')->cascadeOnDelete();

        $build = new \ReflectionMethod(SchemaBuilder::class, 'buildCreateTable');

        $sql = $build->invoke(new SchemaBuilder($this->pdo), $blueprint);

        $this->assertStringContainsString('`user_id` BIGINT UNSIGNED NOT NULL', $sql);
        $this->assertStringContainsString('FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE cascade', $sql);

        // 不传表名时按列名推导
        $bp2 = new Blueprint('comments', $this->pdo);
        $bp2->foreignId('user_id')->constrained();
        $sql2 = $build->invoke(new SchemaBuilder($this->pdo), $bp2);
        $this->assertStringContainsString('REFERENCES `users`(`id`)', $sql2);
    }

    public function testBlueprintLevelNullableAndDefaultThrow(): void
    {
        $blueprint = new Blueprint('t', $this->pdo);

        $this->assertThrows(\BadMethodCallException::class, fn () => $blueprint->nullable());
        $this->assertThrows(\BadMethodCallException::class, fn () => $blueprint->default('x'));
    }
}
