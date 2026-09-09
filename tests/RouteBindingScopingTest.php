<?php

declare(strict_types=1);

namespace Tests;

use Bin\App\App;
use Bin\Database\Model;
use Bin\Exception\NotFoundHttpException;
use Bin\Request\Request;
use Bin\Routing\ControllerDispatcher;
use Bin\Route\Route;
use Bin\Route\RouteAction;
use Bin\Testing\TestCase;
use PDO;

/**
 * 隐式绑定 scoped 嵌套约束与 missing 回调测试
 */
class RouteBindingScopingTest extends TestCase
{
    private ControllerDispatcher $dispatcher;
    protected ?PDO $connection = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = new ControllerDispatcher();

        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->connection->exec('
            CREATE TABLE scoped_blog_posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL
            )
        ');
        $this->connection->exec('
            CREATE TABLE scoped_blog_comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                post_id INTEGER NOT NULL,
                content TEXT NOT NULL
            )
        ');
        $this->connection->exec('
            CREATE TABLE scoped_alt_comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                blog_id INTEGER NOT NULL,
                content TEXT NOT NULL
            )
        ');

        ScopedBlogPost::resetBooted();
        ScopedBlogPost::setConnection($this->connection);
        ScopedBlogComment::resetBooted();
        ScopedBlogComment::setConnection($this->connection);
        ScopedAltComment::resetBooted();
        ScopedAltComment::setConnection($this->connection);

        // post 1 有评论 1；post 2 有评论 2、3
        $this->connection->exec("INSERT INTO scoped_blog_posts (title) VALUES ('p1'), ('p2')");
        $this->connection->exec("INSERT INTO scoped_blog_comments (post_id, content) VALUES (1, 'c1'), (2, 'c2'), (2, 'c3')");
        // blog 1 有评论 10；blog 2 有评论 11
        $this->connection->exec("INSERT INTO scoped_alt_comments (id, blog_id, content) VALUES (10, 1, 'a1'), (11, 2, 'a2')");

