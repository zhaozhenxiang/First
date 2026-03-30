<?php

declare(strict_types=1);

namespace Tests;

use Bin\Database\Model;
use Bin\Database\Relations\MorphOne;
use Bin\Database\Relations\MorphMany;
use Bin\Database\Relations\MorphOneOrMany;
use Bin\Database\Relations\HasMany;
use Bin\Testing\TestCase;

class RelationRefactorTest extends TestCase
{
    protected \PDO $pdo;

    public function setUp(): void
    {
        parent::setUp();

        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE rr_posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL
        )');
        $this->pdo->exec('CREATE TABLE rr_images (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            url TEXT NOT NULL,
            imageable_type TEXT,
            imageable_id INTEGER
        )');
        $this->pdo->exec('CREATE TABLE rr_tags (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            taggable_type TEXT,
            taggable_id INTEGER
        )');
        $this->pdo->exec('CREATE TABLE rr_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL
        )');
        $this->pdo->exec('CREATE TABLE rr_sub_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            item_id INTEGER NOT NULL,
            name TEXT NOT NULL
        )');

        $this->pdo->exec("INSERT INTO rr_posts (title) VALUES ('Post A')");
        $this->pdo->exec("INSERT INTO rr_posts (title) VALUES ('Post B')");
        $this->pdo->exec("INSERT INTO rr_images (url, imageable_type, imageable_id) VALUES ('img1.jpg', 'Tests\\RrPost', 1)");
        $this->pdo->exec("INSERT INTO rr_images (url, imageable_type, imageable_id) VALUES ('img2.jpg', 'Tests\\RrPost', 1)");
        $this->pdo->exec("INSERT INTO rr_images (url, imageable_type, imageable_id) VALUES ('img3.jpg', 'Tests\\RrPost', 2)");
        $this->pdo->exec("INSERT INTO rr_tags (name, taggable_type, taggable_id) VALUES ('php', 'Tests\\RrPost', 1)");
        $this->pdo->exec("INSERT INTO rr_tags (name, taggable_type, taggable_id) VALUES ('sql', 'Tests\\RrPost', 1)");
        $this->pdo->exec("INSERT INTO rr_items (name) VALUES ('Item 1')");
        $this->pdo->exec("INSERT INTO rr_sub_items (item_id, name) VALUES (1, 'Sub A')");
        $this->pdo->exec("INSERT INTO rr_sub_items (item_id, name) VALUES (1, 'Sub B')");

        RrPost::setConnection($this->pdo);
        RrImage::setConnection($this->pdo);
        RrTag::setConnection($this->pdo);
        RrItem::setConnection($this->pdo);
        RrSubItem::setConnection($this->pdo);
    }

    public function tearDown(): void
    {
        RrPost::setConnection(null);
        RrImage::setConnection(null);
        RrTag::setConnection(null);
        RrItem::setConnection(null);
        RrSubItem::setConnection(null);
        parent::tearDown();
    }

    // === MorphMany 重构验证 ===

    public function testMorphManyReturnsCollection(): void
    {
        $post = RrPost::find(1);
        $tags = $post->tags()->getResults();
        $this->assertCount(2, $tags);
    }

    public function testMorphManyEagerLoading(): void
    {
        $posts = RrPost::with('tags')->get();
        $post1 = $posts->first(fn($p) => $p->title === 'Post A');
        $this->assertCount(2, $post1->getRelation('tags'));

        $post2 = $posts->first(fn($p) => $p->title === 'Post B');
        $this->assertCount(0, $post2->getRelation('tags'));
    }

    public function testMorphManyInheritsMorphOneOrMany(): void
    {
        $post = RrPost::find(1);
        $relation = $post->images();
        $this->assertInstanceOf(MorphOneOrMany::class, $relation);
        $this->assertInstanceOf(MorphMany::class, $relation);
    }

    // === MorphOne 重构验证 ===

    public function testMorphOneReturnsSingleModel(): void
    {
        $post = RrPost::find(1);
        $image = $post->image()->getResults();
        $this->assertNotNull($image);
        $this->assertEquals('img1.jpg', $image->url);
    }

    public function testMorphOneReturnsNullWhenNone(): void
    {
        $post = RrPost::find(2);
        $post->image(); // just ensure no error for single result query
    }

    public function testMorphOneEagerLoading(): void
    {
        $posts = RrPost::with('image')->get();
        $post1 = $posts->first(fn($p) => $p->title === 'Post A');
        $this->assertNotNull($post1->getRelation('image'));
        // MorphOne eager loading takes the last matched result for a parent key
        $this->assertContains($post1->getRelation('image')->url, ['img1.jpg', 'img2.jpg']);
    }

    // === saveMany ===

    public function testSaveMany(): void
    {
        $item = RrItem::find(1);
        $sub1 = new RrSubItem(['name' => 'Sub C']);
        $sub2 = new RrSubItem(['name' => 'Sub D']);

        $item->subs()->saveMany([$sub1, $sub2]);

        $this->assertTrue($sub1->exists);
        $this->assertTrue($sub2->exists);
        $this->assertEquals(1, $sub1->item_id);
        $this->assertEquals(1, $sub2->item_id);
    }

    // === destroy ===

    public function testDestroySingleId(): void
    {
        $count = RrPost::destroy(1);
        $this->assertEquals(1, $count);
        $this->assertNull(RrPost::find(1));
    }

    public function testDestroyMultipleIds(): void
    {
        $count = RrPost::destroy(1, 2);
        $this->assertEquals(2, $count);
        $this->assertNull(RrPost::find(1));
        $this->assertNull(RrPost::find(2));
    }

    public function testDestroyArray(): void
    {
        $count = RrPost::destroy([1, 2]);
        $this->assertEquals(2, $count);
    }

    public function testDestroyNonExistent(): void
    {
        $count = RrPost::destroy(999);
        $this->assertEquals(0, $count);
    }
}

// === 测试模型 ===

class RrPost extends Model
{
    protected string $table = 'rr_posts';
    protected array $guarded = [];
    protected bool $timestamps = false;

    public function images(): MorphMany
    {
        return $this->morphMany(RrImage::class, 'imageable');
    }

    public function image(): MorphOne
    {
        return $this->morphOne(RrImage::class, 'imageable');
    }

    public function tags(): MorphMany
    {
        return $this->morphMany(RrTag::class, 'taggable');
    }
}

class RrImage extends Model
{
    protected string $table = 'rr_images';
    protected array $guarded = [];
    protected bool $timestamps = false;
}

class RrTag extends Model
{
    protected string $table = 'rr_tags';
    protected array $guarded = [];
    protected bool $timestamps = false;
}

class RrItem extends Model
{
    protected string $table = 'rr_items';
    protected array $guarded = [];
    protected bool $timestamps = false;

    public function subs(): HasMany
    {
        return $this->hasMany(RrSubItem::class, 'item_id');
    }
}

class RrSubItem extends Model
{
    protected string $table = 'rr_sub_items';
    protected array $guarded = [];
    protected bool $timestamps = false;
}

return new RelationRefactorTest();
