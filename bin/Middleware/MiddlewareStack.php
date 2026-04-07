<?php

declare(strict_types=1);

namespace Bin\Middleware;

/**
 * 中间件栈管理器
 *
 * 管理三种中间件：
 * 1. 全局中间件 — 每个请求都经过
 * 2. 中间件组 — 按组批量分配 (web/api)
 * 3. 路由中间件别名 — 短名到类名映射
 */
class MiddlewareStack
{
    private static ?self $instance = null;

    /** @var array<string> 全局中间件类名列表 */
    protected array $globals = [];

    /** @var array<string, array<string>> 中间件组 */
    protected array $groups = [];

    /** @var array<string, class-string<Middleware>> 中间件别名映射 */
    protected array $aliases = [];

    /** @var array<string, int> 中间件优先级排序（值越大越先执行） */
    protected array $priority = [];

    private function __construct()
    {
    }

    /**
     * 获取单例实例
     */
    public static function getInstance(): static
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    /**
     * 从配置文件加载中间件定义
     *
     * @param  array  $config  config/middleware.php 返回的数组
     */
    public static function loadFromConfig(array $config): static
    {
        $stack = static::getInstance();

        $stack->globals = $config['global'] ?? [];
        $stack->groups = $config['groups'] ?? [];
        $stack->aliases = $config['aliases'] ?? [];
        $stack->priority = $config['priority'] ?? [];

        return $stack;
    }

    /**
     * 获取全局中间件
     *
     * @return array<string>
     */
    public function getGlobals(): array
    {
        return $this->sortMiddleware($this->globals);
    }

    /**
     * 获取指定组的中间件
     *
     * @return array<string>
     */
    public function getGroup(string $group): array
    {
        return $this->sortMiddleware($this->groups[$group] ?? []);
    }

    /**
     * 解析中间件名为完整类名
     */
    public function resolveAlias(string $name): string
    {
        return $this->aliases[$name] ?? $name;
    }

    /**
     * 获取所有别名映射
     *
     * @return array<string, class-string<Middleware>>
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * 添加全局中间件
     */
    public function addGlobal(string $middleware): static
    {
        if (!in_array($middleware, $this->globals, true)) {
            $this->globals[] = $middleware;
        }

        return $this;
    }

    /**
     * 添加到指定组
     */
    public function addToGroup(string $group, string $middleware): static
    {
        if (!isset($this->groups[$group])) {
            $this->groups[$group] = [];
        }

        if (!in_array($middleware, $this->groups[$group], true)) {
            $this->groups[$group][] = $middleware;
        }

        return $this;
    }

    /**
     * 注册别名
     */
    public function alias(string $name, string $class): static
    {
        $this->aliases[$name] = $class;

        return $this;
    }

    /**
     * 收集路由所需的全部中间件
     *
     * 合并：全局 + 组 + 路由指定 - 排除
     *
     * @param  array<string>  $routeMiddleware  路由指定的中间件
     * @param  array<string>  $groups           路由使用的组
     * @param  array<string>  $excluded         要排除的中间件
     * @return array<string>  去重、排序后的中间件列表
     */
    public function collectRouteMiddleware(
        array $routeMiddleware = [],
        array $groups = [],
        array $excluded = []
    ): array {
        $middleware = $this->getGlobals();

        // 合并组中间件
        foreach ($groups as $group) {
            $middleware = array_merge($middleware, $this->getGroup($group));
        }

        // 合并路由指定中间件
        $middleware = array_merge($middleware, $routeMiddleware);

        // 排除
        if ($excluded !== []) {
            $middleware = array_filter($middleware, function (string $m) use ($excluded): bool {
                foreach ($excluded as $exclude) {
                    $excludeClass = $this->resolveAlias($exclude);
                    $mClass = $this->resolveAlias($m);
                    if ($mClass === $excludeClass || $m === $exclude) {
                        return false;
                    }
                }
                return true;
            });
        }

        // 去重 + 排序
        return array_values(array_unique($this->sortMiddleware($middleware)));
    }

    /**
     * 按优先级排序中间件
     */
    protected function sortMiddleware(array $middleware): array
    {
        if ($this->priority === []) {
            return $middleware;
        }

        usort($middleware, function (string $a, string $b): int {
            $aResolved = $this->resolveAlias($a);
            $bResolved = $this->resolveAlias($b);

            $aPri = $this->priority[$a] ?? $this->priority[$aResolved] ?? 0;
            $bPri = $this->priority[$b] ?? $this->priority[$bResolved] ?? 0;

            return $bPri <=> $aPri; // 值越大越前
        });

        return $middleware;
    }

    /**
     * 重置（用于测试）
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
