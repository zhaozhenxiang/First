<?php

declare(strict_types=1);

namespace Tests;

use Bin\Database\Model;
use Bin\Database\Relations\HasMany;
use Bin\Database\QueryBuilder;
use Bin\Testing\TestCase;

class RelationshipQueryTest extends TestCase
{
    protected \PDO $pdo;

    public function setUp(): void
    {
        parent::setUp();

        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // 创建表
        $this->pdo->exec('CREATE TABLE rel_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL
        )');

        $this->pdo->exec('CREATE TABLE rel_posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            views INTEGER NOT NULL DEFAULT 0,
            published INTEGER NOT NULL DEFAULT 0,
            FOREIGN KEY (user_id) REFERENCES rel_users(id)
        )');

        $this->pdo->exec('CREATE TABLE rel_comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id INTEGER NOT NULL,
            content TEXT NOT NULL,
            FOREIGN KEY (post_id) REFERENCES rel_posts(id)
        )');

        // 插入数据
        $this->pdo->exec("INSERT INTO rel_users (name, email) VALUES ('Alice', 'alice@test.com')");
        $this->pdo->exec("INSERT INTO rel_users (name, email) VALUES ('Bob', 'bob@test.com')");
        $this->pdo->exec("INSERT INTO rel_users (name, email) VALUES ('Charlie', 'charlie@test.com')");

        // Alice 的文章
        $this->pdo->exec("INSERT INTO rel_posts (user_id, title, views, published) VALUES (1, 'Post A', 100, 1)");
        $this->pdo->exec("INSERT INTO rel_posts (user_id, title, views, published) VALUES (1, 'Post B', 200, 1)");
        // Bob 的文章
        $this->pdo->exec("INSERT INTO rel_posts (user_id, title, views, published) VALUES (2, 'Post C', 50, 0)");
        // Charlie 没有文章

        RelTestUser::setConnection($this->pdo);
        RelTestPost::setConnection($this->pdo);
    }

    public function tearDown(): void
    {
        RelTestUser::setConnection(null);
        RelTestPost::setConnection(null);
        parent::tearDown();
    }

    // === 基本关系加载 ===

    public function testEagerLoadRelation(): void
    {
        $users = RelTestUser::with('posts')->get();
        $this->assertEquals(3, $users->count());
        // Alice 应该有 2 篇文章
        $alice = $users->first(fn($u) => $u->name === 'Alice');
        $this->assertNotNull($alice);
        $posts = $alice->getRelation('posts');
        $this->assertCount(2, $posts);
    }

    public function testRelationWithConstraints(): void
    {
        $users = RelTestUser::with(['posts' => function (HasMany $query) {
            $query->where('published', 1);
        }])->get();

        $alice = $users->first(fn($u) => $u->name === 'Alice');
        $this->assertNotNull($alice);
        $posts = $alice->getRelation('posts');
        $this->assertCount(2, $posts); // Alice has 2 published posts
    }

    // === whereHas ===

    public function testWhereHasFiltersUsersWithPosts(): void
    {
        $users = RelTestUser::whereHas('posts')->get();
        // Alice 和 Bob 有文章
        $this->assertEquals(2, $users->count());
        $names = array_map(fn($u) => $u->name, $users->toArray());
        $this->assertContains('Alice', $names);
        $this->assertContains('Bob', $names);
    }

    public function testWhereHasWithCallback(): void
    {
        $users = RelTestUser::whereHas('posts', function (QueryBuilder $query) {
            $query->where('published', 1);
        })->get();
        // 只有 Alice 有发布的文章
        $this->assertEquals(1, $users->count());
        $this->assertEquals('Alice', $users->first()->name);
    }

    public function testWhereDoesntHave(): void
    {
        $users = RelTestUser::whereDoesntHave('posts')->get();
        // Charlie 没有文章
        $this->assertEquals(1, $users->count());
        $this->assertEquals('Charlie', $users->first()->name);
    }

    // === withCount ===

    public function testWithCountAddsCountAttribute(): void
    {
        $users = RelTestUser::withCount('posts')->get();
        $alice = $users->first(fn($u) => $u->name === 'Alice');
        $this->assertEquals(2, $alice->posts_count);

        $charlie = $users->first(fn($u) => $u->name === 'Charlie');
        $this->assertEquals(0, $charlie->posts_count);
    }

    public function testWithSumAggregates(): void
    {
        $users = RelTestUser::withSum('posts', 'views')->get();
        $alice = $users->first(fn($u) => $u->name === 'Alice');
        $this->assertEquals(300, $alice->posts_sum_views);
    }

    // === 关系查询链式调用 ===

    public function testWhereHasWithOtherConditions(): void
    {
        $users = RelTestUser::where('name', 'like', '%a%')
            ->whereHas('posts')
            ->get();
        // Alice 含 'a'，Bob 不含 'a'... 实际是 Alice(name含a) 和 Charlie(name含a/ie)
        // 但只有 Alice 有文章
        $this->assertEquals(1, $users->count());
        $this->assertEquals('Alice', $users->first()->name);
    }
}

// === 测试模型 ===

class RelTestUser extends Model
{
    protected string $table = 'rel_users';
    protected array $guarded = [];

    public function posts(): \Bin\Database\Relations\HasMany
    {
        return $this->hasMany(RelTestPost::class, 'user_id');
    }
}

class RelTestPost extends Model
{
    protected string $table = 'rel_posts';
    protected array $guarded = [];

    public function user(): \Bin\Database\Relations\BelongsTo
    {
        return $this->belongsTo(RelTestUser::class, 'user_id');
    }
}

return new RelationshipQueryTest();
