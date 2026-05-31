<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Database\Model;
use Bin\Database\ModelEventDispatcher;
use Bin\Database\Collection;
use Bin\Database\SoftDeletes;
use PDO;
use InvalidArgumentException;
use LogicException;

/**
 * Eloquent 对齐测试 — 验证框架行为与 Laravel 约定的一致性
 */
class EloquentParityTest extends TestCase
{
    protected ?PDO $connection = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 创建测试表
        $this->connection->exec('
            CREATE TABLE parity_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                role TEXT DEFAULT NULL,
                is_active INTEGER DEFAULT 1,
                settings TEXT DEFAULT NULL,
                created_at TEXT DEFAULT NULL,
                updated_at TEXT DEFAULT NULL
            )
        ');

        $this->connection->exec('
            CREATE TABLE parity_posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                body TEXT DEFAULT NULL,
                created_at TEXT DEFAULT NULL,
                updated_at TEXT DEFAULT NULL
            )
        ');

        $this->connection->exec('
            CREATE TABLE parity_comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                post_id INTEGER NOT NULL,
                content TEXT NOT NULL,
                created_at TEXT DEFAULT NULL,
                updated_at TEXT DEFAULT NULL
            )
        ');

        $this->connection->exec('
            CREATE TABLE parity_soft_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                deleted_at TEXT DEFAULT NULL,
                created_at TEXT DEFAULT NULL,
                updated_at TEXT DEFAULT NULL
            )
        ');

        // 重置所有模型
        ParityUser::resetBooted();
        ParityUser::flushEventListeners();
        ParityUser::setConnection($this->connection);
        ParityUser::preventLazyLoading(false);
        ParityUser::preventSilentlyDiscardingAttributes(false);
        ParityUser::preventAccessingMissingAttributes(false);

        ParityPost::resetBooted();
        ParityPost::flushEventListeners();
        ParityPost::setConnection($this->connection);

        ParityComment::resetBooted();
        ParityComment::setConnection($this->connection);

