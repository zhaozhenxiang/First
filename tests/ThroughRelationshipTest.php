<?php

declare(strict_types=1);

namespace Tests;

use Bin\Database\Model;
use Bin\Database\Relations\HasManyThrough;
use Bin\Database\Relations\HasOneThrough;
use Bin\Testing\TestCase;

class ThroughRelationshipTest extends TestCase
{
    protected \PDO $pdo;

    public function setUp(): void
    {
        parent::setUp();

        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // countries
        $this->pdo->exec('CREATE TABLE through_countries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL
        )');

        // users (中间表)
        $this->pdo->exec('CREATE TABLE through_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            country_id INTEGER NOT NULL,
            name TEXT NOT NULL
        )');

        // posts (远层表)
        $this->pdo->exec('CREATE TABLE through_posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL
        )');

        // history (远层一对一)
        $this->pdo->exec('CREATE TABLE through_user_histories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            info TEXT NOT NULL
        )');

        // 数据
        $this->pdo->exec("INSERT INTO through_countries (name) VALUES ('China')");
        $this->pdo->exec("INSERT INTO through_countries (name) VALUES ('USA')");

        $this->pdo->exec("INSERT INTO through_users (country_id, name) VALUES (1, 'Alice')");
        $this->pdo->exec("INSERT INTO through_users (country_id, name) VALUES (1, 'Bob')");
        $this->pdo->exec("INSERT INTO through_users (country_id, name) VALUES (2, 'Charlie')");

        $this->pdo->exec("INSERT INTO through_posts (user_id, title) VALUES (1, 'Post A')");
        $this->pdo->exec("INSERT INTO through_posts (user_id, title) VALUES (1, 'Post B')");
        $this->pdo->exec("INSERT INTO through_posts (user_id, title) VALUES (2, 'Post C')");
        $this->pdo->exec("INSERT INTO through_posts (user_id, title) VALUES (3, 'Post D')");

        $this->pdo->exec("INSERT INTO through_user_histories (user_id, info) VALUES (1, 'Alice history')");

        ThroughCountry::setConnection($this->pdo);
        ThroughUser::setConnection($this->pdo);
        ThroughPost::setConnection($this->pdo);
        ThroughUserHistory::setConnection($this->pdo);
    }

    public function tearDown(): void
    {
        ThroughCountry::setConnection(null);
        ThroughUser::setConnection(null);
        ThroughPost::setConnection(null);
        ThroughUserHistory::setConnection(null);
        parent::tearDown();
    }

    // === HasManyThrough ===

    public function testHasManyThroughBasic(): void
    {
        $china = ThroughCountry::where('name', 'China')->first();
        $this->assertNotNull($china);

        $posts = $china->posts();
        $this->assertInstanceOf(HasManyThrough::class, $posts);

        $results = $posts->getResults();
        $this->assertCount(3, $results); // Alice(2) + Bob(1)
    }

    public function testHasManyThroughEagerLoad(): void
    {
        $countries = ThroughCountry::with('posts')->get();
        $this->assertEquals(2, $countries->count());

        $china = $countries->first(fn($c) => $c->name === 'China');
        $posts = $china->getRelation('posts');
        $this->assertCount(3, $posts);

        $usa = $countries->first(fn($c) => $c->name === 'USA');
        $posts = $usa->getRelation('posts');
        $this->assertCount(1, $posts);
    }

    public function testHasManyThroughWhereHas(): void
    {
        $countries = ThroughCountry::whereHas('posts')->get();
        $this->assertEquals(2, $countries->count());
    }

    // === HasOneThrough ===

    public function testHasOneThroughBasic(): void
    {
        $china = ThroughCountry::where('name', 'China')->first();
        $this->assertNotNull($china);

        $history = $china->userHistory();
        $this->assertInstanceOf(HasOneThrough::class, $history);

        $result = $history->getResults();
        $this->assertNotNull($result);
        $this->assertEquals('Alice history', $result->info);
    }

    public function testHasOneThroughReturnsNullWhenNoResult(): void
    {
        $usa = ThroughCountry::where('name', 'USA')->first();
        $this->assertNotNull($usa);

        $result = $usa->userHistory()->getResults();
        $this->assertNull($result);
    }
}

// === 测试模型 ===

class ThroughCountry extends Model
{
    protected string $table = 'through_countries';
    protected array $guarded = [];

    public function posts(): HasManyThrough
    {
        return $this->hasManyThrough(
            ThroughPost::class,
            ThroughUser::class,
            'country_id',
            'user_id',
            'id',
            'id'
        );
    }

    public function userHistory(): HasOneThrough
    {
        return $this->hasOneThrough(
            ThroughUserHistory::class,
            ThroughUser::class,
            'country_id',
            'user_id',
            'id',
            'id'
        );
    }
}

class ThroughUser extends Model
{
    protected string $table = 'through_users';
    protected array $guarded = [];
}

class ThroughPost extends Model
{
    protected string $table = 'through_posts';
    protected array $guarded = [];
}

class ThroughUserHistory extends Model
{
    protected string $table = 'through_user_histories';
    protected array $guarded = [];
}

return new ThroughRelationshipTest();
