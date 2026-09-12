<?php

declare(strict_types=1);

namespace Tests;

use Bin\Database\Model;
use Bin\Database\QueryBuilder;
use Bin\Testing\TestCase;
use PDO;

/**
 * 阶段12 回归测试：子查询 select/from/orderBy + cursorPaginate 泛化
 * （任意排序列/方向、双向游标）。
 */
class SubQueryAndCursorTest extends TestCase
{
    protected \PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE sq_users (id INTEGER PRIMARY KEY, name TEXT, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE sq_posts (id INTEGER PRIMARY KEY, user_id INTEGER, views INTEGER, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE sq_stats (id INTEGER PRIMARY KEY, total INTEGER)');

        SqUser::resetBooted();
        Model::setConnection($this->pdo);
    }

    protected function tearDown(): void
    {
        SqUser::flushEventListeners();
        Model::setConnection(null);
    }

    // === 子查询 ===

    public function testSelectSubComputesPerRowAggregate(): void
    {
        $this->pdo->exec("INSERT INTO sq_users (id, name) VALUES (1, 'a'), (2, 'b')");
        $this->pdo->exec("INSERT INTO sq_posts (user_id, views) VALUES (1, 10), (1, 20), (2, 5)");

        $users = SqUser::query()->orderBy('id')->selectSub(
            fn (QueryBuilder $q) => $q->from('sq_posts')->selectRaw('SUM(views)')->whereColumn('sq_posts.user_id', 'sq_users.id'),
            'total_views'
        )->get();

        $this->assertSame(30, (int) $users[0]->getAttribute('total_views'));
        $this->assertSame(5, (int) $users[1]->getAttribute('total_views'));
    }

    public function testSelectSubBindingOrderCorrect(): void
    {
        $this->pdo->exec("INSERT INTO sq_users (id, name) VALUES (1, 'a')");

        $query = SqUser::query()
            ->selectSub(
                fn (QueryBuilder $q) => $q->from('sq_posts')->selectRaw('COUNT(*)')->where('views', '>', 3),
                'popular'
            )
            ->where('name', '=', 'a');

        // select 子查询绑定先于 where 绑定（占位符顺序）
        $this->assertSame([3, 'a'], $query->getBindings());

        $user = $query->first();
        $this->assertSame(0, (int) $user->getAttribute('popular'));
    }

    public function testFromSubQueriesDerivedTable(): void
    {
        $this->pdo->exec("INSERT INTO sq_stats (id, total) VALUES (1, 100), (2, 300)");

        $rows = (new QueryBuilder($this->pdo))
            ->fromSub(
                fn (QueryBuilder $q) => $q->from('sq_stats')->where('total', '>', 50),
                'totals'
            )
            ->select('totals.id', 'totals.total')
            ->orderBy('totals.id')
            ->get();

        $this->assertSame(2, $rows->count());
        $this->assertSame('100', (string) $rows->first()['total']);
    }

    public function testOrderBySubQuery(): void
    {
        $this->pdo->exec("INSERT INTO sq_users (id, name) VALUES (1, 'a'), (2, 'b')");
        $this->pdo->exec("INSERT INTO sq_posts (user_id, views) VALUES (1, 10), (1, 20), (2, 50)");

        // 按聚合 views 排序：b（50）在前（子查询列自动归一化，selectRaw 不残留 *）
        $users = SqUser::query()->orderBy(
            fn (QueryBuilder $q) => $q->from('sq_posts')->selectRaw('SUM(views)')->whereColumn('sq_posts.user_id', 'sq_users.id'),
            'desc'
        )->get();

        $this->assertSame(['b', 'a'], $users->pluck('name'));
    }

    // === cursorPaginate 泛化 ===

    private function seedCursorRows(): void
    {
        $insert = $this->pdo->prepare('INSERT INTO sq_users (id, name, created_at) VALUES (?, ?, ?)');
        foreach ([
            [1, 'u1', '2026-01-01 00:00:00'],
            [2, 'u2', '2026-01-02 00:00:00'],
            [3, 'u3', '2026-01-03 00:00:00'],
            [4, 'u4', '2026-01-04 00:00:00'],
            [5, 'u5', '2026-01-05 00:00:00'],
        ] as $row) {
            $insert->execute($row);
        }
    }

    public function testCursorPaginateWalksForwardAndBackward(): void
    {
        $this->seedCursorRows();

        $page1 = SqUser::query()->orderBy('id')->cursorPaginate(2);
        $this->assertSame(['u1', 'u2'], array_column($page1->items(), 'name'));
        $this->assertNotNull($page1->nextCursor());
        $this->assertNull($page1->previousCursor());

        $page2 = SqUser::query()->orderBy('id')->cursorPaginate(2, ['*'], 'cursor', $page1->nextCursor());
        $this->assertSame(['u3', 'u4'], array_column($page2->items(), 'name'));
        $this->assertNotNull($page2->previousCursor());

        // 回到上一页：内容与 page1 相同
        $back = SqUser::query()->orderBy('id')->cursorPaginate(2, ['*'], 'cursor', $page2->previousCursor());
        $this->assertSame(['u1', 'u2'], array_column($back->items(), 'name'));
    }

    public function testCursorPaginateRespectsCustomOrderColumnAndDirection(): void
    {
        $this->seedCursorRows();

        // 按 created_at 降序：第一页是最新两条
        $page1 = SqUser::query()->orderBy('created_at', 'desc')->cursorPaginate(2);
        $this->assertSame(['u5', 'u4'], array_column($page1->items(), 'name'));

        $page2 = SqUser::query()->orderBy('created_at', 'desc')->cursorPaginate(2, ['*'], 'cursor', $page1->nextCursor());
        $this->assertSame(['u3', 'u2'], array_column($page2->items(), 'name'));

        // 降序方向下向前翻页
        $back = SqUser::query()->orderBy('created_at', 'desc')->cursorPaginate(2, ['*'], 'cursor', $page2->previousCursor());
        $this->assertSame(['u5', 'u4'], array_column($back->items(), 'name'));
    }

    public function testCursorPaginateLastPageHasNoNextCursor(): void
    {
        $this->seedCursorRows();

        $page1 = SqUser::query()->orderBy('id')->cursorPaginate(4);
        $page2 = SqUser::query()->orderBy('id')->cursorPaginate(4, ['*'], 'cursor', $page1->nextCursor());

        $this->assertSame(['u5'], array_column($page2->items(), 'name'));
        $this->assertNull($page2->nextCursor());
        $this->assertTrue($page2->hasMorePages() === false);
    }

    public function testCursorPaginateRejectsForeignCursor(): void
    {
        $this->seedCursorRows();

        $foreign = base64_encode(json_encode(['other_col' => 1]));

        try {
            SqUser::query()->orderBy('id')->cursorPaginate(2, ['*'], 'cursor', $foreign);
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('Invalid cursor token', $exception->getMessage());
        }
    }
}

class SqUser extends Model
{
    protected string $table = 'sq_users';

    protected array $fillable = ['name'];
}