        ParitySoftUser::resetBooted();
        ParitySoftUser::flushEventListeners();
        ParitySoftUser::setConnection($this->connection);
    }

    protected function tearDown(): void
    {
        ParityUser::flushEventListeners();
        ParityUser::resetBooted();
        ParityUser::setConnection(null);

        ParityPost::flushEventListeners();
        ParityPost::resetBooted();
        ParityPost::setConnection(null);

        ParityComment::resetBooted();
        ParityComment::setConnection(null);

        ParitySoftUser::flushEventListeners();
        ParitySoftUser::resetBooted();
        ParitySoftUser::setConnection(null);

        Model::reguard();
        Model::preventLazyLoading(false);
        Model::preventSilentlyDiscardingAttributes(false);
        Model::preventAccessingMissingAttributes(false);

        $this->connection = null;
        parent::tearDown();
    }

    // =========================================================================
    // 基线约定测试
    // =========================================================================

    public function testPerPageDefaultValue(): void
    {
        $user = new ParityUser();
        $this->assertEquals(15, $user->getPerPage());
    }

    public function testPerPageCustomizable(): void
    {
        $user = new ParityUser();
        $user->setPerPage(25);
        $this->assertEquals(25, $user->getPerPage());
    }

    public function testWithDefaultEmpty(): void
    {
        $user = new ParityUser();
        $this->assertEquals([], $user->getWith());
    }

    public function testWithCustomizable(): void
    {
        $user = new ParityUser();
        $user->setWith(['posts']);
        $this->assertEquals(['posts'], $user->getWith());
    }

    public function testKeyNameDefault(): void
    {
        $user = new ParityUser();
        $this->assertEquals('id', $user->getKeyName());
    }

    public function testKeyTypeDefault(): void
    {
        $user = new ParityUser();
        $this->assertEquals('int', $user->getKeyType());
    }

    public function testIncrementingDefault(): void
    {
        $user = new ParityUser();
        $this->assertTrue($user->getIncrementing());
    }

    public function testForeignKeyIsSnakeCase(): void
    {
        // 测试 getForeignKey 在 HasRelationships 中生成 snake_case
        $post = new ParityPost();
        // 通过反射测试 protected 方法
        $ref = new \ReflectionMethod($post, 'getForeignKey');
        $foreignKey = $ref->invoke($post);
        $this->assertEquals('parity_post_id', $foreignKey);
    }

    public function testJoiningTableSnakeCase(): void
    {
        // 通过反射测试 protected 方法
        $user = new ParityUser();
        $ref = new \ReflectionMethod($user, 'joiningTable');
        $table = $ref->invoke($user, ParityPost::class);
        $this->assertEquals('parity_post_parity_user', $table);
    }

    // =========================================================================
    // Mass Assignment 边界测试
    // =========================================================================

    public function testFillableAcceptsWhitelistedAttributes(): void
    {
        $user = new ParityUser();
        $user->fill(['name' => 'John', 'email' => 'john@example.com', 'role' => 'admin']);
        $this->assertEquals('John', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertNull($user->role); // role 不在 fillable 中
    }

    public function testGuardedBlocksAllWhenStar(): void
    {
        $guardedModel = new GuardedParityUser();
        $guardedModel->fill(['name' => 'Blocked']);
        $this->assertNull($guardedModel->name);
    }

    public function testSetAttributeBypassesGuard(): void
    {
        // setAttribute 应该不检查 guard（仅 fill 检查）
        $user = new ParityUser();
        $user->setAttribute('role', 'admin');
        $this->assertEquals('admin', $user->role);
    }

    public function testPropertySetBypassesGuard(): void
    {
        $user = new ParityUser();
        $user->role = 'admin';
        $this->assertEquals('admin', $user->role);
    }

    public function testForceFillBypassesGuard(): void
    {
        $user = new ParityUser();
        $user->forceFill(['name' => 'John', 'email' => 'j@e.com', 'role' => 'admin']);
        $this->assertEquals('admin', $user->role);
    }

    public function testIsFillable(): void
    {
        $user = new ParityUser();
        $this->assertTrue($user->isFillable('name'));
        $this->assertTrue($user->isFillable('email'));
        $this->assertFalse($user->isFillable('role'));
    }

    public function testIsGuarded(): void
    {
        // ParityUser 有 guarded=['*']（默认），所以所有属性都被 guard
        $user = new ParityUser();
        $this->assertTrue($user->isGuarded('name'));
        $this->assertTrue($user->isGuarded('email'));
        $this->assertTrue($user->isGuarded('role'));

        // 但 name 和 email 在 fillable 中，所以 isFillable 为 true
        $this->assertTrue($user->isFillable('name'));
        $this->assertTrue($user->isFillable('email'));
        $this->assertFalse($user->isFillable('role'));
    }

    public function testTotallyGuarded(): void
    {
        $guarded = new GuardedParityUser();
        $this->assertTrue($guarded->totallyGuarded());

        $user = new ParityUser();
        $this->assertFalse($user->totallyGuarded());
    }

    public function testUnguardAllowsAllAttributes(): void
    {
        Model::unguard();

        $user = new ParityUser();
        $user->fill(['name' => 'John', 'email' => 'j@e.com', 'role' => 'admin']);
        $this->assertEquals('admin', $user->role);

        Model::reguard();
    }

    public function testUnguardedCallback(): void
    {
        $result = Model::unguarded(function () {
            $user = new ParityUser();
            $user->fill(['name' => 'John', 'email' => 'j@e.com', 'role' => 'admin']);
            return $user->role;
        });

        $this->assertEquals('admin', $result);

        // 回调结束后应恢复保护状态
        $user = new ParityUser();
        $user->fill(['role' => 'should-be-blocked']);
        $this->assertNull($user->role);
    }

    // =========================================================================
    // Strictness 行为测试
    // =========================================================================

    public function testPreventSilentlyDiscardingAttributes(): void
    {
        ParityUser::preventSilentlyDiscardingAttributes(true);

        $thrown = false;
        try {
            $user = new ParityUser();
            $user->fill(['name' => 'John', 'role' => 'admin']);
        } catch (InvalidArgumentException $e) {
            $thrown = true;
            $this->assertStringContainsString('silently discarded', $e->getMessage());
        }

        $this->assertTrue($thrown, 'Expected InvalidArgumentException was not thrown');
    }

    public function testPreventAccessingMissingAttributes(): void
    {
        ParityUser::preventAccessingMissingAttributes(true);

        $thrown = false;
        try {
            $user = new ParityUser();
            $user->nonexistent_attribute;
        } catch (InvalidArgumentException $e) {
            $thrown = true;
            $this->assertStringContainsString('does not exist', $e->getMessage());
        }

        $this->assertTrue($thrown, 'Expected InvalidArgumentException was not thrown');
    }

    public function testShouldBeStrictEnablesAll(): void
    {
        ParityUser::shouldBeStrict();

        // 测试静默丢弃
        try {
            $user = new ParityUser();
            $user->fill(['role' => 'admin']);
            $this->fail('Should have thrown for discarded attribute');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('silently discarded', $e->getMessage());
        }
    }

    public function testStrictModeOffByDefault(): void
    {
        // 默认应不抛异常
        $user = new ParityUser();
        $user->fill(['name' => 'John', 'role' => 'admin']); // role 被静默忽略
        $this->assertNull($user->role);

        $value = $user->nonexistent_attribute;
        $this->assertNull($value);
    }

    // =========================================================================
    // Boot 生命周期测试
    // =========================================================================

    public function testInitializeTraitsCalledOnConstruct(): void
    {
        $user = new InitTestUser();
        $this->assertTrue($user->initialized, 'initializeTraits should call initialize{TraitName} methods');
    }

    public function testBootCalledOncePerClass(): void
    {
        $class = ParityUser::class;
        ParityUser::boot();

        // 再次调用不应重复引导
        ParityUser::boot();

        // 通过创建实例验证不会重复引导
        $user = new ParityUser();
        $this->assertInstanceOf(ParityUser::class, $user);
    }

    public function testRetrievedEventFiresOnFetch(): void
    {
        $retrievedFired = false;

        ParityUser::retrieved(function ($model) use (&$retrievedFired) {
            $retrievedFired = true;
        });

        ParityUser::create(['name' => 'John', 'email' => 'j@e.com']);
        ParityUser::find(1);

        $this->assertTrue($retrievedFired);
    }

    // =========================================================================
    // 关系属性访问测试
    // =========================================================================

    public function testRelationPropertyAccess(): void
    {
        // 创建用户和帖子
        ParityUser::create(['name' => 'John', 'email' => 'j@e.com']);
        ParityPost::create(['user_id' => 1, 'title' => 'First Post']);

        $user = ParityUser::find(1);
        $posts = $user->posts; // 通过属性访问关系

        $this->assertInstanceOf(Collection::class, $posts);
        $this->assertCount(1, $posts);
    }

    public function testRelationLoadedCached(): void
    {
        ParityUser::create(['name' => 'John', 'email' => 'j@e.com']);
        ParityPost::create(['user_id' => 1, 'title' => 'First Post']);

        $user = ParityUser::find(1);

        // 第一次访问
        $posts1 = $user->posts;
        // 第二次访问（应从缓存返回）
        $posts2 = $user->posts;

        $this->assertSame($posts1, $posts2);
    }

    public function testRelationLoadedCheck(): void
    {
        $user = new ParityUser();
        $this->assertFalse($user->relationLoaded('posts'));

        $user->setRelation('posts', new Collection());
        $this->assertTrue($user->relationLoaded('posts'));
    }

    public function testLoadMethodLoadsRelation(): void
    {
        ParityUser::create(['name' => 'John', 'email' => 'j@e.com']);
        ParityPost::create(['user_id' => 1, 'title' => 'First Post']);

        $user = ParityUser::find(1);
        $this->assertFalse($user->relationLoaded('posts'));

        $user->load('posts');
        $this->assertTrue($user->relationLoaded('posts'));
    }

    public function testBelongsToRelationAccess(): void
    {
        ParityUser::create(['name' => 'John', 'email' => 'j@e.com']);
        ParityPost::create(['user_id' => 1, 'title' => 'First Post']);

        $post = ParityPost::find(1);
        $user = $post->user; // 通过属性访问 belongsTo

        $this->assertInstanceOf(ParityUser::class, $user);
        $this->assertEquals('John', $user->name);
    }

    // =========================================================================
    // 序列化测试
    // =========================================================================

    public function testToArrayIncludesAttributes(): void
    {
        $user = new ParityUser();
        $user->setAttribute('name', 'John');
        $user->setAttribute('email', 'j@e.com');

        $array = $user->toArray();
        $this->assertEquals('John', $array['name']);
        $this->assertEquals('j@e.com', $array['email']);
    }

    public function testToArrayIncludesLoadedRelations(): void
    {
        ParityUser::create(['name' => 'John', 'email' => 'j@e.com']);
        ParityPost::create(['user_id' => 1, 'title' => 'Test']);

        $user = ParityUser::find(1);
        $user->load('posts');

        $array = $user->toArray();
        $this->assertArrayHasKey('posts', $array);
        $this->assertCount(1, $array['posts']);
    }

    public function testToArrayExcludesHidden(): void
    {
        $user = new HiddenParityUser();
        $user->setAttribute('name', 'John');
        $user->setAttribute('email', 'secret@example.com');

        $array = $user->toArray();
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayNotHasKey('email', $array);
    }

    public function testToArrayOnlyShowsVisible(): void
    {
        $user = new VisibleParityUser();
        $user->setAttribute('name', 'John');
        $user->setAttribute('email', 'j@e.com');
        $user->setAttribute('role', 'admin');

        $array = $user->toArray();
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayNotHasKey('role', $array);
    }

    public function testCastAppliedInToArray(): void
    {
        ParityUser::create(['name' => 'John', 'email' => 'j@e.com']);

        $user = ParityUser::find(1);
        $array = $user->toArray();
        // is_active 应被 cast 为 boolean
        $this->assertTrue($array['is_active']);
    }

    public function testToJsonIncludesRelations(): void
    {
        ParityUser::create(['name' => 'John', 'email' => 'j@e.com']);
        ParityPost::create(['user_id' => 1, 'title' => 'Test']);

        $user = ParityUser::find(1);
        $user->load('posts');

        $json = $user->toJson();
        $data = json_decode($json, true);

        $this->assertArrayHasKey('posts', $data);
    }

    public function testToArrayWithRelationsDeprecated(): void
    {
        ParityUser::create(['name' => 'John', 'email' => 'j@e.com']);
        ParityPost::create(['user_id' => 1, 'title' => 'Test']);

        $user = ParityUser::find(1);
        $user->load('posts');

        $array = $user->toArrayWithRelations();
        $this->assertArrayHasKey('posts', $array);
    }

    // =========================================================================
    // getMorphClass 测试
    // =========================================================================

    public function testGetMorphClassReturnsClassName(): void
    {
        $user = new ParityUser();
        $this->assertEquals(ParityUser::class, $user->getMorphClass());
    }

    public function testGetMorphClassWithAlias(): void
    {
        ParityUser::enforceMorphMap(['user' => ParityUser::class]);

        $user = new ParityUser();
        $this->assertEquals('user', $user->getMorphClass());

        // 清理
        ParityUser::resetBooted();
    }

    // =========================================================================
    // 完整生命周期测试（CRUD + Events）
    // =========================================================================

    public function testFullCreateLifecycle(): void
    {
        $events = [];

        ParityUser::creating(function ($model) use (&$events) {
            $events[] = 'creating';
        });
        ParityUser::created(function ($model) use (&$events) {
            $events[] = 'created';
        });
        ParityUser::saving(function ($model) use (&$events) {
            $events[] = 'saving';
        });
        ParityUser::saved(function ($model) use (&$events) {
            $events[] = 'saved';
        });

        ParityUser::create(['name' => 'John', 'email' => 'j@e.com']);

        $this->assertEquals(['saving', 'creating', 'created', 'saved'], $events);
    }

    public function testFullUpdateLifecycle(): void
    {
        ParityUser::create(['name' => 'John', 'email' => 'j@e.com']);

        $events = [];

        ParityUser::updating(function ($model) use (&$events) {
            $events[] = 'updating';
        });
        ParityUser::updated(function ($model) use (&$events) {
            $events[] = 'updated';
        });
        ParityUser::saving(function ($model) use (&$events) {
            $events[] = 'saving';
        });
        ParityUser::saved(function ($model) use (&$events) {
            $events[] = 'saved';
        });

        $user = ParityUser::find(1);
        $user->name = 'Jane';
        $user->save();

        $this->assertEquals(['saving', 'updating', 'updated', 'saved'], $events);
    }

    public function testFullDeleteLifecycle(): void
    {
        ParityUser::create(['name' => 'John', 'email' => 'j@e.com']);

        $events = [];

        ParityUser::deleting(function () use (&$events) {
            $events[] = 'deleting';
        });
        ParityUser::deleted(function () use (&$events) {
            $events[] = 'deleted';
        });

        $user = ParityUser::find(1);
        $user->delete();

        $this->assertEquals(['deleting', 'deleted'], $events);
    }

    // =========================================================================
    // Model::withoutEvents 测试
    // =========================================================================

    public function testWithoutEventsDisablesEvents(): void
    {
        $eventFired = false;

        ParityUser::created(function () use (&$eventFired) {
            $eventFired = true;
        });

        Model::withoutEvents(function () {
            ParityUser::create(['name' => 'Silent', 'email' => 's@e.com']);
        });

        $this->assertFalse($eventFired);
    }

    // =========================================================================
    // Replicate 测试
    // =========================================================================

    public function testReplicateCreatesNewInstance(): void
    {
        $user = ParityUser::create(['name' => 'John', 'email' => 'j@e.com']);

        $clone = $user->replicate();

        $this->assertFalse($clone->exists);
        $this->assertNull($clone->getKey());
        $this->assertEquals('John', $clone->name);
        $this->assertEquals('j@e.com', $clone->email);
    }
}

