<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Database\QueryBuilder;
use Bin\Database\Collection;

class QueryBuilderAdvancedTest extends TestCase
{
    private QueryBuilder $query;

    protected function setUp(): void
    {
        parent::setUp();

        $pdo = new \PDO('sqlite::memory:');
        $this->query = new QueryBuilder($pdo);
    }

    // ========================================
    // 条件查询增强
    // ========================================

    public function testWhereColumnBasic(): void
    {
        $sql = $this->query->from('users')
            ->whereColumn('updated_at', '>', 'created_at')
            ->toSql();

        $this->assertStringContainsString('`updated_at` > `created_at`', $sql);
    }

    public function testWhereColumnTwoArgs(): void
    {
        $sql = $this->query->from('users')
            ->whereColumn('first_name', 'last_name')
            ->toSql();

        $this->assertStringContainsString('`first_name` = `last_name`', $sql);
    }

    public function testOrWhereColumn(): void
    {
        $sql = $this->query->from('users')
            ->whereColumn('col1', '=', 'col2')
            ->orWhereColumn('col3', '=', 'col4')
            ->toSql();

        $this->assertStringContainsString('`col1` = `col2`', $sql);
        $this->assertStringContainsString('OR `col3` = `col4`', $sql);
    }

    public function testWhereRaw(): void
    {
        $sql = $this->query->from('users')
            ->whereRaw('`id` > ? AND `status` = ?', [1, 'active'])
            ->toSql();

        $this->assertStringContainsString('`id` > ? AND `status` = ?', $sql);
    }

    public function testWhereRawBindings(): void
    {
        $q = $this->query->from('users')
            ->whereRaw('id > ?', [5]);

        $this->assertEquals([5], $q->getBindings());
    }

    public function testOrWhereRaw(): void
    {
        $sql = $this->query->from('users')
            ->where('id', 1)
            ->orWhereRaw('`status` = ?', ['active'])
            ->toSql();

        $this->assertStringContainsString('OR `status` = ?', $sql);
    }

    public function testWhereExists(): void
    {
        $sql = $this->query->from('users')
            ->whereExists(function (QueryBuilder $q) {
                $q->from('orders')->whereRaw('orders.user_id = users.id');
            })
            ->toSql();

        $this->assertStringContainsString('EXISTS', $sql);
        $this->assertStringContainsString('SELECT', $sql);
    }

    public function testWhereNotExists(): void
    {
        $sql = $this->query->from('users')
            ->whereNotExists(function (QueryBuilder $q) {
                $q->from('orders')->whereRaw('orders.user_id = users.id');
            })
            ->toSql();

        $this->assertStringContainsString('NOT EXISTS', $sql);
    }

    public function testOrWhereExists(): void
    {
        $sql = $this->query->from('users')
            ->where('id', 1)
            ->orWhereExists(function (QueryBuilder $q) {
                $q->from('orders')->whereRaw('orders.user_id = users.id');
            })
            ->toSql();

        $this->assertStringContainsString('OR EXISTS', $sql);
    }

    public function testWhenTrue(): void
    {
        $sql = $this->query->from('users')
            ->when(true, function (QueryBuilder $q) {
                $q->where('active', 1);
            })
            ->toSql();

        $this->assertStringContainsString('active', $sql);
    }

    public function testWhenFalse(): void
    {
        $sql = $this->query->from('users')
            ->when(false, function (QueryBuilder $q) {
                $q->where('active', 1);
            })
            ->toSql();

        $this->assertStringNotContainsString('active', $sql);
    }

    public function testWhenFalseWithDefault(): void
    {
        $sql = $this->query->from('users')
            ->when(false, function (QueryBuilder $q) {
                $q->where('active', 1);
            }, function (QueryBuilder $q) {
                $q->where('active', 0);
            })
            ->toSql();

        $this->assertStringContainsString('`active` = ?', $sql);
        $this->assertEquals(0, $this->query->getBindings()[0]);
    }

    public function testUnlessTrue(): void
    {
        $sql = $this->query->from('users')
            ->unless(true, function (QueryBuilder $q) {
                $q->where('active', 1);
            })
            ->toSql();

        $this->assertStringNotContainsString('active', $sql);
    }

    public function testUnlessFalse(): void
    {
        $sql = $this->query->from('users')
            ->unless(false, function (QueryBuilder $q) {
                $q->where('active', 1);
            })
            ->toSql();

        $this->assertStringContainsString('active', $sql);
    }

