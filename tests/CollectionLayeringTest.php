<?php

declare(strict_types=1);

namespace Tests;

use Bin\Database\Collection as EloquentCollection;
use Bin\Database\Model;
use Bin\Support\Collection as BaseCollection;
use Bin\Testing\TestCase;

/**
 * Collection 双层拆分回归测试（阶段9A）
 *
 * 结构：Bin\Support\Collection（通用层）← Bin\Database\Collection（模型集合层）。
 * 既有调用点全部经由 Bin\Database\Collection 使用（继承兼容，零破坏）。
 */
class CollectionLayeringTest extends TestCase
{
    protected \PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE layer_users (id INTEGER PRIMARY KEY, name TEXT, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE layer_posts (id INTEGER PRIMARY KEY, user_id INTEGER, title TEXT, created_at TEXT, updated_at TEXT)');

        LayerUser::resetBooted();
        LayerPost::resetBooted();
        Model::setConnection($this->pdo);
    }

    protected function tearDown(): void
    {
        LayerUser::flushEventListeners();
        LayerPost::flushEventListeners();
        Model::setConnection(null);
    }

    public function testBaseCollectionWorksStandalone(): void
    {
        $collection = BaseCollection::make([3, 1, 2])->filter(fn ($v) => $v > 1)->sort();

        $this->assertSame([2, 3], $collection->all());
        $this->assertInstanceOf(BaseCollection::class, $collection);
    }

    public function testEloquentCollectionExtendsBase(): void
    {
        $collection = EloquentCollection::make([1, 2, 3]);

        $this->assertInstanceOf(BaseCollection::class, $collection);
        // 子类 make/map/filter 返回子类（new static 语义）
        $this->assertInstanceOf(EloquentCollection::class, $collection->map(fn ($v) => $v));
        $this->assertInstanceOf(EloquentCollection::class, $collection->filter(fn ($v) => true));
    }

    public function testBaseMakeReturnsBaseClass(): void
    {
        $this->assertInstanceOf(BaseCollection::class, BaseCollection::make([]));
        $this->assertFalse(BaseCollection::make([]) instanceof EloquentCollection);
    }

    public function testOrmQueriesStillReturnEloquentCollection(): void
    {
        LayerUser::create(['name' => 'u1']);
        LayerUser::create(['name' => 'u2']);

        $this->assertInstanceOf(EloquentCollection::class, LayerUser::all());
        $this->assertInstanceOf(EloquentCollection::class, LayerUser::query()->get());
        $this->assertInstanceOf(EloquentCollection::class, LayerUser::findMany([1, 2]));
    }

    public function testModelKeysAndFind(): void
    {
        $u1 = LayerUser::create(['name' => 'u1']);
        $u2 = LayerUser::create(['name' => 'u2']);
        $users = LayerUser::all();

        $this->assertSame([$u1->id, $u2->id], $users->modelKeys());

        $this->assertSame($u2->id, $users->find(2)->id);
        $this->assertSame($u1->id, $users->find('u1', 'name')->id);
        $this->assertNull($users->find(99));
    }

    public function testCollectionLoadAndLoadCount(): void
    {
        $user = LayerUser::create(['name' => 'u1']);
        $user->posts()->create(['title' => 'p1']);
        $user->posts()->create(['title' => 'p2']);

        $users = LayerUser::all();

        $this->assertFalse($users->first()->relationLoaded('posts'));

        $users->load('posts');

        $this->assertTrue($users->first()->relationLoaded('posts'));
        $this->assertSame(2, $users->first()->posts->count());

        $users->loadCount('posts');
        $this->assertSame(2, (int) $users->first()->posts_count);
    }
}

class LayerUser extends Model
{
    protected string $table = 'layer_users';

    protected array $fillable = ['name'];

    public function posts()
    {
        return $this->hasMany(LayerPost::class, 'user_id');
    }
}

class LayerPost extends Model
{
    protected string $table = 'layer_posts';

    protected array $fillable = ['user_id', 'title'];
}
