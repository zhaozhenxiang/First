<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Database\QueryBuilder;

/**
 * SQL 编译回归测试
 *
 * 覆盖此前全部零覆盖的编译路径：HAVING 首条件、UNION 绑定、空 whereIn、
 * 操作符白名单、where('col','=',null) 与标识符包裹。
 */
class QueryCompilerRegressionTest extends TestCase
{
    protected \PDO $pdo;

    protected QueryBuilder $query;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->query = new QueryBuilder($this->pdo);
    }

    protected function tearDown(): void
    {
        unset($this->query, $this->pdo);
    }

    public function testHavingFirstConditionHasNoBooleanPrefix(): void
    {
        $sql = $this->query->from('orders')
            ->groupBy('status')
            ->having('total', '>', 100)
            ->toSql();

        // 首条件不能再带 and/or 前缀（原产出 "HAVING and total > ?" 非法 SQL）
        $this->assertStringContainsString('HAVING `total` > ?', $sql);
        $this->assertStringNotContainsString('HAVING and', strtolower($sql));

        // 第二个条件保留 OR 连接
        $sql2 = $this->query->from('orders')
            ->groupBy('status')
            ->having('total', '>', 100)
            ->orHavingRaw('COUNT(*) > 10')
            ->toSql();

        $this->assertMatchesRegularExpression('/HAVING .+ OR COUNT\(\*\) > 10/', $sql2);
    }

    public function testUnionMergesSubqueryBindings(): void
    {
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $this->pdo->exec("INSERT INTO users (name) VALUES ('a'), ('x')");

        $q = (new QueryBuilder($this->pdo))->from('users')->where('id', 1);
        $q->union(function ($sub) {
            $sub->from('users')->where('name', 'x');
        });

        // 两个占位符必须有两个绑定
        $this->assertCount(2, $q->getBindings());

        // 真实执行不再报绑定数不匹配
        $results = $q->get();
        $this->assertCount(2, $results);
    }

    public function testWhereInWithEmptyArrayCompilesToFalseCondition(): void
    {
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');

        $results = (new QueryBuilder($this->pdo))->from('users')->whereIn('id', [])->get();
        $this->assertCount(0, $results);

        // whereNotIn 空数组 = 恒真
        $this->pdo->exec('INSERT INTO users (id) VALUES (1)');
        $all = (new QueryBuilder($this->pdo))->from('users')->whereNotIn('id', [])->get();
        $this->assertCount(1, $all);
    }

    public function testWhereWithNullValueCompilesToIsNull(): void
    {
        $sql = $this->query->from('users')->where('deleted_at', '=', null)->toSql();

        $this->assertStringContainsString('`deleted_at` IS NULL', $sql);
        // '=' 不能被误当成绑定值
        $this->assertNotContains('=', $this->query->getBindings());

        $sql2 = $this->query->from('users')->where('active', '<>', null)->toSql();
        $this->assertStringContainsString('`active` IS NOT NULL', $sql2);
    }

    public function testWhereRejectsIllegalOperator(): void
    {
        $this->assertThrows(\InvalidArgumentException::class, function () {
            $this->query->from('users')->where('id', '= 1 OR 1=1 --', 5);
        });

        $this->assertThrows(\InvalidArgumentException::class, function () {
            $this->query->from('users')->orderBy('id', 'asc; DROP TABLE users');
        });
    }

    public function testIdentifiersAreWrappedWithBackticks(): void
    {
        $this->pdo->exec('CREATE TABLE "order" (id INTEGER PRIMARY KEY, "key" TEXT)');

        // 保留字表名/列名在包裹后可用
        $count = (new QueryBuilder($this->pdo))->from('order')->where('key', '=', 'a')->count();
        $this->assertSame(0, $count);
    }

    public function testAggregateKeepsJoins(): void
    {
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        $this->pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY, user_id INTEGER)');
        $this->pdo->exec('INSERT INTO users (id) VALUES (1)');
        $this->pdo->exec('INSERT INTO posts (user_id) VALUES (1), (1)');

        // join 不再被聚合丢弃
        $count = (new QueryBuilder($this->pdo))->from('users')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->count();

        $this->assertSame(2, $count);
    }

    public function testAggregateDoesNotFireRetrievedEvents(): void
    {
        // count() 不应水合伪造模型并触发 retrieved 事件
        \Bin\Database\Model::setConnection($this->pdo);

        $fired = 0;
        AggCountUser::retrieved(function () use (&$fired) {
            $fired++;
        });

        $this->pdo->exec('CREATE TABLE agg_users (id INTEGER PRIMARY KEY)');
        AggCountUser::query();
        AggCountUser::count();

        $this->assertSame(0, $fired);

        \Bin\Database\Model::setConnection(null);
    }
}

class AggCountUser extends \Bin\Database\Model
{
    protected string $table = 'agg_users';
}
