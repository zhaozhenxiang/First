<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Database\LengthAwarePaginator;
use Bin\Database\Paginator;
use Bin\Database\CursorPaginator;
use Bin\Database\QueryBuilder;
use PDO;

/**
 * 分页功能测试
 */
class PaginationTest extends TestCase
{
    protected ?PDO $connection = null;

    protected ?QueryBuilder $queryBuilder = null;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建 SQLite 内存数据库
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 创建测试表
        $this->connection->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');

        // 插入测试数据（25 条）
        for ($i = 1; $i <= 25; $i++) {
            $stmt = $this->connection->prepare('INSERT INTO users (name, email) VALUES (?, ?)');
            $stmt->execute(["User {$i}", "user{$i}@example.com"]);
        }

        $this->queryBuilder = new QueryBuilder($this->connection);
        $this->queryBuilder->from('users');
    }

    protected function tearDown(): void
    {
        $this->connection = null;
        $this->queryBuilder = null;

        parent::tearDown();
    }

    // =========================================================================
    // LengthAwarePaginator 测试
    // =========================================================================

    public function testLengthAwarePaginatorCreation(): void
    {
        $items = ['a', 'b', 'c'];
        $paginator = new LengthAwarePaginator($items, 100, 10, 1);

        $this->assertEquals($items, $paginator->items());
        $this->assertEquals(100, $paginator->total());
        $this->assertEquals(10, $paginator->perPage());
        $this->assertEquals(1, $paginator->currentPage());
    }

    public function testLengthAwarePaginatorLastPage(): void
    {
        $paginator = new LengthAwarePaginator([], 100, 10, 1);
        $this->assertEquals(10, $paginator->lastPage());

        $paginator = new LengthAwarePaginator([], 95, 10, 1);
        $this->assertEquals(10, $paginator->lastPage());

        $paginator = new LengthAwarePaginator([], 0, 10, 1);
        $this->assertEquals(1, $paginator->lastPage());
    }

    public function testLengthAwarePaginatorHasMorePages(): void
    {
        $paginator = new LengthAwarePaginator([], 100, 10, 5);
        $this->assertTrue($paginator->hasMorePages());

        $paginator = new LengthAwarePaginator([], 100, 10, 10);
        $this->assertFalse($paginator->hasMorePages());

        $paginator = new LengthAwarePaginator([], 100, 10, 15);
        $this->assertFalse($paginator->hasMorePages());
    }

    public function testLengthAwarePaginatorOnFirstPage(): void
    {
        $paginator = new LengthAwarePaginator([], 100, 10, 1);
        $this->assertTrue($paginator->onFirstPage());

        $paginator = new LengthAwarePaginator([], 100, 10, 2);
        $this->assertFalse($paginator->onFirstPage());
    }

    public function testLengthAwarePaginatorOnLastPage(): void
    {
        $paginator = new LengthAwarePaginator([], 100, 10, 10);
        $this->assertTrue($paginator->onLastPage());

        $paginator = new LengthAwarePaginator([], 100, 10, 9);
        $this->assertFalse($paginator->onLastPage());
    }

    public function testLengthAwarePaginatorUrls(): void
    {
        $paginator = new LengthAwarePaginator([], 100, 10, 2, ['path' => '/users']);

        // 第一页不显示页码参数（更简洁）
        $this->assertEquals('/users', $paginator->firstPageUrl());
        $this->assertEquals('/users?page=10', $paginator->lastPageUrl());
        $this->assertEquals('/users', $paginator->previousPageUrl());
        $this->assertEquals('/users?page=3', $paginator->nextPageUrl());
    }

    public function testLengthAwarePaginatorNoPreviousPage(): void
    {
        $paginator = new LengthAwarePaginator([], 100, 10, 1);
        $this->assertNull($paginator->previousPageUrl());
    }

    public function testLengthAwarePaginatorNoNextPage(): void
    {
        $paginator = new LengthAwarePaginator([], 100, 10, 10);
        $this->assertNull($paginator->nextPageUrl());
    }

    public function testLengthAwarePaginatorAppends(): void
    {
        $paginator = new LengthAwarePaginator([], 100, 10, 2, ['path' => '/users']);
        $paginator->appends('sort', 'name');
        $paginator->appends(['order' => 'desc']);

        $this->assertEquals('/users?sort=name&order=desc&page=3', $paginator->nextPageUrl());
    }

    public function testLengthAwarePaginatorFragment(): void
    {
        $paginator = new LengthAwarePaginator([], 100, 10, 2, ['path' => '/users']);
        $paginator->fragment('comments');

        $this->assertEquals('/users?page=3#comments', $paginator->nextPageUrl());
    }

    public function testLengthAwarePaginatorFirstLastItem(): void
    {
        $items = range(1, 10);
        $paginator = new LengthAwarePaginator($items, 100, 10, 3);

        $this->assertEquals(21, $paginator->firstItem());
        $this->assertEquals(30, $paginator->lastItem());
    }

    public function testLengthAwarePaginatorEmptyItems(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 10, 1);

        $this->assertNull($paginator->firstItem());
        $this->assertNull($paginator->lastItem());
    }

    public function testLengthAwarePaginatorToArray(): void
    {
        $items = ['a', 'b'];
        $paginator = new LengthAwarePaginator($items, 100, 10, 2, ['path' => '/users']);
        $array = $paginator->toArray();

        $this->assertEquals(2, $array['current_page']);
        $this->assertEquals($items, $array['data']);
        $this->assertEquals(10, $array['last_page']);
        $this->assertEquals(10, $array['per_page']);
        $this->assertEquals(100, $array['total']);
        $this->assertEquals(11, $array['from']);
        $this->assertEquals(12, $array['to']);
    }

    public function testLengthAwarePaginatorJsonSerialize(): void
    {
        $items = ['a', 'b'];
        $paginator = new LengthAwarePaginator($items, 100, 10, 2, ['path' => '/users']);
        $json = $paginator->toJson();

        $decoded = json_decode($json, true);
        $this->assertEquals(2, $decoded['current_page']);
        $this->assertEquals(100, $decoded['total']);
    }

    public function testLengthAwarePaginatorArrayAccess(): void
    {
        $items = ['a', 'b', 'c'];
        $paginator = new LengthAwarePaginator($items, 100, 10, 1);

        $this->assertTrue(isset($paginator[0]));
        $this->assertEquals('a', $paginator[0]);
        $this->assertEquals('b', $paginator[1]);

        $paginator[3] = 'd';
        $this->assertEquals('d', $paginator[3]);

        unset($paginator[3]);
        $this->assertFalse(isset($paginator[3]));
    }

    public function testLengthAwarePaginatorCountable(): void
    {
        $items = ['a', 'b', 'c'];
        $paginator = new LengthAwarePaginator($items, 100, 10, 1);

        $this->assertEquals(3, count($paginator));
    }

    public function testLengthAwarePaginatorIterator(): void
    {
        $items = ['a', 'b', 'c'];
        $paginator = new LengthAwarePaginator($items, 100, 10, 1);

        $collected = [];
        foreach ($paginator as $item) {
            $collected[] = $item;
        }

        $this->assertEquals($items, $collected);
    }

    // =========================================================================
    // Paginator（简单分页器）测试
    // =========================================================================

    public function testSimplePaginatorCreation(): void
    {
        $items = ['a', 'b', 'c'];
        $paginator = new Paginator($items, 10, 2, [], true);

        $this->assertEquals($items, $paginator->items());
        $this->assertEquals(10, $paginator->perPage());
        $this->assertEquals(2, $paginator->currentPage());
        $this->assertTrue($paginator->hasMorePages());
    }

    public function testSimplePaginatorNoMorePages(): void
    {
        $paginator = new Paginator(['a'], 10, 1, [], false);
        $this->assertFalse($paginator->hasMorePages());
    }

    public function testSimplePaginatorUrls(): void
    {
        $paginator = new Paginator(['a'], 10, 2, ['path' => '/items'], true);

        // 第一页不显示页码参数
        $this->assertEquals('/items', $paginator->previousPageUrl());
        $this->assertEquals('/items?page=3', $paginator->nextPageUrl());
    }

    public function testSimplePaginatorNoPreviousPage(): void
    {
        $paginator = new Paginator(['a'], 10, 1, [], true);
        $this->assertNull($paginator->previousPageUrl());
    }

    public function testSimplePaginatorNoNextPage(): void
    {
        $paginator = new Paginator(['a'], 10, 1, [], false);
        $this->assertNull($paginator->nextPageUrl());
    }

    public function testSimplePaginatorToArray(): void
    {
        $items = ['a', 'b'];
        $paginator = new Paginator($items, 10, 2, ['path' => '/items'], true);
        $array = $paginator->toArray();

        $this->assertEquals(2, $array['current_page']);
        $this->assertEquals($items, $array['data']);
        $this->assertEquals(10, $array['per_page']);
    }

    // =========================================================================
    // CursorPaginator（游标分页器）测试
    // =========================================================================

    public function testCursorPaginatorCreation(): void
    {
        $items = ['a', 'b', 'c'];
        $paginator = new CursorPaginator($items, 10, null, 'eyJpZCI6M30=');

        $this->assertEquals($items, $paginator->items());
        $this->assertEquals(10, $paginator->perPage());
        $this->assertNull($paginator->cursor());
        $this->assertEquals('eyJpZCI6M30=', $paginator->nextCursor());
        $this->assertTrue($paginator->hasMorePages());
    }

    public function testCursorPaginatorNoMorePages(): void
    {
        $paginator = new CursorPaginator(['a'], 10, null, null);
        $this->assertFalse($paginator->hasMorePages());
    }

    public function testCursorPaginatorNextPageUrl(): void
    {
        $paginator = new CursorPaginator(['a'], 10, null, 'abc123', ['path' => '/items']);

        $this->assertEquals('/items?cursor=abc123', $paginator->nextPageUrl());
    }

    public function testCursorPaginatorNoNextPage(): void
    {
        $paginator = new CursorPaginator(['a'], 10, null, null, ['path' => '/items']);
        $this->assertNull($paginator->nextPageUrl());
    }

    public function testCursorPaginatorPreviousPageAlwaysNull(): void
    {
        $paginator = new CursorPaginator(['a'], 10, 'xyz', 'abc');
        $this->assertNull($paginator->previousPageUrl());
    }

    public function testCursorPaginatorFirstPageUrl(): void
    {
        $paginator = new CursorPaginator(['a'], 10, 'xyz', null, ['path' => '/items']);
        $this->assertEquals('/items', $paginator->firstPageUrl());
    }

    public function testCursorPaginatorToArray(): void
    {
        $items = ['a', 'b'];
        $paginator = new CursorPaginator($items, 10, 'cur1', 'cur2', ['path' => '/items']);
        $array = $paginator->toArray();

        $this->assertEquals($items, $array['data']);
        $this->assertEquals(10, $array['per_page']);
        $this->assertEquals('cur2', $array['next_cursor']);
        // 阶段12 起 prev_cursor 为真实的上一页游标（原为当前游标的错误语义）
        $this->assertNull($array['prev_cursor']);

        $withPrev = new CursorPaginator($items, 10, 'cur1', 'cur2', ['path' => '/items', 'previousCursor' => 'cur0']);
        $this->assertEquals('cur0', $withPrev->toArray()['prev_cursor']);
        $this->assertEquals('/items?cursor=cur0', $withPrev->previousPageUrl());
    }

    // =========================================================================
    // QueryBuilder 分页方法测试
    // =========================================================================

    public function testQueryBuilderForPage(): void
    {
        $results = $this->queryBuilder->forPage(1, 10)->get();

        $this->assertCount(10, $results);
        $this->assertEquals('User 1', $results[0]['name']);
    }

    public function testQueryBuilderForPageSecondPage(): void
    {
        $results = $this->queryBuilder->forPage(2, 10)->get();

        $this->assertCount(10, $results);
        $this->assertEquals('User 11', $results[0]['name']);
    }

    public function testQueryBuilderForPageLastPage(): void
    {
        $results = $this->queryBuilder->forPage(3, 10)->get();

        $this->assertCount(5, $results);
        $this->assertEquals('User 21', $results[0]['name']);
    }

    public function testQueryBuilderPaginate(): void
    {
        $paginator = $this->queryBuilder->paginate(10);

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertCount(10, $paginator->items());
        $this->assertEquals(25, $paginator->total());
        $this->assertEquals(10, $paginator->perPage());
        $this->assertEquals(1, $paginator->currentPage());
        $this->assertEquals(3, $paginator->lastPage());
    }

    public function testQueryBuilderPaginateSecondPage(): void
    {
        $paginator = $this->queryBuilder->paginate(10, ['*'], 'page', 2);

        $this->assertCount(10, $paginator->items());
        $this->assertEquals('User 11', $paginator->items()[0]['name']);
        $this->assertEquals(2, $paginator->currentPage());
    }

    public function testQueryBuilderPaginateLastPage(): void
    {
        $paginator = $this->queryBuilder->paginate(10, ['*'], 'page', 3);

        $this->assertCount(5, $paginator->items());
        $this->assertEquals('User 21', $paginator->items()[0]['name']);
        $this->assertTrue($paginator->onLastPage());
    }

    public function testQueryBuilderSimplePaginate(): void
    {
        $paginator = $this->queryBuilder->simplePaginate(10);

        $this->assertInstanceOf(Paginator::class, $paginator);
        $this->assertCount(10, $paginator->items());
        $this->assertTrue($paginator->hasMorePages());
    }

    public function testQueryBuilderSimplePaginateLastPage(): void
    {
        $paginator = $this->queryBuilder->simplePaginate(10, ['*'], 'page', 3);

        $this->assertCount(5, $paginator->items());
        $this->assertFalse($paginator->hasMorePages());
    }

    public function testQueryBuilderCursorPaginate(): void
    {
        $paginator = $this->queryBuilder->cursorPaginate(10);

        $this->assertInstanceOf(CursorPaginator::class, $paginator);
        $this->assertCount(10, $paginator->items());
        $this->assertTrue($paginator->hasMorePages());
        $this->assertNotNull($paginator->nextCursor());
    }

    public function testQueryBuilderCursorPaginateWithCursor(): void
    {
        // 获取第一页
        $firstPage = $this->queryBuilder->cursorPaginate(10);
        $cursor = $firstPage->nextCursor();

        // 使用游标获取第二页
        $queryBuilder = new QueryBuilder($this->connection);
        $queryBuilder->from('users');
        $secondPage = $queryBuilder->cursorPaginate(10, ['*'], 'cursor', $cursor);

        $this->assertCount(10, $secondPage->items());
        $this->assertEquals('User 11', $secondPage->items()[0]['name']);
    }

    public function testQueryBuilderPaginateWithWhere(): void
    {
        $paginator = $this->queryBuilder
            ->where('id', '<=', 15)
            ->paginate(5);

        $this->assertEquals(15, $paginator->total());
        $this->assertCount(5, $paginator->items());
    }

    public function testQueryBuilderPaginateWithOrder(): void
    {
        $paginator = $this->queryBuilder
            ->orderBy('id', 'desc')
            ->paginate(5);

        $this->assertEquals('User 25', $paginator->items()[0]['name']);
    }

    // =========================================================================
    // 辅助函数测试
    // =========================================================================

    public function testCreatePaginatorHelper(): void
    {
        $items = ['a', 'b', 'c'];
        $paginator = \create_paginator($items, 100, 10, 2);

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertEquals($items, $paginator->items());
        $this->assertEquals(100, $paginator->total());
    }

    public function testSimplePaginatorHelper(): void
    {
        $items = ['a', 'b'];
        $paginator = \simple_paginator($items, 10, 2, [], true);

        $this->assertInstanceOf(Paginator::class, $paginator);
        $this->assertTrue($paginator->hasMorePages());
    }

    public function testCursorPaginatorHelper(): void
    {
        $items = ['a', 'b'];
        $paginator = \cursor_paginator($items, 10, 'cur1', 'cur2');

        $this->assertInstanceOf(CursorPaginator::class, $paginator);
        $this->assertEquals('cur2', $paginator->nextCursor());
    }
}