<?php

declare(strict_types=1);

namespace Bin\Foundation;

use Bin\App\App;
use Bin\Console\Kernel as ConsoleKernelBase;

/**
 * Console 内核
 *
 * 统一处理 CLI 命令的 bootstrapping 和分发
 * 职责：
 *   1. 运行 CLI 所需的引导器（不含路由加载）
 *   2. 委托给 Console\Kernel 进行命令发现和分发
 *   3. 提供程序化调用入口（确保 bootstrapping）
 */
class ConsoleKernel
{
    /**
     * 应用实例
     */
    protected App $app;

    /**
     * Console 引导器列表（按顺序执行）
     *
     * 顺序说明：
     * 1. 环境变量 → 2. 异常处理 → 3. 配置
     * 4. Provider 注册 → 5. Provider 启动
     * 注意：不需要 SetRequestContext（CLI 不需要时区/编码设置）
     * @var array<class-string>
     */
    protected array $bootstrappers = [
        \Bin\Foundation\Bootstrap\LoadEnvironmentVariables::class,
        \Bin\Foundation\Bootstrap\HandleExceptions::class,
        \Bin\Foundation\Bootstrap\LoadConfiguration::class,
        \Bin\Foundation\Bootstrap\RegisterProviders::class,
        \Bin\Foundation\Bootstrap\BootProviders::class,
    ];

    /**
     * 是否已引导
     */
    protected bool $bootstrapped = false;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * 引导应用
     */
    public function bootstrap(): void
    {
        if ($this->bootstrapped) {
            return;
        }

        if (!$this->app->hasBeenBootstrapped()) {
            $this->app->bootstrapWith($this->bootstrappers);
        }

        $this->bootstrapped = true;
    }

    /**
     * 处理 CLI 请求（主入口）
     */
    public function handle(): int
    {
        $this->bootstrap();

        ConsoleKernelBase::setConsoleKernel($this);

        return ConsoleKernelBase::handle();
    }

    /**
     * 程序化调用命令（经过 bootstrapping）
     */
    public function call(string $command, array $arguments = []): int
    {
        $this->bootstrap();

        return ConsoleKernelBase::call($command, $arguments);
    }

    /**
     * 获取应用实例
     */
    public function getApp(): App
    {
        return $this->app;
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
     * 前置引导器
     */
    public function prependBootstrapper(string $bootstrapper): void
    {
        array_unshift($this->bootstrappers, $bootstrapper);
    }

    /**
     * 后置引导器
     */
    public function appendBootstrapper(string $bootstrapper): void
    {
        $this->bootstrappers[] = $bootstrapper;
    }
}