    public function testWhereNot(): void
    {
        $sql = $this->query->from('users')
            ->whereNot('status', 'active')
            ->toSql();

        $this->assertStringContainsString('NOT `status` = ?', $sql);
    }

    public function testWhereInSubquery(): void
    {
        $sql = $this->query->from('users')
            ->whereIn('id', function (QueryBuilder $q) {
                $q->from('orders')->select('user_id')->distinct();
            })
            ->toSql();

        $this->assertStringContainsString('`id` IN (SELECT', $sql);
    }

    public function testWhereNotInSubquery(): void
    {
        $sql = $this->query->from('users')
            ->whereNotIn('id', function (QueryBuilder $q) {
                $q->from('orders')->select('user_id');
            })
            ->toSql();

        $this->assertStringContainsString('`id` NOT IN (SELECT', $sql);
    }

    // ========================================
    // ORDER BY RAW
    // ========================================

    public function testOrderByRaw(): void
    {
        $sql = $this->query->from('users')
            ->orderByRaw('FIELD(status, "active", "pending", "deleted")')
            ->toSql();

        $this->assertStringContainsString('FIELD(status, "active", "pending", "deleted")', $sql);
    }

    // ========================================
    // HAVING RAW
    // ========================================

    public function testHavingRaw(): void
    {
        $sql = $this->query->from('orders')
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->havingRaw('total > ?', [5])
            ->toSql();

        $this->assertStringContainsString('total > ?', $sql);
    }

    // ========================================
    // UNION
    // ========================================

    public function testUnion(): void
    {
        $query1 = $this->query->from('users')->select(['name']);
        $query2 = (new QueryBuilder(new \PDO('sqlite::memory:')))->from('admins')->select(['name']);

        $sql = $query1->union($query2)->toSql();

        $this->assertStringContainsString('UNION', $sql);
        $this->assertStringContainsString('SELECT `name` FROM `users`', $sql);
        $this->assertStringContainsString('SELECT `name` FROM `admins`', $sql);
    }

    public function testUnionAll(): void
    {
        $query1 = $this->query->from('users')->select(['id', 'name']);
        $query2 = (new QueryBuilder(new \PDO('sqlite::memory:')))->from('admins')->select(['id', 'name']);

        $sql = $query1->unionAll($query2)->toSql();

        $this->assertStringContainsString('UNION ALL', $sql);
    }

    public function testUnionWithClosure(): void
    {
        $sql = $this->query->from('users')->select(['name'])
            ->union(function (QueryBuilder $q) {
                $q->from('admins')->select(['name']);
            })
            ->toSql();

        $this->assertStringContainsString('UNION', $sql);
    }

    // ========================================
    // 悲观锁
    // ========================================

    public function testLockForUpdate(): void
    {
        $sql = $this->query->from('users')
            ->where('id', 1)
            ->lockForUpdate()
            ->toSql();

        $this->assertStringContainsString('FOR UPDATE', $sql);
    }

    public function testSharedLock(): void
    {
        $sql = $this->query->from('users')
            ->where('id', 1)
            ->sharedLock()
            ->toSql();

        $this->assertStringContainsString('LOCK IN SHARE MODE', $sql);
    }

    // ========================================
    // get() 返回 Collection
    // ========================================

    public function testGetReturnsCollection(): void
    {
        $this->setUpWithTestData();

        $result = $this->query->from('users')->get();

        $this->assertInstanceOf(Collection::class, $result);
    }

    public function testGetArrayReturnsArray(): void
    {
        $this->setUpWithTestData();

        $result = $this->query->from('users')->getArray();

        $this->assertTrue(is_array($result));
        $this->assertCount(2, $result);
    }

    public function testGetCollectionCountable(): void
    {
        $this->setUpWithTestData();

        $result = $this->query->from('users')->get();

        $this->assertEquals(2, count($result));
        $this->assertFalse($result->isEmpty());
        $this->assertTrue($result->isNotEmpty());
    }

    public function testGetCollectionFirst(): void
    {
        $this->setUpWithTestData();

        $result = $this->query->from('users')
            ->orderBy('id', 'asc')
            ->first();

        $this->assertNotNull($result);
        $this->assertEquals(1, $result['id']);
    }

    public function testGetCollectionPluck(): void
    {
        $this->setUpWithTestData();

        $names = $this->query->from('users')->pluck('name');

        $this->assertEquals(['Alice', 'Bob'], $names);
    }

