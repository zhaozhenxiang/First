<?php

declare(strict_types=1);

namespace Tests;

use Bin\Database\Model;
use Bin\Database\QueryBuilder;
use Bin\Testing\TestCase;

class LocalScopeTest extends TestCase
{
    protected \PDO $connection;

    public function setUp(): void
    {
        parent::setUp();
        $this->connection = new \PDO('sqlite::memory:');
        $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->connection->exec('CREATE TABLE scope_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            type TEXT NOT NULL DEFAULT "user",
            active INTEGER NOT NULL DEFAULT 1,
            votes INTEGER NOT NULL DEFAULT 0
        )');

        $this->connection->exec("INSERT INTO scope_users (name, type, active, votes) VALUES ('Alice', 'admin', 1, 200)");
        $this->connection->exec("INSERT INTO scope_users (name, type, active, votes) VALUES ('Bob', 'user', 1, 50)");
        $this->connection->exec("INSERT INTO scope_users (name, type, active, votes) VALUES ('Charlie', 'user', 0, 150)");
        $this->connection->exec("INSERT INTO scope_users (name, type, active, votes) VALUES ('Diana', 'admin', 1, 300)");
    }

    // === __callStatic 触发 scope 方法 ===

    public function testStaticCallTriggersScope(): void
    {
        $model = $this->createModel();
        // User::active() 应通过 __callStatic 检测到 scopeActive
        $results = $model::active();
        $this->assertInstanceOf(QueryBuilder::class, $results);
        $rows = $results->get();
        $this->assertEquals(3, $rows->count());
    }

    public function testStaticScopeWithParameter(): void
    {
        $model = $this->createModel();
        $results = $model::ofType('admin');
        $rows = $results->get();
        $this->assertEquals(2, $rows->count());
    }

    // === 链式调用 ===

    public function testScopeChainingWithQueryBuilderMethods(): void
    {
        $model = $this->createModel();
        // active() 返回 QueryBuilder，继续链式调用
        $results = $model::active()->where('type', 'admin')->get();
        $this->assertEquals(2, $results->count());
    }

    public function testScopeWithOrderByAndLimit(): void
    {
        $model = $this->createModel();
        $results = $model::active()->orderBy('name', 'asc')->limit(2)->get();
        $this->assertEquals(2, $results->count());
        $names = $results->pluck('name');
        $this->assertEquals('Alice', $names[0]);
    }

    // === 不存在的方法仍转发到 QueryBuilder ===

    public function testNonScopeMethodForwardsToQueryBuilder(): void
    {
        $model = $this->createModel();
        // where 不对应 scopeWhere，应转发到 QueryBuilder
        $results = $model::where('id', 1)->get();
        $this->assertEquals(1, $results->count());
    }

    // === scope 方法存在性 ===

    public function testScopeMethodsExist(): void
    {
        $model = $this->createModel();
        $this->assertTrue(method_exists($model, 'scopeActive'));
        $this->assertTrue(method_exists($model, 'scopeOfType'));
        $this->assertTrue(method_exists($model, 'scopePopular'));
    }

    // === 聚合查询配合 ===

    public function testCountWithScopeConditions(): void
    {
        $model = $this->createModel();
        $count = $model::active()->count();
        $this->assertEquals(3, $count);
    }

    // === 多条件组合 ===

    public function testMultipleScopesAndConditions(): void
    {
        $model = $this->createModel();
        $results = $model::active()
            ->where('votes', '>=', 100)
            ->orderBy('votes', 'desc')
            ->get();
        // Active + votes>=100: Alice(200), Diana(300) = 2条（Bob只有50votes）
        $this->assertEquals(2, $results->count());
        $names = $results->pluck('name');
        $this->assertContains('Diana', $names);
        $this->assertContains('Alice', $names);
    }

    protected function createModel(): Model
    {
        $model = new class extends Model {
            protected string $table = 'scope_users';
            protected array $guarded = [];

            public function scopeActive(QueryBuilder $query): QueryBuilder
            {
                return $query->where('active', 1);
            }

            public function scopeOfType(QueryBuilder $query, string $type): QueryBuilder
            {
                return $query->where('type', $type);
            }

            public function scopePopular(QueryBuilder $query, int $minVotes = 100): QueryBuilder
            {
                return $query->where('votes', '>=', $minVotes);
            }
        };

        $model::setConnection($this->connection);
        return $model;
    }
}

return new LocalScopeTest();
