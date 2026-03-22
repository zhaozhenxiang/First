<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Database\QueryBuilder;

class QueryBuilderTest extends TestCase
{
    private QueryBuilder $query;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建模拟 PDO 连接（避免实际数据库连接）
        $pdo = $this->createMockPDO();
        $this->query = new QueryBuilder($pdo);
    }

    /**
     * 创建模拟 PDO 对象
     */
    private function createMockPDO(): \PDO
    {
        // 使用 SQLite 内存数据库进行测试
        return new \PDO('sqlite::memory:');
    }

    public function testSelect(): void
    {
        $sql = $this->query->from('users')
            ->select(['id', 'name'])
            ->toSql();

        $this->assertStringContainsString('SELECT id, name', $sql);
        $this->assertStringContainsString('FROM users', $sql);
    }

    public function testWhere(): void
    {
        $sql = $this->query->from('users')
            ->where('status', 'active')
            ->toSql();

        $this->assertStringContainsString('WHERE status = ?', $sql);
    }

    public function testOrWhere(): void
    {
        $sql = $this->query->from('users')
            ->where('status', 'active')
            ->orWhere('role', 'admin')
            ->toSql();

        $this->assertStringContainsString('OR', $sql);
    }

    public function testWhereIn(): void
    {
        $sql = $this->query->from('users')
            ->whereIn('id', [1, 2, 3])
            ->toSql();

        $this->assertStringContainsString('IN', $sql);
    }

    public function testOrderBy(): void
    {
        $sql = $this->query->from('users')
            ->orderBy('created_at', 'desc')
            ->toSql();

        $this->assertStringContainsString('ORDER BY created_at desc', $sql);
    }

    public function testLimit(): void
    {
        $sql = $this->query->from('users')
            ->limit(10)
            ->toSql();

        $this->assertStringContainsString('LIMIT 10', $sql);
    }

    public function testOffset(): void
    {
        $sql = $this->query->from('users')
            ->offset(10)
            ->toSql();

        $this->assertStringContainsString('OFFSET 10', $sql);
    }

    public function testJoin(): void
    {
        $sql = $this->query->from('users')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->toSql();

        $this->assertStringContainsString('JOIN posts ON users.id = posts.user_id', $sql);
    }

    public function testLeftJoin(): void
    {
        $sql = $this->query->from('users')
            ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
            ->toSql();

        $this->assertStringContainsString('LEFT JOIN', $sql);
    }

    public function testCount(): void
    {
        $sql = $this->query->from('users')
            ->toSql();

        $this->assertStringContainsString('SELECT', $sql);
    }

    public function testComplexQuery(): void
    {
        $sql = $this->query->from('users')
            ->select(['users.id', 'users.name', 'posts.title'])
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->where('users.status', 'active')
            ->where('posts.published', true)
            ->orderBy('users.created_at', 'desc')
            ->limit(10)
            ->toSql();

        $this->assertStringContainsString('SELECT users.id, users.name, posts.title', $sql);
        $this->assertStringContainsString('FROM users', $sql);
        $this->assertStringContainsString('JOIN posts ON users.id = posts.user_id', $sql);
        $this->assertStringContainsString('WHERE users.status = ?', $sql);
        $this->assertStringContainsString('posts.published = ?', $sql);
        $this->assertStringContainsString('ORDER BY users.created_at desc', $sql);
        $this->assertStringContainsString('LIMIT 10', $sql);
    }
}
