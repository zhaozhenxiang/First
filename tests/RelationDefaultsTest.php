<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Database\Model;

/**
 * belongsTo 默认外键回归测试
 *
 * 此前默认外键取当前模型类名（Post::user() 得到 post_id），
 * 且外键为 null 时不加约束、返回全表第一行。
 */
class RelationDefaultsTest extends TestCase
{
    protected \PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE rd_users (id INTEGER PRIMARY KEY, name TEXT)');
        $this->pdo->exec('CREATE TABLE rd_posts (id INTEGER PRIMARY KEY, user_id INTEGER, title TEXT)');
        $this->pdo->exec("INSERT INTO rd_users (name) VALUES ('alice'), ('bob')");
        $this->pdo->exec("INSERT INTO rd_posts (user_id, title) VALUES (1, 'p1'), (2, 'p2'), (NULL, 'p3')");

        RdUser::resetBooted();
        RdPost::resetBooted();
        Model::setConnection($this->pdo);
    }

    protected function tearDown(): void
    {
        Model::setConnection(null);
    }

    public function testBelongsToDefaultForeignKeyUsesRelationName(): void
    {
        // user() 不传外键 → user_id（而不是当前模型类名的 post_id）
        $relation = (function () {
            return $this->user();
        })->call(new RdPost());

        $this->assertSame('user_id', $relation->getForeignKey());

        // 显式外键仍然优先
        $explicit = (function () {
            return $this->author();
        })->call(new RdPost());
        $this->assertSame('author_id', $explicit->getForeignKey());
    }

    public function testBelongsToResolvesParentByRelationForeignKey(): void
    {
        // user() → user_id，应关联到 id=2 的 bob
        $post = RdPost::find(2);
        $this->assertSame('bob', $post->user->name);
    }

    public function testBelongsToWithNullForeignKeyReturnsNull(): void
    {
        // user_id 为 NULL 时绝不能返回"第一行"
        $post = RdPost::find(3);
        $this->assertNull($post->user);

        // eager 加载同样为 null
        $posts = RdPost::with('user')->get();
        $this->assertNull($posts[2]->user);
    }

    public function testBelongsToEagerLoadMatchesCorrectParents(): void
    {
        $posts = RdPost::whereNotNull('user_id')->with('user')->get();

        $this->assertSame('alice', $posts[0]->user->name);
        $this->assertSame('bob', $posts[1]->user->name);
    }
}

class RdUser extends Model
{
    protected string $table = 'rd_users';
}

class RdPost extends Model
{
    protected string $table = 'rd_posts';

    protected array $fillable = ['user_id', 'title'];

    public function user()
    {
        return $this->belongsTo(RdUser::class);
    }

    public function author()
    {
        return $this->belongsTo(RdUser::class, 'author_id');
    }
}
