<?php

declare(strict_types=1);

namespace Tests;

use Bin\Database\Model;
use Bin\Database\Relations\MorphTo;
use Bin\Database\Relations\MorphOne;
use Bin\Database\Relations\MorphMany;
use Bin\Testing\TestCase;

class MorphToRelationTest extends TestCase
{
    protected \PDO $pdo;

    public function setUp(): void
    {
        parent::setUp();

        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE mt_posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL
        )');
        $this->pdo->exec('CREATE TABLE mt_videos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL
        )');
        $this->pdo->exec('CREATE TABLE mt_comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content TEXT NOT NULL,
            commentable_type TEXT,
            commentable_id INTEGER
        )');

        $this->pdo->exec("INSERT INTO mt_posts (title) VALUES ('First Post')");
        $this->pdo->exec("INSERT INTO mt_posts (title) VALUES ('Second Post')");
        $this->pdo->exec("INSERT INTO mt_videos (name) VALUES ('Video A')");
        $this->pdo->exec("INSERT INTO mt_comments (content, commentable_type, commentable_id) VALUES ('Great post', 'Tests\\MtPost', 1)");
        $this->pdo->exec("INSERT INTO mt_comments (content, commentable_type, commentable_id) VALUES ('Nice video', 'Tests\\MtVideo', 1)");
        $this->pdo->exec("INSERT INTO mt_comments (content, commentable_type, commentable_id) VALUES ('Another post comment', 'Tests\\MtPost', 2)");

        MtPost::setConnection($this->pdo);
        MtVideo::setConnection($this->pdo);
        MtComment::setConnection($this->pdo);
    }

    public function tearDown(): void
    {
        MtPost::setConnection(null);
        MtVideo::setConnection(null);
        MtComment::setConnection(null);
        parent::tearDown();
    }

    // === 基本延迟加载 ===

    public function testMorphToReturnsPost(): void
    {
        $comment = MtComment::find(1);
        $this->assertNotNull($comment);

        $parent = $comment->commentable();
        $this->assertNotNull($parent);
        $this->assertInstanceOf(MorphTo::class, $parent);

        $result = $parent->getResults();
        $this->assertNotNull($result);
        $this->assertEquals('First Post', $result->title);
    }

    public function testMorphToReturnsVideo(): void
    {
        $comment = MtComment::find(2);
        $parent = $comment->commentable()->getResults();
        $this->assertNotNull($parent);
        $this->assertEquals('Video A', $parent->name);
    }

    public function testMorphToReturnsNullForMissing(): void
    {
        $comment = new MtComment(['content' => 'orphan', 'commentable_type' => null, 'commentable_id' => null]);
        $result = $comment->commentable()->getResults();
        $this->assertNull($result);
    }

    // === Eager Loading ===

    public function testMorphToEagerLoading(): void
    {
        $comments = MtComment::with('commentable')->get();

        $this->assertEquals(3, $comments->count());

        // 第一个评论关联 Post
        $c1 = $comments->first(fn($c) => $c->content === 'Great post');
        $this->assertNotNull($c1);
        $this->assertNotNull($c1->getRelation('commentable'));
        $this->assertEquals('First Post', $c1->getRelation('commentable')->title);

        // 第二个评论关联 Video
        $c2 = $comments->first(fn($c) => $c->content === 'Nice video');
        $this->assertNotNull($c2);
        $this->assertNotNull($c2->getRelation('commentable'));
        $this->assertEquals('Video A', $c2->getRelation('commentable')->name);
    }

    // === 自定义列名 ===

    public function testMorphToCustomColumns(): void
    {
        // 创建带自定义列的表
        $this->pdo->exec('CREATE TABLE mt_custom_comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            body TEXT NOT NULL,
            parent_type TEXT,
            parent_id INTEGER
        )');
        $this->pdo->exec("INSERT INTO mt_custom_comments (body, parent_type, parent_id) VALUES ('custom', 'Tests\\MtPost', 1)");

        MtCustomComment::setConnection($this->pdo);

        $comment = MtCustomComment::find(1);
        $result = $comment->parent()->getResults();
        $this->assertNotNull($result);
        $this->assertEquals('First Post', $result->title);

        MtCustomComment::setConnection(null);
    }

    // === Morph Map ===

    public function testMorphMap(): void
    {
        Model::enforceMorphMap([
            'post' => MtPost::class,
            'video' => MtVideo::class,
        ]);

        // 更新 comment 使用短名
        $this->pdo->exec("UPDATE mt_comments SET commentable_type = 'post' WHERE id = 1");

        $comment = MtComment::find(1);
        $result = $comment->commentable()->getResults();
        $this->assertNotNull($result);
        $this->assertEquals('First Post', $result->title);

        // 清理
        Model::enforceMorphMap([], false);
    }

    // === Associate / Dissociate ===

    public function testAssociate(): void
    {
        $comment = new MtComment();
        $post = MtPost::find(1);

        $comment->commentable()->associate($post);

        $this->assertEquals(1, $comment->commentable_id);
        $this->assertEquals(MtPost::class, $comment->commentable_type);
    }

    public function testDissociate(): void
    {
        $comment = MtComment::find(1);
        $comment->commentable()->dissociate();

        $this->assertNull($comment->commentable_id);
        $this->assertNull($comment->commentable_type);
    }
}

// === 测试模型 ===

class MtPost extends Model
{
    protected string $table = 'mt_posts';
    protected array $guarded = [];
    protected bool $timestamps = false;

    public function comments(): MorphMany
    {
        return $this->morphMany(MtComment::class, 'commentable');
    }
}

class MtVideo extends Model
{
    protected string $table = 'mt_videos';
    protected array $guarded = [];
    protected bool $timestamps = false;

    public function comments(): MorphMany
    {
        return $this->morphMany(MtComment::class, 'commentable');
    }
}

class MtComment extends Model
{
    protected string $table = 'mt_comments';
    protected array $guarded = [];
    protected bool $timestamps = false;

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}

class MtCustomComment extends Model
{
    protected string $table = 'mt_custom_comments';
    protected array $guarded = [];
    protected bool $timestamps = false;

    public function parent(): MorphTo
    {
        return $this->morphTo('parent', 'parent_type', 'parent_id');
    }
}

return new MorphToRelationTest();
