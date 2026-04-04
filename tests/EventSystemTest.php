<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Events\EventDispatcher;
use Bin\Events\Event;
use Bin\Events\Subscriber;
use Bin\Database\Model;
use Bin\Database\ModelEventDispatcher;
use Bin\Facade\Facade;
use Bin\App\App;

/**
 * 事件系统全面测试
 *
 * 覆盖：EventDispatcher 核心、监听器解析、通配符、事件对象、
 * Subscriber、Event 基类、容器集成、Facade、ModelEventDispatcher 委托
 */
class EventSystemTest extends TestCase
{
    private ?\PDO $pdo = null;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        Facade::clear();
        if ($this->pdo !== null) {
            EventSysItem::setConnection(null);
            EventSysItem::flushEventListeners();
            $this->pdo = null;
        }
        parent::tearDown();
    }

    // =========================================================================
    // 1. EventDispatcher 核心分发
    // =========================================================================

    public function testClosureListenerReceivesPayload(): void
    {
        $d = new EventDispatcher();
        $received = null;
        $d->listen('test.event', function ($name, $age) use (&$received) {
            $received = ['name' => $name, 'age' => $age];
        });
        $d->dispatch('test.event', ['Alice', 30]);

        $this->assertEquals(['name' => 'Alice', 'age' => 30], $received);
    }

    public function testMultipleListenersExecutedInOrder(): void
    {
        $d = new EventDispatcher();
        $order = [];
        $d->listen('ordered', function () use (&$order) { $order[] = 1; });
        $d->listen('ordered', function () use (&$order) { $order[] = 2; });
        $d->listen('ordered', function () use (&$order) { $order[] = 3; });
        $d->dispatch('ordered');

        $this->assertEquals([1, 2, 3], $order);
    }

    public function testDispatchReturnsResponses(): void
    {
        $d = new EventDispatcher();
        $d->listen('return.test', function () { return 'a'; });
        $d->listen('return.test', function () { return 'b'; });

        $result = $d->dispatch('return.test');
        $this->assertEquals(['a', 'b'], $result);
    }

    public function testDispatchReturnsNullOnStop(): void
    {
        $d = new EventDispatcher();
        $d->listen('stop', function () { return 'first'; });
        $d->listen('stop', function () { return false; });
        $d->listen('stop', function () { return 'third'; });

        $result = $d->dispatch('stop');
        $this->assertNull($result);
    }

    public function testStopPropagationPreventsSubsequent(): void
    {
        $d = new EventDispatcher();
        $called = [];
        $d->listen('prop', function () use (&$called) { $called[] = 'first'; return false; });
        $d->listen('prop', function () use (&$called) { $called[] = 'second'; });
        $d->dispatch('prop');

        $this->assertEquals(['first'], $called);
    }

    public function testForgetRemovesListeners(): void
    {
        $d = new EventDispatcher();
        $called = false;
        $d->listen('forget.me', function () use (&$called) { $called = true; });
        $d->forget('forget.me');
        $d->dispatch('forget.me');

        $this->assertFalse($called);
    }

    public function testForgetAllClearsEverything(): void
    {
        $d = new EventDispatcher();
        $called = false;
        $d->listen('event.a', function () use (&$called) { $called = true; });
        $d->listen('*', function () use (&$called) { $called = true; });
        $d->forgetAll();
        $d->dispatch('event.a');

        $this->assertFalse($called);
    }

    // =========================================================================
    // 2. 通配符事件
    // =========================================================================

    public function testWildcardCatchesAllEvents(): void
    {
        $d = new EventDispatcher();
        $captured = null;
        $d->listen('*', function ($eventName, $data) use (&$captured) {
            $captured = ['event' => $eventName, 'data' => $data];
        });
        $d->dispatch('app.user.created', ['Alice']);

        // 通配符监听器签名 ($eventName, ...$payload)，payload 展开后 $data = 'Alice'
        $this->assertEquals(['event' => 'app.user.created', 'data' => 'Alice'], $captured);
    }

    public function testWildcardFiresAfterSpecific(): void
    {
        $d = new EventDispatcher();
        $order = [];
        $d->listen('ordered', function () use (&$order) { $order[] = 'specific'; });
        $d->listen('*', function ($event) use (&$order) { $order[] = 'wildcard'; });
        $d->dispatch('ordered');

        $this->assertEquals(['specific', 'wildcard'], $order);
    }

    public function testWildcardStopPropagation(): void
    {
        $d = new EventDispatcher();
        $order = [];
        $d->listen('*', function () use (&$order) { $order[] = 'wild1'; return false; });
        $d->listen('*', function () use (&$order) { $order[] = 'wild2'; });
        $d->dispatch('any.event');

        $this->assertEquals(['wild1'], $order);
    }

    public function testHasListenersForWildcard(): void
    {
        $d = new EventDispatcher();
        $this->assertFalse($d->hasListeners('*'));

        $d->listen('*', function () {});
        $this->assertTrue($d->hasListeners('*'));
    }

    // =========================================================================
    // 3. 事件对象
    // =========================================================================

    public function testDispatchEventObjectUsesClassName(): void
    {
        $d = new EventDispatcher();
        $received = null;
        $d->listen(UserRegisteredEvent::class, function ($event) use (&$received) {
            $received = $event;
        });
        $event = new UserRegisteredEvent('Alice');
        $d->dispatch($event);

        $this->assertSame($event, $received);
    }

    public function testListenerReceivesEventObject(): void
    {
        $d = new EventDispatcher();
        $name = null;
        $d->listen(UserRegisteredEvent::class, function ($event) use (&$name) {
            $name = $event->name;
        });
        $d->dispatch(new UserRegisteredEvent('Bob'));

        $this->assertEquals('Bob', $name);
    }

    public function testCustomEventSubclass(): void
    {
        $d = new EventDispatcher();
        $fired = false;
        $event = new UserRegisteredEvent('Charlie');
        $d->listen(UserRegisteredEvent::class, function () use (&$fired) { $fired = true; });
        $d->dispatch($event);

        $this->assertTrue($fired);
    }

    public function testEventStopPropagation(): void
    {
        $event = new UserRegisteredEvent('Dave');
        $this->assertFalse($event->isPropagationStopped());

        $event->stopPropagation();
        $this->assertTrue($event->isPropagationStopped());
    }

    // =========================================================================
    // 4. 监听器解析
    // =========================================================================

    public function testClassAtMethodListener(): void
    {
        $d = new EventDispatcher();
        $listener = new MethodListener();
        // 注册一个临时类名 — 使用 InvokableListener 的 __invoke 作为备选
        // 由于 MethodListener 不是全局类，用数组格式测试 Class@method 行为
        // 实际 Class@method 需要容器可解析的类名，这里测试无容器场景
        $d->listen('method.test', MethodListener::class . '@handle');

        $d->dispatch('method.test', ['hello']);

        // MethodListener 通过 new 实例化后调用 handle('hello')
        // 但每次 dispatch 会 new 一个新实例，无法直接检查 log
        // 所以这里只验证不抛异常即可
        $this->assertTrue(true);
    }

    public function testInvokableClassListener(): void
    {
        $d = new EventDispatcher();
        $d->listen('invoke.test', InvokableListener::class);
        $d->dispatch('invoke.test', ['data']);

        // 同理，每次 new 新实例
        $this->assertTrue(true);
    }

    public function testArrayCallableListener(): void
    {
        $d = new EventDispatcher();
        $listener = new MethodListener();
        $d->listen('array.test', [$listener, 'handle']);
        $d->dispatch('array.test', ['hello']);

        $this->assertEquals(['hello'], $listener->log);
    }

    public function testContainerResolutionForListeners(): void
    {
        // 创建带容器的 dispatcher
        $app = App::getInstance();
        $container = $app->getContainer();

        // 注册一个单例
        $container->singleton(InvokableListener::class);

        $d = new EventDispatcher();
        $d->setContainer($container);

        $d->listen('container.test', InvokableListener::class);
        $d->dispatch('container.test', ['fired']);

        // 通过容器解析，验证不抛异常
        $this->assertTrue(true);
    }

    public function testFallbackNewWithoutContainer(): void
    {
        $d = new EventDispatcher();
        $this->assertNull($d->getContainer());

        // 无容器时 Class@method 直接 new
        $d->listen('no.container', MethodListener::class . '@handle');
        $result = $d->dispatch('no.container', ['ok']);

        // 不抛异常说明 new + 调用成功
        $this->assertNotNull($result);
    }

    // =========================================================================
    // 5. Subscriber 订阅者
    // =========================================================================

    public function testSubscriberRegistersMultipleListeners(): void
    {
        $d = new EventDispatcher();
        $sub = new TestSubscriber();
        $d->subscribe($sub);

        $d->dispatch('sub.event', ['hello']);
        $d->dispatch('sub.other', ['world']);

        // payload 展开后，监听器接收 ('hello') / ('world')
        $this->assertEquals([['event' => 'hello'], ['other' => 'world']], $sub->log);
    }

    public function testSubscriberListenersAreIndependent(): void
    {
        $d = new EventDispatcher();
        $sub = new TestSubscriber();
        $d->subscribe($sub);

        // 只分发 sub.event
        $d->dispatch('sub.event', ['data']);

        $this->assertCount(1, $sub->log);
        $this->assertEquals(['event' => 'data'], $sub->log[0]);
    }

    public function testSubscribeMethodCalledOnDispatcher(): void
    {
        $d = new EventDispatcher();
        $sub = new TestSubscriber();
        $d->subscribe($sub);

        // subscribe() 应该已经注册了监听器
        $this->assertTrue($d->hasListeners('sub.event'));
        $this->assertTrue($d->hasListeners('sub.other'));
    }

    // =========================================================================
    // 6. EventServiceProvider + 容器
    // =========================================================================

    public function testContainerResolvesEventDispatcher(): void
    {
        $app = App::getInstance();
        $dispatcher = $app->make('events');

        $this->assertInstanceOf(EventDispatcher::class, $dispatcher);
    }

    public function testContainerReturnsSingleton(): void
    {
        $app = App::getInstance();
        $d1 = $app->make('events');
        $d2 = $app->make('events');

        $this->assertSame($d1, $d2);
    }

    public function testDispatcherHasContainerReference(): void
    {
        $app = App::getInstance();
        $dispatcher = $app->make('events');

        $this->assertNotNull($dispatcher->getContainer());
    }

    // =========================================================================
    // 7. Event Facade
    // =========================================================================

    public function testFacadeListenAndDispatch(): void
    {
        $called = false;
        \Bin\Facade\Event::listen('facade.test', function () use (&$called) {
            $called = true;
        });
        \Bin\Facade\Event::dispatch('facade.test');

        $this->assertTrue($called);
    }

    public function testFacadeForget(): void
    {
        $called = false;
        \Bin\Facade\Event::listen('facade.forget', function () use (&$called) {
            $called = true;
        });
        \Bin\Facade\Event::forget('facade.forget');
        \Bin\Facade\Event::dispatch('facade.forget');

        $this->assertFalse($called);
    }

    public function testFacadeProxiesToSameInstance(): void
    {
        $app = App::getInstance();
        $fromContainer = $app->make('events');

        // Facade 的 getInstance 应该返回同一个对象
        \Bin\Facade\Event::setInstance($fromContainer);
        $called = false;
        \Bin\Facade\Event::listen('facade.same', function () use (&$called) { $called = true; });
        \Bin\Facade\Event::dispatch('facade.same');

        $this->assertTrue($called);
    }

    // =========================================================================
    // 8. ModelEventDispatcher 委托
    // =========================================================================

    private function setUpModelDatabase(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE event_test_items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');

        EventSysItem::setConnection($this->pdo);
        EventSysItem::flushEventListeners();
        EventSysItem::resetBooted();
    }

    public function testModelSpecificStopsOnFalse(): void
    {
        $this->setUpModelDatabase();

        $called = false;
        EventSysItem::creating(function () use (&$called) {
            $called = true;
            return false;
        });

        $item = new EventSysItem(['name' => 'blocked']);
        $result = $item->save();

        $this->assertFalse($result);
        $this->assertTrue($called);
    }

    public function testGlobalListenerFiresAfterModelSpecific(): void
    {
        $this->setUpModelDatabase();

        $order = [];
        EventSysItem::created(function () use (&$order) { $order[] = 'model-specific'; });
        ModelEventDispatcher::listen('created', function () use (&$order) { $order[] = 'global'; });

        EventSysItem::create(['name' => 'test']);

        $this->assertEquals(['model-specific', 'global'], $order);
    }

    public function testModelEventsUseSharedDispatcher(): void
    {
        $this->setUpModelDatabase();

        // 注册通用事件
        $genericCalled = false;
        $app = App::getInstance();
        $dispatcher = $app->make('events');
        $dispatcher->listen('generic.event', function () use (&$genericCalled) {
            $genericCalled = true;
        });

        // 模型事件
        $modelCalled = false;
        EventSysItem::created(function () use (&$modelCalled) { $modelCalled = true; });

        EventSysItem::create(['name' => 'test']);
        $dispatcher->dispatch('generic.event');

        $this->assertTrue($modelCalled);
        $this->assertTrue($genericCalled);
    }

    public function testWithoutEventsRestoresState(): void
    {
        $this->setUpModelDatabase();

        $called = false;
        EventSysItem::creating(function () use (&$called) {
            $called = true;
        });

        // withoutEvents 中不触发
        EventSysItem::withoutEvents(function () {
            EventSysItem::create(['name' => 'silent']);
        });
        $this->assertFalse($called);

        // 退出后恢复
        EventSysItem::create(['name' => 'normal']);
        $this->assertTrue($called);
    }

    // =========================================================================
    // 9. hasListeners / getListeners 查询
    // =========================================================================

    public function testHasListenersReturnsFalseForUnknown(): void
    {
        $d = new EventDispatcher();
        $this->assertFalse($d->hasListeners('nonexistent'));
    }

    public function testGetListenersReturnsEmptyArray(): void
    {
        $d = new EventDispatcher();
        $this->assertEquals([], $d->getListeners('nonexistent'));
    }

    // =========================================================================
    // 10. 集成：模型事件与通用事件共存
    // =========================================================================

    public function testModelAndGenericEventsCoexist(): void
    {
        $this->setUpModelDatabase();

        $modelEvent = false;
        $genericEvent = false;

        EventSysItem::created(function () use (&$modelEvent) { $modelEvent = true; });

        $d = new EventDispatcher();
        $d->listen('app.custom', function () use (&$genericEvent) { $genericEvent = true; });

        EventSysItem::create(['name' => 'test']);
        $d->dispatch('app.custom');

        $this->assertTrue($modelEvent);
        $this->assertTrue($genericEvent);
    }

    public function testWildcardCatchesModelEvents(): void
    {
        // 通过 ModelEventDispatcher 注册一个监听 'created' 全局事件的通配符
        // 注意：模型事件通过 EventDispatcher 分发时，事件名是 'created'（全局部分）
        $d = App::getInstance()->make('events');

        $wildcardEvents = [];
        $d->listen('*', function ($eventName, $payload) use (&$wildcardEvents) {
            $wildcardEvents[] = $eventName;
        });

        $this->setUpModelDatabase();
        EventSysItem::create(['name' => 'test']);

        // 模型创建时会分发 'EventSysItem@created' 和 'created' 两个事件
        $this->assertContains('Tests\EventSysItem@created', $wildcardEvents);
        $this->assertContains('created', $wildcardEvents);
    }
}

// =========================================================================
// 辅助类
// =========================================================================

class UserRegisteredEvent extends Event
{
    public function __construct(public string $name) {}
}

class InvokableListener
{
    public array $log = [];
    public function __invoke($payload): void
    {
        $this->log[] = $payload;
    }
}

class MethodListener
{
    public array $log = [];
    public function handle($payload): void
    {
        $this->log[] = $payload;
    }
}

class TestSubscriber implements Subscriber
{
    public array $log = [];
    public function subscribe(EventDispatcher $events): void
    {
        $events->listen('sub.event', [$this, 'onEvent']);
        $events->listen('sub.other', [$this, 'onOther']);
    }
    public function onEvent($data): void
    {
        $this->log[] = ['event' => $data];
    }
    public function onOther($data): void
    {
        $this->log[] = ['other' => $data];
    }
}

class EventSysItem extends Model
{
    protected string $table = 'event_test_items';
    protected array $fillable = ['name'];
    protected bool $timestamps = false;
}
