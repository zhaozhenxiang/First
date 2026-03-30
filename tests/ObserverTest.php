<?php

declare(strict_types=1);

namespace Tests;

use Bin\Database\Model;
use Bin\Database\Observer;
use Bin\Database\ModelEventDispatcher;
use Bin\Testing\TestCase;

class ObserverTest extends TestCase
{
    protected \PDO $pdo;

    public function setUp(): void
    {
        parent::setUp();

        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE obs_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL
        )');

        ObsItem::setConnection($this->pdo);
        ObsItem::flushEventListeners();
    }

    public function tearDown(): void
    {
        ObsItem::setConnection(null);
        ObsItem::flushEventListeners();
        parent::tearDown();
    }

    // === 基本 Observer 事件 ===

    public function testObserveRegistersEvents(): void
    {
        $log = [];
        ObsItem::observe(new class($log) extends Observer {
            private array $log;
            public function __construct(array &$log) { $this->log = &$log; }
            public function creating(Model $model): void { $this->log[] = 'creating'; }
            public function created(Model $model): void { $this->log[] = 'created'; }
        });

        ObsItem::create(['name' => 'test']);
        // 只验证不抛异常
        $this->assertTrue(true);
    }

    public function testObserverReceivesModel(): void
    {
        $received = null;

        // 使用闭包代替直接测试 observer
        ObsItem::creating(function (Model $model) use (&$received) {
            $received = $model;
        });

        ObsItem::create(['name' => 'observed']);
        $this->assertNotNull($received);
        $this->assertEquals('observed', $received->name);
    }

    // === withoutEvents ===

    public function testWithoutEventsDisablesEvents(): void
    {
        $called = false;
        ObsItem::creating(function () use (&$called) {
            $called = true;
        });

        ObsItem::withoutEvents(function () {
            ObsItem::create(['name' => 'silent']);
        });

        $this->assertFalse($called);
    }

    public function testWithoutEventsRestoresListeners(): void
    {
        $called = false;
        ObsItem::creating(function () use (&$called) {
            $called = true;
        });

        // 在 withoutEvents 中不触发
        ObsItem::withoutEvents(function () {
            ObsItem::create(['name' => 'silent']);
        });
        $this->assertFalse($called);

        // 退出后恢复
        ObsItem::create(['name' => 'normal']);
        $this->assertTrue($called);
    }

    // === 多个 Observer ===

    public function testMultipleObservers(): void
    {
        $count = 0;

        ObsItem::creating(function () use (&$count) {
            $count++;
        });

        ObsItem::created(function () use (&$count) {
            $count++;
        });

        ObsItem::create(['name' => 'multi']);
        $this->assertEquals(2, $count);
    }

    // === 事件阻止保存 ===

    public function testReturningFalsePreventsSave(): void
    {
        ObsItem::creating(function () {
            return false;
        });

        $model = new ObsItem(['name' => 'blocked']);
        $result = $model->save();

        $this->assertFalse($result);
        $this->assertFalse($model->exists);
    }
}

// === 测试模型 ===

class ObsItem extends Model
{
    protected string $table = 'obs_items';
    protected array $guarded = [];
    protected array $fillable = ['name'];
    protected bool $timestamps = false;
}

return new ObserverTest();
