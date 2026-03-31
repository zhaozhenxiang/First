<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Database\Schema\Blueprint;
use Bin\Database\Schema\ColumnDefinition;
use Bin\Database\Schema\ForeignKey;

class SchemaBlueprintTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    // === 列类型 ===

    public function testIdColumn(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $col = $blueprint->id();

        $this->assertInstanceOf(ColumnDefinition::class, $col);
        $this->assertEquals('id', $col->getName());
    }

    public function testStringColumn(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $col = $blueprint->string('name');

        $this->assertEquals('name', $col->getName());
        $this->assertEquals(255, $col->length);
    }

    public function testStringColumnCustomLength(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $col = $blueprint->string('title', 100);

        $this->assertEquals('title', $col->getName());
        $this->assertEquals(100, $col->length);
    }

    public function testTextColumn(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $col = $blueprint->text('body');

        $this->assertEquals('body', $col->getName());
    }

    public function testIntegerColumn(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $col = $blueprint->integer('age');

        $this->assertEquals('age', $col->getName());
    }

    public function testBooleanColumn(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $col = $blueprint->boolean('active');

        $this->assertEquals('active', $col->getName());
    }

    public function testTimestamps(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $blueprint->timestamps();

        $columns = $blueprint->getColumns();
        $names = array_map(fn($c) => $c->getName(), $columns);
        $this->assertContains('created_at', $names);
        $this->assertContains('updated_at', $names);
    }

    // === ColumnDefinition 链式调用 ===

    public function testColumnNullable(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $col = $blueprint->string('email')->nullable();

        $this->assertTrue($col->nullable);
    }

    public function testColumnDefault(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $col = $blueprint->string('role')->default('user');

        $this->assertEquals('user', $col->default);
    }

    public function testColumnUnique(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $col = $blueprint->string('email')->unique();

        $this->assertTrue($col->unique);
    }

    public function testColumnUnsigned(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $col = $blueprint->integer('score')->unsigned();

        $this->assertTrue($col->unsigned);
    }

    public function testColumnComment(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $col = $blueprint->string('name')->comment('user name');

        $this->assertEquals('user name', $col->comment);
    }

    public function testColumnAutoIncrement(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $col = $blueprint->integer('id')->autoIncrement();

        $this->assertTrue($col->autoIncrement);
    }

    public function testColumnPrimary(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $col = $blueprint->integer('id')->primary();

        $this->assertTrue($col->primary);
    }

    // === 索引 ===

    public function testPrimaryIndex(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $blueprint->id();
        $blueprint->primary('id');

        $commands = $blueprint->getCommands();
        $this->assertNotEmpty($commands);
    }

    public function testUniqueIndex(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $blueprint->string('email');
        $blueprint->unique('email');

        $commands = $blueprint->getCommands();
        $this->assertNotEmpty($commands);
    }

    public function testIndexCreation(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $blueprint->string('name');
        $blueprint->index('name');

        $commands = $blueprint->getCommands();
        $this->assertNotEmpty($commands);
    }

    // === 外键 ===

    public function testForeignKeyCreation(): void
    {
        $blueprint = new Blueprint('posts', $this->pdo);
        $fk = $blueprint->foreign('user_id', 'users', 'id');

        $this->assertInstanceOf(ForeignKey::class, $fk);
        $this->assertEquals('user_id', $fk->getColumn());
        $this->assertEquals('users', $fk->getReferencedTable());
        $this->assertEquals('id', $fk->getReferencedColumn());
    }

    public function testForeignKeyCascadeOnDelete(): void
    {
        $blueprint = new Blueprint('posts', $this->pdo);
        $fk = $blueprint->foreign('user_id', 'users', 'id')->cascadeOnDelete();

        $this->assertEquals('cascade', $fk->getOnDelete());
    }

    public function testForeignKeyNullOnDelete(): void
    {
        $blueprint = new Blueprint('posts', $this->pdo);
        $fk = $blueprint->foreign('user_id', 'users', 'id')->nullOnDelete();

        $this->assertEquals('set null', $fk->getOnDelete());
    }

    public function testForeignKeyRestrictOnDelete(): void
    {
        $blueprint = new Blueprint('posts', $this->pdo);
        $fk = $blueprint->foreign('user_id', 'users', 'id')->restrictOnDelete();

        $this->assertEquals('restrict', $fk->getOnDelete());
    }

    public function testForeignKeyCascadeOnUpdate(): void
    {
        $blueprint = new Blueprint('posts', $this->pdo);
        $fk = $blueprint->foreign('user_id', 'users', 'id')->cascadeOnUpdate();

        $this->assertEquals('cascade', $fk->getOnUpdate());
    }

    // === 修改列 ===

    public function testRenameColumn(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $blueprint->renameColumn('name', 'full_name');

        $commands = $blueprint->getCommands();
        $this->assertNotEmpty($commands);
    }

    public function testDropColumn(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $blueprint->dropColumn('old_column');

        $commands = $blueprint->getCommands();
        $this->assertNotEmpty($commands);
    }

    // === ColumnDefinition toArray ===

    public function testColumnDefinitionToArray(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $col = $blueprint->string('email')->nullable()->default('test@test.com');
        $arr = $col->toArray();

        $this->assertArrayHasKey('name', $arr);
        $this->assertArrayHasKey('nullable', $arr);
        $this->assertArrayHasKey('default', $arr);
        $this->assertTrue($arr['nullable']);
        $this->assertEquals('test@test.com', $arr['default']);
    }

    // === Blueprint getters ===

    public function testGetTable(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $this->assertEquals('users', $blueprint->getTable());
    }

    public function testGetConnection(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $this->assertSame($this->pdo, $blueprint->getConnection());
    }

    // === Engine/Charset/Collation ===

    public function testEngineSetting(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $result = $blueprint->engine('MyISAM');
        $this->assertSame($blueprint, $result);
        $this->assertEquals('MyISAM', $blueprint->getEngine());
    }

    public function testCharsetSetting(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $result = $blueprint->charset('utf8');
        $this->assertSame($blueprint, $result);
        $this->assertEquals('utf8', $blueprint->getCharset());
    }

    public function testCollationSetting(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $result = $blueprint->collation('utf8_general_ci');
        $this->assertSame($blueprint, $result);
        $this->assertEquals('utf8_general_ci', $blueprint->getCollation());
    }

    public function testDefaultEngineIsInnodb(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $this->assertEquals('InnoDB', $blueprint->getEngine());
    }

    public function testDefaultCharsetIsUtf8mb4(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $this->assertEquals('utf8mb4', $blueprint->getCharset());
    }

    // === 软删除 ===

    public function testSoftDeletes(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $blueprint->softDeletes();

        $columns = $blueprint->getColumns();
        $names = array_map(fn($c) => $c->getName(), $columns);
        $this->assertContains('deleted_at', $names);
    }

    // === 各种列类型 ===

    public function testDecimalColumn(): void
    {
        $blueprint = new Blueprint('products', $this->pdo);
        $col = $blueprint->decimal('price', 10, 2);

        $this->assertEquals('price', $col->getName());
    }

    public function testEnumColumn(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $col = $blueprint->enum('status', ['active', 'inactive']);

        $this->assertEquals('status', $col->getName());
    }

    public function testJsonColumn(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $col = $blueprint->json('metadata');

        $this->assertEquals('metadata', $col->getName());
    }

    public function testDateColumn(): void
    {
        $blueprint = new Blueprint('events', $this->pdo);
        $col = $blueprint->date('event_date');

        $this->assertEquals('event_date', $col->getName());
    }

    public function testUuidColumn(): void
    {
        $blueprint = new Blueprint('users', $this->pdo);
        $col = $blueprint->uuid();

        $this->assertEquals('uuid', $col->getName());
    }

    public function testBigIntegerColumn(): void
    {
        $blueprint = new Blueprint('logs', $this->pdo);
        $col = $blueprint->bigInteger('count');

        $this->assertEquals('count', $col->getName());
    }

    public function testFloatColumn(): void
    {
        $blueprint = new Blueprint('measurements', $this->pdo);
        $col = $blueprint->float('value');

        $this->assertEquals('value', $col->getName());
    }

    public function testBinaryColumn(): void
    {
        $blueprint = new Blueprint('files', $this->pdo);
        $col = $blueprint->binary('data');

        $this->assertEquals('data', $col->getName());
    }
}
