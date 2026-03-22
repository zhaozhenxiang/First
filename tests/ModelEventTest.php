<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Database\Model;
use Bin\Database\ModelEventDispatcher;
use PDO;

/**
 * 模型事件测试
 */
class ModelEventTest extends TestCase
{
    protected ?PDO $connection = null;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建 SQLite 内存数据库
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 创建测试表
        $this->connection->exec('
            CREATE TABLE event_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');

        // 重置并设置模型
        EventTestUser::resetBooted();
        EventTestUser::flushEventListeners();
        EventTestUser::setConnection($this->connection);
    }

    protected function tearDown(): void
    {
        EventTestUser::flushEventListeners();
        EventTestUser::resetBooted();
        EventTestUser::setConnection(null);
        $this->connection = null;

        parent::tearDown();
    }

    // =========================================================================
    // 创建事件测试
    // =========================================================================

    public function testCreatingEventFires(): void
    {
        $fired = false;
        $capturedModel = null;

        EventTestUser::creating(function ($model) use (&$fired, &$capturedModel) {
            $fired = true;
            $capturedModel = $model;
        });

        $user = EventTestUser::create(['name' => 'Test', 'email' => 'test@example.com']);

        $this->assertTrue($fired);
        $this->assertSame($user, $capturedModel);
    }

    public function testCreatedEventFires(): void
    {
        $fired = false;

        EventTestUser::created(function ($model) use (&$fired) {
            $fired = true;
        });

        EventTestUser::create(['name' => 'Test', 'email' => 'test@example.com']);

        $this->assertTrue($fired);
    }

    public function testCreatingEventCanPreventInsert(): void
    {
        EventTestUser::creating(function ($model) {
            return false; // 阻止创建
        });

        $user = EventTestUser::create(['name' => 'Test', 'email' => 'test@example.com']);

        // 创建应该失败
        $this->assertFalse($user->exists);

        // 数据库不应该有记录
        $stmt = $this->connection->query('SELECT COUNT(*) FROM event_users');
        $this->assertEquals(0, $stmt->fetchColumn());
    }

    // =========================================================================
    // 更新事件测试
    // =========================================================================

    public function testUpdatingEventFires(): void
    {
        $fired = false;

        EventTestUser::updating(function ($model) use (&$fired) {
            $fired = true;
        });

        $user = EventTestUser::create(['name' => 'Test', 'email' => 'test@example.com']);
        $user->name = 'Updated';
        $user->save();

        $this->assertTrue($fired);
    }

    public function testUpdatedEventFires(): void
    {
        $fired = false;

        EventTestUser::updated(function ($model) use (&$fired) {
            $fired = true;
        });

        $user = EventTestUser::create(['name' => 'Test', 'email' => 'test@example.com']);
        $user->name = 'Updated';
        $user->save();

        $this->assertTrue($fired);
    }

    public function testUpdatingEventCanPreventUpdate(): void
    {
        EventTestUser::updating(function ($model) {
            return false; // 阻止更新
        });

        $user = EventTestUser::create(['name' => 'Test', 'email' => 'test@example.com']);
        $originalName = $user->name;

        $user->name = 'Updated';
        $result = $user->save();

        $this->assertFalse($result);

        // 刷新并检查名称未更新
        $freshUser = EventTestUser::find($user->id);
        $this->assertEquals($originalName, $freshUser->name);
    }

    // =========================================================================
    // 保存事件测试
    // =========================================================================

    public function testSavingEventFiresOnCreate(): void
    {
        $fired = false;

        EventTestUser::saving(function ($model) use (&$fired) {
            $fired = true;
        });

        EventTestUser::create(['name' => 'Test', 'email' => 'test@example.com']);

        $this->assertTrue($fired);
    }

    public function testSavingEventFiresOnUpdate(): void
    {
        $count = 0;

        EventTestUser::saving(function ($model) use (&$count) {
            $count++;
        });

        $user = EventTestUser::create(['name' => 'Test', 'email' => 'test@example.com']);
        $user->name = 'Updated';
        $user->save();

        // 创建和更新各触发一次
        $this->assertEquals(2, $count);
    }

    public function testSavedEventFiresAfterCreate(): void
    {
        $fired = false;
        $modelId = null;

        EventTestUser::saved(function ($model) use (&$fired, &$modelId) {
            $fired = true;
            $modelId = $model->id;
        });

        $user = EventTestUser::create(['name' => 'Test', 'email' => 'test@example.com']);

        $this->assertTrue($fired);
        $this->assertNotNull($modelId);
    }

    // =========================================================================
    // 删除事件测试
    // =========================================================================

    public function testDeletingEventFires(): void
    {
        $fired = false;

        EventTestUser::deleting(function ($model) use (&$fired) {
            $fired = true;
        });

        $user = EventTestUser::create(['name' => 'Test', 'email' => 'test@example.com']);
        $user->delete();

        $this->assertTrue($fired);
    }

