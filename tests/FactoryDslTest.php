<?php

declare(strict_types=1);

namespace Tests;

use Bin\Database\Collection;
use Bin\Database\Factory;
use Bin\Database\Model;
use Bin\Testing\TestCase;
use PDO;

/**
 * 工厂类 DSL 回归测试（阶段13）
 *
 * Model::factory() 流式代理：count/state/sequence/for/has，
 * 定义解析：Factory::define 闭包 > Database\Factories\{X}Factory::definition()。
 */
class FactoryDslTest extends TestCase
{
    protected \PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE fct_users (id INTEGER PRIMARY KEY, name TEXT, role TEXT DEFAULT NULL, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE fct_posts (id INTEGER PRIMARY KEY, user_id INTEGER, author_id INTEGER, title TEXT, status TEXT, created_at TEXT, updated_at TEXT)');

        FctUser::resetBooted();
        FctPost::resetBooted();
        Model::setConnection($this->pdo);

        Factory::define(FctUser::class, fn () => ['name' => 'user-' . random_int(1, 999)]);
    }

    protected function tearDown(): void
    {
        Factory::flush();
        FctUser::flushEventListeners();
        FctPost::flushEventListeners();
        Model::setConnection(null);
    }

    public function testFactoryCreateSingleAndCounted(): void
    {
        $user = FctUser::factory()->create();

        $this->assertInstanceOf(FctUser::class, $user);
        $this->assertTrue($user->exists);
        $this->assertStringStartsWith('user-', $user->name);

        $users = FctUser::factory()->count(3)->create();
        $this->assertInstanceOf(Collection::class, $users);
        $this->assertSame(3, $users->count());
        $this->assertSame(4, FctUser::count());
    }

    public function testFactoryMakeDoesNotPersist(): void
    {
        $user = FctUser::factory()->make(['name' => 'draft']);

        $this->assertFalse($user->exists);
        $this->assertSame('draft', $user->name);
        $this->assertSame(0, FctUser::count());
    }

    public function testConventionFactoryClassResolution(): void
    {
        // FctPost 无 define 注册，回退 Database\Factories\FctPostFactory（见 tests stubs）
        require_once __DIR__ . '/stubs/FctPostFactory.php';

        $post = FctPost::factory()->create(['user_id' => 1]);

        $this->assertSame('untitled', $post->title);
        $this->assertSame('draft', $post->status);

        // 命名状态经工厂类 states() 方法
        $published = FctPost::factory()->state('published')->create(['user_id' => 1]);
        $this->assertSame('published', $published->status);
    }

    public function testClosureStateAndNamedRegistryState(): void
    {
        Factory::state(FctUser::class, 'admin', fn () => ['role' => 'admin']);

        $admin = FctUser::factory()->state('admin')->create();
        $this->assertSame('admin', $admin->role);

        $mod = FctUser::factory()->state(fn (array $attrs) => ['role' => 'mod'])->create();
        $this->assertSame('mod', $mod->role);
    }

    public function testSequenceCyclesAttributeSets(): void
    {
        $users = FctUser::factory()->count(3)->sequence(
            ['name' => 'alpha'],
            ['name' => 'beta'],
        )->make();

        $this->assertSame(['alpha', 'beta', 'alpha'], $users->map(fn ($u) => $u->name)->toArray());
    }

    public function testForWiresBelongsToForeignKey(): void
    {
        $user = FctUser::factory()->create();

        // 关系名 author() → 外键 author_id
        $post = FctPost::factory()->for($user, 'author')->create(['title' => 't']);

        $this->assertSame($user->id, $post->author_id);

        // 缺省关系名：snake(父类名)_id
        $post2 = FctPost::factory()->for($user)->create(['title' => 't2']);
        $this->assertSame($user->id, $post2->user_id);
    }

    public function testHasCreatesChildren(): void
    {
        $user = FctUser::factory()->has(
            FctPost::factory()->count(2)->state(fn () => ['status' => 'child']),
            'posts'
        )->create();

        $this->assertSame(2, FctPost::query()->where('user_id', $user->id)->count());
        $this->assertSame('child', FctPost::query()->where('user_id', $user->id)->first()->status);
    }

    public function testHasDefaultsToChildTableName(): void
    {
        $user = FctUser::factory()->has(FctPost::factory())->create();

        $this->assertSame(1, FctPost::query()->where('user_id', $user->id)->count());
    }

    public function testLegacyStaticApiStillWorks(): void
    {
        Factory::times(2, FctUser::class);

        $this->assertSame(2, FctUser::count());

        // makeTimes 不落库
        $made = Factory::makeTimes(2, FctUser::class);
        $this->assertSame(2, $made->count());
        $this->assertSame(2, FctUser::count());
    }
}

class FctUser extends Model
{
    protected string $table = 'fct_users';

    protected array $fillable = ['name', 'role'];

    public function posts()
    {
        return $this->hasMany(FctPost::class, 'user_id');
    }

    public function fct_posts()
    {
        return $this->hasMany(FctPost::class, 'user_id');
    }
}

class FctPost extends Model
{
    protected string $table = 'fct_posts';

    protected array $fillable = ['user_id', 'author_id', 'title', 'status'];

    public function author()
    {
        return $this->belongsTo(FctUser::class, 'author_id');
    }

    public function fctUser()
    {
        return $this->belongsTo(FctUser::class, 'user_id');
    }
}
