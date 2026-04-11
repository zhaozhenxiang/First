<?php

declare(strict_types=1);

namespace Bin\Foundation;

use Bin\App\App;
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
 *   2. 加载路由定义
 *   3. 加载中间件配置
 *   4. 将请求分发到路由 → 控制器
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
    ];

    /**
     * 中间件配置
     * @var array<string, mixed>
     */
    protected array $middlewareConfig = [];

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * 引导应用
     */
    protected function bootstrap(): void
    {
        if (!$this->app->hasBeenBootstrapped()) {
            $this->app->bootstrapWith($this->bootstrappers);
        }

        // 加载中间件配置
        $this->loadMiddleware();

        // 加载路由定义
        $this->loadRoutes();
    }

    /**
     * 处理 HTTP 请求并返回响应
     */
    public function handle(): Response
    {
        $this->bootstrap();

        // 通过路由系统分发请求
        $response = RouteAction::action();

        return $response instanceof Response ? $response : new Response((string)$response);
    }

    /**
     * 加载中间件配置
     */
    protected function loadMiddleware(): void
    {
        $configPath = $this->app->configPath('middleware.php');

        if (file_exists($configPath)) {
            $this->middlewareConfig = require $configPath;
            \Bin\Middleware\MiddlewareStack::loadFromConfig($this->middlewareConfig);
        }
    }

    /**
     * 加载路由定义
     */
    protected function loadRoutes(): void
    {
        $routeFile = $this->app->basePath() . '/app/routes.php';

        if (file_exists($routeFile)) {
            require_once $routeFile;
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
}
