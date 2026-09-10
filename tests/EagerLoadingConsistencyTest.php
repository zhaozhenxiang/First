<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Database\Collection;
use Bin\Database\Model;

/**
 * Eager 加载一致性回归测试
 *
 * 此前懒加载返回 Collection、eager 加载返回裸数组；isset($model->relation)
 * 恒为 false；嵌套 with() 的第二层按顶层模型逐条查询（N+1）。
 */
class EagerLoadingConsistencyTest extends TestCase
{
    protected CountingPDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new CountingPDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE el_users (id INTEGER PRIMARY KEY, name TEXT)');
        $this->pdo->exec('CREATE TABLE el_posts (id INTEGER PRIMARY KEY, user_id INTEGER, title TEXT)');
        $this->pdo->exec('CREATE TABLE el_comments (id INTEGER PRIMARY KEY, post_id INTEGER, body TEXT)');

        foreach ([1, 2, 3, 4] as $i) {
            $this->pdo->exec("INSERT INTO el_users (name) VALUES ('u{$i}')");
            $this->pdo->exec("INSERT INTO el_posts (user_id, title) VALUES ({$i}, 'p{$i}')");
            $this->pdo->exec("INSERT INTO el_comments (post_id, body) VALUES ({$i}, 'c1'), ({$i}, 'c2')");
        }

        ElUser::resetBooted();
        ElPost::resetBooted();
        Model::setConnection($this->pdo);
    }

    protected function tearDown(): void
    {
        Model::setConnection(null);
    }

    public function testLazyAndEagerLoadingReturnSameType(): void
    {
        $lazy = ElUser::find(1)->posts;
        $eager = ElUser::with('posts')->get()->first()->posts;

        $this->assertInstanceOf(Collection::class, $lazy);
        $this->assertInstanceOf(Collection::class, $eager);

        // Collection 方法在两种加载方式下都可用
        $this->assertCount(1, $eager);
        $this->assertSame(['p1'], $eager->pluck('title'));
    }

    public function testIssetRecognizesLoadedRelation(): void
    {
        $user = ElUser::with('posts')->get()->first();

        $this->assertTrue(isset($user->posts));
        $this->assertFalse(isset($user->undefined_relation_loaded));
    }

    public function testNestedEagerLoadingUsesConstantQueryCount(): void
    {
        CountingPDO::$statements = 0;

        $users = ElUser::with('posts.comments')->get();

        // 4 个用户、4 篇文章：恒定 3 条 SQL（1 主查询 + 1 posts + 1 comments），
        // 与父模型数量无关——此前为 1 + K（K 个顶层模型）的 N+1
        $this->assertCount(4, $users);
        $this->assertSame(3, CountingPDO::$statements);

        // 关系确实已加载
        $this->assertCount(2, $users[0]->posts[0]->comments);
    }

    public function testEagerLoadCountMatchesLazyCount(): void
    {
        // MorphTo 一类单模型关系不受影响；hasMany eager 数量与懒加载一致
        $lazyCount = count(ElUser::find(2)->posts);
        $eagerCount = ElUser::with('posts')->get()[1]->posts->count();

        $this->assertSame($lazyCount, $eagerCount);
    }
}

class ElUser extends Model
{
    protected string $table = 'el_users';

    public function posts()
    {
        return $this->hasMany(ElPost::class, 'user_id');
    }
}

class ElPost extends Model
{
    protected string $table = 'el_posts';

    public function comments()
    {
        return $this->hasMany(ElComment::class, 'post_id');
    }
}

class ElComment extends Model
{
    protected string $table = 'el_comments';
}

class CountingPDO extends \PDO
{
    public static int $statements = 0;

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        self::$statements++;

        return parent::prepare($query, $options);
    }
}