        $property = new \ReflectionProperty(RouteAction::class, 'dispatcher');
        $property->setValue(null, null);
    }

    protected function tearDown(): void
    {
        ScopedBlogPost::resetBooted();
        ScopedBlogPost::setConnection(null);
        ScopedBlogComment::resetBooted();
        ScopedBlogComment::setConnection(null);
        ScopedAltComment::resetBooted();
        ScopedAltComment::setConnection(null);

        App::getInstance()->forget(Request::class);
        App::getInstance()->singleton(Request::class, Request::class);
        $property = new \ReflectionProperty(RouteAction::class, 'dispatcher');
        $property->setValue(null, null);
        parent::tearDown();
    }

    protected function seedUrlParams(array $params): void
    {
        // 必须经 App::getInstance() 注册：App 构造时会用自己的内部容器
        // 顶掉 Container 全局实例，先注册到旧容器会丢失绑定
        $request = Request::capture();
        $request->setUrlParam($params);
        App::getInstance()->instance(Request::class, $request);
    }

    // ================================================================
    // scoped 嵌套绑定
    // ================================================================

    public function testScopedBindingResolvesCommentWithinParent(): void
    {
        $this->seedUrlParams(['post' => '2', 'comment' => '3']);

        $closure = fn (ScopedBlogComment $comment): string => 'comment=' . $comment->id;

        $route = new Route('GET', '/posts/{post}/comments/{comment}', $closure);
        $route->scoped(['comment' => 'post']);

        $result = $this->dispatcher->dispatchClosure($closure, $route);

        $this->assertEquals('comment=3', $result->getContent());
    }

    public function testScopedBindingRejectsCommentFromOtherParent(): void
    {
        $this->seedUrlParams(['post' => '1', 'comment' => '3']);

        $closure = fn (ScopedBlogComment $comment): string => 'comment=' . $comment->id;

        $route = new Route('GET', '/posts/{post}/comments/{comment}', $closure);
        $route->scoped(['comment' => 'post']);

        $this->assertThrows(NotFoundHttpException::class, function () use ($closure, $route): void {
            $this->dispatcher->dispatchClosure($closure, $route);
        });
    }

    public function testUnscopedBindingIgnoresParentConstraint(): void
    {
        $this->seedUrlParams(['post' => '1', 'comment' => '3']);

        $closure = fn (ScopedBlogComment $comment): string => 'comment=' . $comment->id;

        $route = new Route('GET', '/posts/{post}/comments/{comment}', $closure);

        $result = $this->dispatcher->dispatchClosure($closure, $route);

        $this->assertEquals('comment=3', $result->getContent());
    }

    public function testScopedBindingWithCustomForeignKey(): void
    {
        $this->seedUrlParams(['post' => '2', 'comment' => '11']);

        $closure = fn (ScopedAltComment $comment): string => 'alt=' . $comment->id;

        $route = new Route('GET', '/posts/{post}/alt-comments/{comment}', $closure);
        $route->scoped(['comment' => ['post' => 'blog_id']]);

        $result = $this->dispatcher->dispatchClosure($closure, $route);

        $this->assertEquals('alt=11', $result->getContent());
    }

    public function testScopedBindingWithCustomForeignKeyRejectsOtherParent(): void
    {
        $this->seedUrlParams(['post' => '1', 'comment' => '11']);

        $closure = fn (ScopedAltComment $comment): string => 'alt=' . $comment->id;

        $route = new Route('GET', '/posts/{post}/alt-comments/{comment}', $closure);
        $route->scoped(['comment' => ['post' => 'blog_id']]);

        $this->assertThrows(NotFoundHttpException::class, function () use ($closure, $route): void {
            $this->dispatcher->dispatchClosure($closure, $route);
        });
    }

    // ================================================================
    // missing 回调
    // ================================================================

    public function testMissingCallbackReplacesNotFoundResponse(): void
    {
        $this->seedUrlParams(['comment' => '999']);

        $closure = fn (ScopedBlogComment $comment): string => 'comment=' . $comment->id;

        $route = new Route('GET', '/comments/{comment}', $closure);
        $route->missing(fn (NotFoundHttpException $e): string => 'redirected-home');

        $result = $this->dispatcher->dispatchClosure($closure, $route);

        $this->assertEquals('redirected-home', $result->getContent());
    }

    public function testMissingCallbackReceivesNotFoundException(): void
    {
        $this->seedUrlParams(['comment' => '999']);

        $closure = fn (ScopedBlogComment $comment): string => 'comment=' . $comment->id;
        $received = null;

        $route = new Route('GET', '/comments/{comment}', $closure);
        $route->missing(function (NotFoundHttpException $e) use (&$received): string {
            $received = $e;
            return 'missing-handled';
        });

        $result = $this->dispatcher->dispatchClosure($closure, $route);

        $this->assertEquals('missing-handled', $result->getContent());
        $this->assertInstanceOf(NotFoundHttpException::class, $received);
    }

    public function testMissingCallbackWorksOnControllerDispatch(): void
    {
        $this->seedUrlParams(['comment' => '999']);

        $controller = new class {
            public function show(ScopedBlogComment $comment): string
            {
                return 'comment=' . $comment->id;
            }
        };
        $className = get_class($controller);
        App::getInstance()->instance($className, $controller);

        $route = new Route('GET', '/comments/{comment}', $className . '@show');
        $route->missing(fn (NotFoundHttpException $e): string => 'controller-missing');

        $result = $this->dispatcher->dispatch($className, 'show', $route);

        $this->assertEquals('controller-missing', $result->getContent());
    }

    public function testWithoutMissingCallbackNotFoundStillThrown(): void
    {
        $this->seedUrlParams(['comment' => '999']);

        $closure = fn (ScopedBlogComment $comment): string => 'comment=' . $comment->id;

        $route = new Route('GET', '/comments/{comment}', $closure);

        $this->assertThrows(NotFoundHttpException::class, function () use ($closure, $route): void {
            $this->dispatcher->dispatchClosure($closure, $route);
        });
    }

    public function testScopedStateDoesNotLeakAcrossDispatches(): void
    {
        // 第一次调度触发 missing 短路
        $this->seedUrlParams(['comment' => '999']);
        $closure = fn (ScopedBlogComment $comment): string => 'comment=' . $comment->id;
        $route = new Route('GET', '/comments/{comment}', $closure);
        $route->missing(fn (NotFoundHttpException $e): string => 'first-missing');
        $this->assertEquals('first-missing', $this->dispatcher->dispatchClosure($closure, $route)->getContent());

        // 第二次正常调度不受上一次 missingResponse 影响
        $this->seedUrlParams(['comment' => '1']);
        $route2 = new Route('GET', '/comments/{comment}', $closure);
        $this->assertEquals('comment=1', $this->dispatcher->dispatchClosure($closure, $route2)->getContent());
    }
}

class ScopedBlogPost extends Model
{
    protected string $table = 'scoped_blog_posts';
    protected array $fillable = ['title'];
    protected bool $timestamps = false;
}

class ScopedBlogComment extends Model
{
    protected string $table = 'scoped_blog_comments';
    protected array $fillable = ['post_id', 'content'];
    protected bool $timestamps = false;
}

class ScopedAltComment extends Model
{
    protected string $table = 'scoped_alt_comments';
    protected array $fillable = ['blog_id', 'content'];
    protected bool $timestamps = false;
}
