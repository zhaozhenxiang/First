<?php

declare(strict_types=1);

namespace Tests;

use Bin\Database\Model;
use Bin\Testing\TestCase;

class LoadAggregateTest extends TestCase
{
    protected \PDO $pdo;

    public function setUp(): void
    {
        parent::setUp();

        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE la_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL
        )');
        $this->pdo->exec('CREATE TABLE la_posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            views INTEGER NOT NULL DEFAULT 0
        )');

        $this->pdo->exec("INSERT INTO la_users (name) VALUES ('Alice')");
        $this->pdo->exec("INSERT INTO la_users (name) VALUES ('Bob')");
        $this->pdo->exec("INSERT INTO la_posts (user_id, title, views) VALUES (1, 'Post A', 100)");
        $this->pdo->exec("INSERT INTO la_posts (user_id, title, views) VALUES (1, 'Post B', 200)");
        $this->pdo->exec("INSERT INTO la_posts (user_id, title, views) VALUES (2, 'Post C', 50)");

        LaUser::setConnection($this->pdo);
        LaPost::setConnection($this->pdo);
    }

    public function tearDown(): void
    {
        LaUser::setConnection(null);
        LaPost::setConnection(null);
        parent::tearDown();
    }

    // === loadCount ===

    public function testLoadCount(): void
    {
        $user = LaUser::find(1);
        $this->assertFalse($user->hasAttribute('posts_count'));

        $user->loadCount('posts');

        $this->assertTrue($user->hasAttribute('posts_count'));
        $this->assertEquals(2, $user->posts_count);
    }

    public function testLoadCountZero(): void
    {
        $this->pdo->exec("DELETE FROM la_posts WHERE user_id = 2");
        $user = LaUser::find(2);

        $user->loadCount('posts');
        $this->assertEquals(0, $user->posts_count);
    }

    // === loadSum ===

    public function testLoadSum(): void
    {
        $user = LaUser::find(1);
        $user->loadSum('posts', 'views');

        $this->assertTrue($user->hasAttribute('posts_views_sum'));
        $this->assertEquals(300, $user->posts_views_sum);
    }

    public function testLoadSumZero(): void
    {
        $this->pdo->exec("DELETE FROM la_posts WHERE user_id = 2");
        $user = LaUser::find(2);
        $user->loadSum('posts', 'views');

        $this->assertEquals(0, $user->posts_views_sum);
    }
}

// === 测试模型 ===

class LaUser extends Model
{
    protected string $table = 'la_users';
    protected array $guarded = [];
    protected bool $timestamps = false;

    public function posts(): \Bin\Database\Relations\HasMany
    {
        return $this->hasMany(LaPost::class, 'user_id');
    }
}

class LaPost extends Model
{
    protected string $table = 'la_posts';
    protected array $guarded = [];
}

return new LoadAggregateTest();