    public function testDeletedEventFires(): void
    {
        $fired = false;

        EventTestUser::deleted(function ($model) use (&$fired) {
            $fired = true;
        });

        $user = EventTestUser::create(['name' => 'Test', 'email' => 'test@example.com']);
        $user->delete();

        $this->assertTrue($fired);
    }

    public function testDeletingEventCanPreventDelete(): void
    {
        EventTestUser::deleting(function ($model) {
            return false; // 阻止删除
        });

        $user = EventTestUser::create(['name' => 'Test', 'email' => 'test@example.com']);
        $result = $user->delete();

        $this->assertFalse($result);

        // 记录应该仍然存在
        $freshUser = EventTestUser::find($user->id);
        $this->assertNotNull($freshUser);
    }

    // =========================================================================
    // 事件顺序测试
    // =========================================================================

    public function testEventOrderOnCreate(): void
    {
        $order = [];

        EventTestUser::saving(function () use (&$order) {
            $order[] = 'saving';
        });

        EventTestUser::creating(function () use (&$order) {
            $order[] = 'creating';
        });

        EventTestUser::created(function () use (&$order) {
            $order[] = 'created';
        });

        EventTestUser::saved(function () use (&$order) {
            $order[] = 'saved';
        });

        EventTestUser::create(['name' => 'Test', 'email' => 'test@example.com']);

        $this->assertEquals(['saving', 'creating', 'created', 'saved'], $order);
    }

    public function testEventOrderOnUpdate(): void
    {
        $order = [];

        EventTestUser::saving(function () use (&$order) {
            $order[] = 'saving';
        });

        EventTestUser::updating(function () use (&$order) {
            $order[] = 'updating';
        });

        EventTestUser::updated(function () use (&$order) {
            $order[] = 'updated';
        });

        EventTestUser::saved(function () use (&$order) {
            $order[] = 'saved';
        });

        $user = EventTestUser::create(['name' => 'Test', 'email' => 'test@example.com']);
        $order = []; // 重置

        $user->name = 'Updated';
        $user->save();

        $this->assertEquals(['saving', 'updating', 'updated', 'saved'], $order);
    }

    // =========================================================================
    // 多个监听器测试
    // =========================================================================

    public function testMultipleListeners(): void
    {
        $count = 0;

        EventTestUser::creating(function () use (&$count) {
            $count++;
        });

        EventTestUser::creating(function () use (&$count) {
            $count++;
        });

        EventTestUser::creating(function () use (&$count) {
            $count++;
        });

        EventTestUser::create(['name' => 'Test', 'email' => 'test@example.com']);

        $this->assertEquals(3, $count);
    }

    // =========================================================================
    // 模型隔离测试
    // =========================================================================

    public function testEventListenersAreModelSpecific(): void
    {
        $userFired = false;
        $otherFired = false;

        EventTestUser::created(function () use (&$userFired) {
            $userFired = true;
        });

        OtherEventModel::created(function () use (&$otherFired) {
            $otherFired = true;
        });

        OtherEventModel::setConnection($this->connection);
        OtherEventModel::resetBooted();

        EventTestUser::create(['name' => 'Test', 'email' => 'test@example.com']);

        $this->assertTrue($userFired);
        $this->assertFalse($otherFired);
    }

    // =========================================================================
    // 事件修改模型测试
    // =========================================================================

    public function testEventCanModifyModel(): void
    {
        EventTestUser::creating(function ($model) {
            $model->email = 'modified@example.com';
        });

        $user = EventTestUser::create(['name' => 'Test', 'email' => 'original@example.com']);

        $this->assertEquals('modified@example.com', $user->email);

        // 验证数据库中也已修改
        $freshUser = EventTestUser::find($user->id);
        $this->assertEquals('modified@example.com', $freshUser->email);
    }

    // =========================================================================
    // 全局事件监听器测试
    // =========================================================================

    public function testGlobalEventListener(): void
    {
        $fired = false;

        ModelEventDispatcher::listen('created', function ($model) use (&$fired) {
            $fired = true;
        });

        EventTestUser::create(['name' => 'Test', 'email' => 'test@example.com']);

        $this->assertTrue($fired);
    }

    // =========================================================================
    // 清除监听器测试
    // =========================================================================

    public function testFlushEventListeners(): void
    {
        $count = 0;

        EventTestUser::created(function () use (&$count) {
            $count++;
        });

        EventTestUser::create(['name' => 'Test1', 'email' => 'test1@example.com']);
        $this->assertEquals(1, $count);

        EventTestUser::flushEventListeners();

        EventTestUser::create(['name' => 'Test2', 'email' => 'test2@example.com']);
        $this->assertEquals(1, $count); // 计数不变
    }
}

/**
 * 测试用模型
 */
class EventTestUser extends Model
{
    protected string $table = 'event_users';

    protected array $fillable = ['name', 'email'];

    protected bool $timestamps = false;
}

/**
 * 另一个测试模型
 */
class OtherEventModel extends Model
{
    protected string $table = 'event_users';

    protected array $fillable = ['name', 'email'];

    protected bool $timestamps = false;
}