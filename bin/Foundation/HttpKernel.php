<?php

declare(strict_types=1);

namespace Bin\Foundation;

use Bin\App\App;
use Bin\Middleware\Middleware;
use Bin\Middleware\MiddlewareStack;
use Bin\Middleware\Pipeline;
use Bin\Request\Request;
use Bin\Response\Response;
use Bin\Route\RouteAction;
use Bin\Route\RouteCollection;

/**
 * HTTP 内核
 *
 * 统一处理 HTTP 请求的 bootstrapping 和分发
 * 职责：
 *   1. 运行 HTTP 所需的引导器
 *   2. 将请求分发到路由 → 控制器
 */
class HttpKernel
{
    /**
     * 应用实例
     */
    protected App $app;

    /**
     * HTTP 引导器列表（按顺序执行）
     *
     * 顺序说明：
     * 1. 环境变量 → 2. 异常处理 → 3. 配置 → 4. 请求上下文
     * 5. Provider 注册 → 6. Provider 启动
     * @var array<class-string>
     */
    protected array $bootstrappers = [
        \Bin\Foundation\Bootstrap\LoadEnvironmentVariables::class,
        \Bin\Foundation\Bootstrap\HandleExceptions::class,
        \Bin\Foundation\Bootstrap\LoadConfiguration::class,
        \Bin\Foundation\Bootstrap\SetRequestContext::class,
        \Bin\Foundation\Bootstrap\RegisterProviders::class,
        \Bin\Foundation\Bootstrap\BootProviders::class,
        \Bin\Foundation\Bootstrap\LoadMiddlewareConfiguration::class,
        \Bin\Foundation\Bootstrap\LoadRoutes::class,
    ];

    /** @var Request|null 当前请求 */
    protected ?Request $currentRequest = null;

    /** @var Response|null 当前响应 */
    protected ?Response $currentResponse = null;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * 引导应用
     */
    protected function bootstrap(): void
    {
        $this->app->bootstrapWith($this->bootstrappers);
    }

    /**
     * 处理 HTTP 请求并返回响应
     */
    public function handle(): Response
    {
        $this->bootstrap();

        $this->currentRequest = Request::capture();
        $this->app->instance(Request::class, $this->currentRequest);

        // 通过路由系统分发请求
        $response = RouteAction::dispatch($this->currentRequest);

        $factory = $this->app->make(\Bin\Response\ResponseFactory::class);
        $this->currentResponse = $factory->make($response);

        return $this->currentResponse;
    }

    /**
     * 获取应用实例
     */
    public function getApp(): App
    {
        return $this->app;
    }

    /**
     * 在响应发送后调用 terminate 钩子
     *
     * 按逆序调用所有中间件的 terminate() 方法，
     * 用于日志记录、资源清理等后置处理。
     */
    public function terminate(): void
    {
        if ($this->currentRequest !== null && $this->currentResponse !== null) {
            RouteAction::terminate($this->currentRequest, $this->currentResponse);
        }
    }

    /**
     * 获取引导器列表
     *
     * @return array<class-string>
     */
    public function getBootstrappers(): array
    {
        return $this->bootstrappers;
    }

    /**
     * 设置引导器列表
     *
     * @param array<class-string> $bootstrappers
     */
    public function setBootstrappers(array $bootstrappers): void
    {
        $this->bootstrappers = $bootstrappers;
    }

    /**
     * 在指定引导器之前插入
     */
    public function prependBootstrapper(string $before, string $bootstrapper): void
    {
        $position = array_search($before, $this->bootstrappers, true);
        if ($position !== false) {
            array_splice($this->bootstrappers, (int)$position, 0, [$bootstrapper]);
        } else {
            $this->bootstrappers[] = $bootstrapper;
        }
    }

    /**
     * 在指定引导器之后插入
     */
    public function appendBootstrapper(string $after, string $bootstrapper): void
    {
        $position = array_search($after, $this->bootstrappers, true);
        if ($position !== false) {
            array_splice($this->bootstrappers, (int)$position + 1, 0, [$bootstrapper]);
        } else {
            $this->bootstrappers[] = $bootstrapper;
        }
    }

    // ================================================================
    // 中间件管理
    // ================================================================

    /**
     * 追加全局中间件
     */
    public function pushGlobalMiddleware(string $middleware): static
    {
        MiddlewareStack::getInstance()->addGlobal($middleware);
        return $this;
    }

    /**
     * 前置全局中间件
     */
    public function prependGlobalMiddleware(string $middleware): static
    {
        $stack = MiddlewareStack::getInstance();
        $stack->prependGlobal($middleware);
        return $this;
    }

    /**
     * 追加组中间件
     */
    public function pushMiddlewareToGroup(string $group, string $middleware): static
    {
        MiddlewareStack::getInstance()->addToGroup($group, $middleware);
        return $this;
    }

    /**
     * 前置组中间件
     */
    public function prependMiddlewareToGroup(string $group, string $middleware): static
    {
        MiddlewareStack::getInstance()->prependToGroup($group, $middleware);
        return $this;
    }

    /**
     * 注册中间件别名
     */
    public function middlewareAlias(string $name, string $class): static
    {
        MiddlewareStack::getInstance()->alias($name, $class);
        return $this;
    }

    /**
     * 设置中间件优先级
     *
     * @param array<string, int> $priority  别名/类名 => 优先级（值越大越先执行）
     */
    public function middlewarePriority(array $priority): static
    {
        MiddlewareStack::getInstance()->setPriority($priority);
        return $this;
    }

    /**
     * 获取 MiddlewareStack 实例
     */
    public function getMiddlewareStack(): MiddlewareStack
    {
        return MiddlewareStack::getInstance();
    }
}