    // ========================================
    // Collection pop 方法
    // ========================================

    public function testCollectionPop(): void
    {
        $collection = new Collection([1, 2, 3]);

        $popped = $collection->pop();

        $this->assertEquals([1, 2], $popped->toArray());
    }

    public function testCollectionPopEmpty(): void
    {
        $collection = new Collection([]);

        $popped = $collection->pop();

        $this->assertTrue($popped->isEmpty());
    }

    // ========================================
    // chunk / each / tap / explain（需要真实数据）
    // ========================================

    public function testChunk(): void
    {
        $this->setUpWithTestData();

        $chunks = [];
        $this->query->from('users')->orderBy('id')->chunk(1, function (Collection $items) use (&$chunks) {
            $chunks[] = $items->first()['name'];
        });

        $this->assertEquals(['Alice', 'Bob'], $chunks);
    }

    public function testChunkCallbackReturningFalse(): void
    {
        $this->setUpWithTestData();

        $chunks = [];
        $result = $this->query->from('users')->orderBy('id')->chunk(1, function (Collection $items) use (&$chunks) {
            $chunks[] = $items->first()['name'];
            return false; // 停止处理
        });

        $this->assertFalse($result);
        $this->assertCount(1, $chunks);
    }

    public function testChunkById(): void
    {
        $this->setUpWithTestData();

        $chunks = [];
        $this->query->from('users')->chunkById(1, function (Collection $items) use (&$chunks) {
            $chunks[] = $items->first()['name'];
        });

        $this->assertEquals(['Alice', 'Bob'], $chunks);
    }

    public function testEach(): void
    {
        $this->setUpWithTestData();

        $names = [];
        $this->query->from('users')->orderBy('id')->each(function (array $item) use (&$names) {
            $names[] = $item['name'];
        });

        $this->assertEquals(['Alice', 'Bob'], $names);
    }

    public function testEachCallbackReturningFalse(): void
    {
        $this->setUpWithTestData();

        $names = [];
        $this->query->from('users')->orderBy('id')->each(function (array $item) use (&$names) {
            $names[] = $item['name'];
            return false;
        });

        $this->assertCount(1, $names);
    }

    public function testTap(): void
    {
        $tapped = false;
        $result = $this->query->from('users')->tap(function (QueryBuilder $q) use (&$tapped) {
            $tapped = true;
            $q->where('id', 1);
        });

        $this->assertTrue($tapped);
        $this->assertSame($result, $this->query);
    }

    // ========================================
    // SQL 编译正确性
    // ========================================

    public function testCombinedWhereConditions(): void
    {
        $sql = $this->query->from('users')
            ->where('status', 'active')
            ->whereColumn('age', '>=', '18')
            ->whereExists(function (QueryBuilder $q) {
                $q->from('profiles')->whereRaw('profiles.user_id = users.id');
            })
            ->whereNotIn('role', ['banned', 'suspended'])
            ->toSql();

        $this->assertStringContainsString('`status` = ?', $sql);
        $this->assertStringContainsString('`age` >= `18`', $sql);
        $this->assertStringContainsString('EXISTS', $sql);
        $this->assertStringContainsString('NOT IN', $sql);
    }

    public function testComplexQuery(): void
    {
        $sql = $this->query->from('users')
            ->select(['id', 'name', 'email'])
            ->where('active', 1)
            ->whereRaw('created_at > ?', ['2024-01-01'])
            ->orderByRaw('FIELD(status, "admin", "user")')
            ->limit(10)
            ->offset(5)
            ->lockForUpdate()
            ->toSql();

        $this->assertStringContainsString('SELECT `id`, `name`, `email`', $sql);
        $this->assertStringContainsString('`active` = ?', $sql);
        $this->assertStringContainsString('created_at > ?', $sql);
        $this->assertStringContainsString('FIELD(status', $sql);
        $this->assertStringContainsString('LIMIT 10', $sql);
        $this->assertStringContainsString('OFFSET 5', $sql);
        $this->assertStringContainsString('FOR UPDATE', $sql);
    }

    // ========================================
    // 辅助方法
    // ========================================

    private function setUpWithTestData(): void
    {
        $pdo = $this->query->getConnection();
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');
        $pdo->exec("INSERT INTO users (id, name, email) VALUES (1, 'Alice', 'alice@test.com')");
        $pdo->exec("INSERT INTO users (id, name, email) VALUES (2, 'Bob', 'bob@test.com')");
    }
}
