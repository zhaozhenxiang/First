<?php

declare(strict_types=1);

namespace Tests;

use Bin\Database\Schema\Blueprint;
use Bin\Database\Schema\SchemaBuilder;
use Bin\Testing\TestCase;

/**
 * Schema 阶段10A 回归测试
 *
 * 覆盖：fullText/spatialIndex 编译（此前静默空操作）、modifyColumn 执行臂
 * （此前死代码）、流式 ->change()、morphs 列族、rememberToken、drop 系列
 * 数组形式（列数组推导索引名）。
 *
 * SQLite 无法执行 MySQL 方言 DDL，alter 路径用捕获 SQL 的子类断言编译结果。
 */
class SchemaP1BatchTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new \PDO('sqlite::memory:');
    }

    /**
     * 捕获 execute() 的 SQL 而不真执行（SQLite 不认 FULLTEXT/MODIFY 方言）
     */
    private function capturingBuilder(): CapturingSchemaBuilder
    {
        return new CapturingSchemaBuilder($this->pdo);
    }

    // === fullText / spatialIndex 编译（回归：此前 create 与 alter 两条路径都没有编译分支） ===

    public function testFullTextCompilesInCreatePath(): void
    {
        $builder = $this->capturingBuilder();

        $blueprint = new Blueprint('posts', $this->pdo);
        $blueprint->fullText(['title', 'content']);

        $method = new \ReflectionMethod($builder, 'getCommandSql');
        $sql = $method->invoke($builder, $blueprint, $blueprint->getCommands()[0]);

        $this->assertSame(
            'FULLTEXT KEY `posts_title_content_fulltext` (`title`, `content`)',
            $sql
        );
    }

    public function testSpatialIndexCompilesInCreatePath(): void
    {
        $builder = $this->capturingBuilder();

        $blueprint = new Blueprint('places', $this->pdo);
        $blueprint->spatialIndex('location');

        $method = new \ReflectionMethod($builder, 'getCommandSql');
        $sql = $method->invoke($builder, $blueprint, $blueprint->getCommands()[0]);

        $this->assertSame(
            'SPATIAL KEY `places_location_spatialindex` (`location`)',
            $sql
        );
    }

    public function testFullTextAndSpatialCompileInAlterPath(): void
    {
        $builder = $this->capturingBuilder();

        $builder->table('posts', function (Blueprint $table): void {
            $table->fullText(['title']);
        });

        $this->assertSame(
            ['ALTER TABLE `posts` ADD FULLTEXT KEY `posts_title_fulltext` (`title`)'],
            $builder->captured
        );

        $builder = $this->capturingBuilder();
        $builder->table('places', function (Blueprint $table): void {
            $table->spatialIndex(['location']);
        });

        $this->assertSame(
            ['ALTER TABLE `places` ADD SPATIAL KEY `places_location_spatialindex` (`location`)'],
            $builder->captured
        );
    }

    // === 列修改：命令式 modifyColumn（回归：此前命令被静默丢弃）与流式 ->change() ===

    public function testModifyColumnCommandNowCompiles(): void
    {
        $builder = $this->capturingBuilder();

        $builder->table('users', function (Blueprint $table): void {
            $table->modifyColumn('name', 'string', ['length' => 100, 'nullable' => true]);
        });

        $this->assertSame(
            ['ALTER TABLE `users` MODIFY COLUMN `name` VARCHAR(100) NULL'],
            $builder->captured
        );
    }

    public function testChangeFlowsToModifyColumn(): void
    {
        $builder = $this->capturingBuilder();

        $builder->table('users', function (Blueprint $table): void {
            $table->string('name', 100)->nullable()->change();
        });

        $this->assertSame(
            ['ALTER TABLE `users` MODIFY COLUMN `name` VARCHAR(100) NULL'],
            $builder->captured
        );
    }

    public function testMixedColumnsRouteAddVersusModify(): void
    {
        $builder = $this->capturingBuilder();

        $builder->table('users', function (Blueprint $table): void {
            $table->string('nickname', 50);
            $table->string('email', 190)->change();
        });

        $this->assertCount(2, $builder->captured);
        $this->assertStringStartsWith('ALTER TABLE `users` ADD COLUMN `nickname` VARCHAR(50)', $builder->captured[0]);
        $this->assertSame('ALTER TABLE `users` MODIFY COLUMN `email` VARCHAR(190) NOT NULL', $builder->captured[1]);
    }

    // === morphs 列族与 rememberToken ===

    public function testMorphsAddsTypeAndIdColumnsWithIndex(): void
    {
        $blueprint = new Blueprint('comments', $this->pdo);
        $blueprint->morphs('commentable');

        $columns = $blueprint->getColumns();
        $this->assertSame('commentable_type', $columns[0]->name);
        $this->assertSame('string', $columns[0]->getType());
        $this->assertSame('commentable_id', $columns[1]->name);
        $this->assertSame('bigInteger', $columns[1]->getType());
        $this->assertTrue($columns[1]->unsigned);

        $command = $blueprint->getCommands()[0];
        $this->assertSame('index', $command['type']);
        $this->assertSame(['commentable_type', 'commentable_id'], $command['columns']);
    }

    public function testNullableMorphsMakesBothColumnsNullable(): void
    {
        $blueprint = new Blueprint('comments', $this->pdo);
        $blueprint->nullableMorphs('commentable');

        $columns = $blueprint->getColumns();
        $this->assertTrue($columns[0]->nullable);
        $this->assertTrue($columns[1]->nullable);
    }

    public function testUuidMorphsUsesUuidIdColumn(): void
    {
        $blueprint = new Blueprint('comments', $this->pdo);
        $blueprint->uuidMorphs('commentable');

        $columns = $blueprint->getColumns();
        $this->assertSame('commentable_id', $columns[1]->name);
        $this->assertSame('uuid', $columns[1]->getType());
    }

    public function testRememberTokenColumn(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $column = $blueprint->rememberToken();

        $this->assertSame('remember_token', $column->name);
        $this->assertSame(100, $column->length);
        $this->assertTrue($column->nullable);
    }

    // === drop 系列数组形式 ===

    public function testDropIndexesAcceptNameOrColumnArray(): void
    {
        $builder = $this->capturingBuilder();

        $builder->table('users', function (Blueprint $table): void {
            $table->dropIndex(['state', 'city']);
            $table->dropUnique('users_email_unique');
            $table->dropFullText(['title']);
            $table->dropSpatialIndex('places_location_spatialindex');
        });

        $this->assertSame([
            'ALTER TABLE `users` DROP INDEX `users_state_city_index`',
            'ALTER TABLE `users` DROP INDEX `users_email_unique`',
            'ALTER TABLE `users` DROP INDEX `users_title_fulltext`',
            'ALTER TABLE `users` DROP INDEX `places_location_spatialindex`',
        ], $builder->captured);
    }
}

class CapturingSchemaBuilder extends SchemaBuilder
{
    /** @var list<string> */
    public array $captured = [];

    protected function execute(string $sql): void
    {
        $this->captured[] = $sql;
    }
}
