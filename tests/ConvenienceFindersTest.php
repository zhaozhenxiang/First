<?php

declare(strict_types=1);

namespace Tests;

use Bin\Database\Model;
use Bin\Testing\TestCase;

class ConvenienceFindersTest extends TestCase
{
    protected \PDO $connection;

    public function setUp(): void
    {
        parent::setUp();
        $this->connection = new \PDO('sqlite::memory:');
        $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->connection->exec('CREATE TABLE finder_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            active INTEGER NOT NULL DEFAULT 1
        )');

        $this->connection->exec("INSERT INTO finder_users (name, email, active) VALUES ('Alice', 'alice@test.com', 1)");
        $this->connection->exec("INSERT INTO finder_users (name, email, active) VALUES ('Bob', 'bob@test.com', 1)");
        $this->connection->exec("INSERT INTO finder_users (name, email, active) VALUES ('Charlie', 'charlie@test.com', 0)");

        FinderTestUser::setConnection($this->connection);
    }

    public function tearDown(): void
    {
        FinderTestUser::setConnection(null);
        parent::tearDown();
    }

    // === firstOrCreate ===

    public function testFirstOrCreateFindsExisting(): void
    {
        $user = FinderTestUser::firstOrCreate(['email' => 'alice@test.com']);
        $this->assertEquals('Alice', $user->name);
        $this->assertFalse($user->wasRecentlyCreated);
    }

    public function testFirstOrCreateCreatesNew(): void
    {
        $user = FinderTestUser::firstOrCreate(
            ['email' => 'new@test.com'],
            ['name' => 'NewUser', 'active' => 1]
        );
        $this->assertEquals('new@test.com', $user->email);
        $this->assertEquals('NewUser', $user->name);
        $this->assertTrue($user->wasRecentlyCreated);

        // 验证已写入数据库
        $found = FinderTestUser::where('email', 'new@test.com')->first();
        $this->assertNotNull($found);
    }

    // === firstOrNew ===

    public function testFirstOrNewFindsExisting(): void
    {
        $user = FinderTestUser::firstOrNew(['email' => 'bob@test.com']);
        $this->assertTrue($user->exists);
        $this->assertEquals('Bob', $user->name);
    }

    public function testFirstOrNewReturnsUnpersistedInstance(): void
    {
        $user = FinderTestUser::firstOrNew(
            ['email' => 'unsaved@test.com'],
            ['name' => 'Unsaved', 'active' => 1]
        );
        $this->assertFalse($user->exists);
        $this->assertEquals('unsaved@test.com', $user->email);
        $this->assertEquals('Unsaved', $user->name);

        // 验证数据库中不存在
        $found = FinderTestUser::where('email', 'unsaved@test.com')->first();
        $this->assertNull($found);
    }

    // === updateOrCreate ===

    public function testUpdateOrCreateUpdatesExisting(): void
    {
        $user = FinderTestUser::updateOrCreate(
            ['email' => 'alice@test.com'],
            ['name' => 'AliceUpdated']
        );
        $this->assertEquals('AliceUpdated', $user->name);
        $this->assertFalse($user->wasRecentlyCreated);

        // 验证数据库已更新
        $fresh = FinderTestUser::where('email', 'alice@test.com')->first();
        $this->assertEquals('AliceUpdated', $fresh->name);
    }

    public function testUpdateOrCreateCreatesNew(): void
    {
        $user = FinderTestUser::updateOrCreate(
            ['email' => 'created@test.com'],
            ['name' => 'Created', 'active' => 1]
        );
        $this->assertEquals('created@test.com', $user->email);
        $this->assertTrue($user->wasRecentlyCreated);
    }

    // === firstWhere ===

    public function testFirstWhereFindsRecord(): void
    {
        $user = FinderTestUser::firstWhere('email', 'bob@test.com');
        $this->assertNotNull($user);
        $this->assertEquals('Bob', $user->name);
    }

    public function testFirstWhereReturnsNullWhenNotFound(): void
    {
        $user = FinderTestUser::firstWhere('email', 'nobody@test.com');
        $this->assertNull($user);
    }

    // === findOrFail ===

    public function testFindOrFailReturnsModel(): void
    {
        $user = FinderTestUser::findOrFail(1);
        $this->assertEquals('Alice', $user->name);
    }

    public function testFindOrFailThrowsOnMissing(): void
    {
        try {
            FinderTestUser::findOrFail(999);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('No query results', $e->getMessage());
        }
    }

    // === findOr ===

    public function testFindOrReturnsCallbackOnMissing(): void
    {
        $result = FinderTestUser::findOr(999, fn() => 'default_value');
        $this->assertEquals('default_value', $result);
    }

    public function testFindOrReturnsModelWhenFound(): void
    {
        $result = FinderTestUser::findOr(1, fn() => 'default_value');
        $this->assertInstanceOf(Model::class, $result);
    }

    // === firstOr ===

    public function testFirstOrReturnsCallbackWhenEmpty(): void
    {
        $result = FinderTestUser::where('email', 'nobody@test.com')->firstOr(fn() => 'default');
        $this->assertEquals('default', $result);
    }

    // === value ===

    public function testValueReturnsSingleColumn(): void
    {
        $name = FinderTestUser::where('id', 1)->value('name');
        $this->assertEquals('Alice', $name);
    }

    public function testValueReturnsNullWhenNotFound(): void
    {
        $name = FinderTestUser::where('id', 999)->value('name');
        $this->assertNull($name);
    }

    // === exists / doesntExist ===

    public function testExistsReturnsTrueWhenRecordsFound(): void
    {
        $this->assertTrue(FinderTestUser::where('active', 1)->exists());
    }

    public function testDoesntExistReturnsTrueWhenNoRecords(): void
    {
        $this->assertTrue(FinderTestUser::where('email', 'nobody@test.com')->doesntExist());
    }
}

class FinderTestUser extends Model
{
    protected string $table = 'finder_users';
    protected array $guarded = [];
    protected bool $timestamps = false;
}

return new ConvenienceFindersTest();