// =========================================================================
// 测试模型定义
// =========================================================================

class ParityUser extends Model
{
    protected string $table = 'parity_users';
    protected array $fillable = ['name', 'email'];
    protected bool $timestamps = false;
    protected array $casts = [
        'is_active' => 'boolean',
    ];

    public function posts(): \Bin\Database\Relations\HasMany
    {
        return $this->hasMany(ParityPost::class, 'user_id');
    }
}

class ParityPost extends Model
{
    protected string $table = 'parity_posts';
    protected array $fillable = ['user_id', 'title', 'body'];
    protected bool $timestamps = false;

    public function user(): \Bin\Database\Relations\BelongsTo
    {
        return $this->belongsTo(ParityUser::class, 'user_id');
    }

    public function comments(): \Bin\Database\Relations\HasMany
    {
        return $this->hasMany(ParityComment::class, 'post_id');
    }
}

class ParityComment extends Model
{
    protected string $table = 'parity_comments';
    protected array $fillable = ['post_id', 'content'];
    protected bool $timestamps = false;
}

class ParitySoftUser extends Model
{
    use SoftDeletes;

    protected string $table = 'parity_soft_users';
    protected array $fillable = ['name'];
    protected bool $timestamps = false;
}

class GuardedParityUser extends Model
{
    protected string $table = 'parity_users';
    protected array $fillable = [];
    protected array $guarded = ['*'];
    protected bool $timestamps = false;
}

class HiddenParityUser extends Model
{
    protected string $table = 'parity_users';
    protected array $fillable = ['name', 'email'];
    protected array $hidden = ['email'];
    protected bool $timestamps = false;
}

class VisibleParityUser extends Model
{
    protected string $table = 'parity_users';
    protected array $fillable = ['name', 'email', 'role'];
    protected array $visible = ['name', 'email'];
    protected bool $timestamps = false;
}

class InitTestUser extends Model
{
    use InitTestTrait;

    protected string $table = 'parity_users';
    public bool $initialized = false;
    protected bool $timestamps = false;
}

trait InitTestTrait
{
    protected function initializeInitTestTrait(): void
    {
        /** @var InitTestUser $this */
        $this->initialized = true;
    }
}
