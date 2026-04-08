<?php

declare(strict_types=1);

namespace Bin\Route;

use Bin\App\App;
use Bin\Request\Request;

class Route
{
    /** @var array<string, mixed> */
    private array $param = [];

    /** @var array<string> 中间件列表（短名或类名，支持 "throttle:60,1" 格式） */
    private array $middleware = [];

    /** @var array<string> 要排除的中间件 */
    private array $excludedMiddleware = [];

    /** @var array<string> 使用的中间件组 */
    private array $middlewareGroups = [];

    public function __construct(
        private string $method,
        private string $path,
        private mixed $action
    ) {
        $this->param = [
            'method' => $method,
            'path' => $path,
            'action' => $action,
        ];
    }

    /**
     * 合并参数
     */
    private function mergeParam(array $param): void
    {
        $this->param = array_merge($this->param, $param);
    }

    /**
     * 添加参数到指定键
     */
    private function addParam(string $key, mixed $param): void
    {
        if (!isset($this->param[$key])) {
            $this->param[$key] = [];
        }
        $this->param[$key][] = $param;
    }

    /**
     * 获取路径
     */
    public function getPath(): string
    {
        return $this->param['path'];
    }

    /**
     * 更新路径（路由组前缀场景）
     */
    public function updatePath(string $path): void
    {
        $this->param['path'] = $path;
        $this->path = $path;
    }

    /**
     * 获取方法
     */
    public function getMethod(): string
    {
        return $this->param['method'];
    }

    /**
     * 获取 action
     */
    public function getAction(): mixed
    {
        return $this->param['action'];
    }

    /**
     * 设置 action（路由组 namespace 场景）
     */
    public function setAction(mixed $action): void
    {
        $this->param['action'] = $action;
        $this->action = $action;
    }

    /**
     * 设置域名约束
     */
    public function setDomain(string $domain): void
    {
        $this->param['domain'] = $domain;
    }

    /**
     * 获取域名约束
     */
    public function getDomain(): ?string
    {
        return $this->param['domain'] ?? null;
    }

    /**
     * 获取中间件（兼容旧 API）
     *
     * @deprecated 使用 getMiddleware() 替代
     */
    public function getMiddle(): ?array
    {
        return $this->param['middle'] ?? null;
    }

    /**
     * 获取中间件列表
     *
     * @return array<string>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * 获取排除的中间件
     *
     * @return array<string>
     */
    public function getExcludedMiddleware(): array
    {
        return $this->excludedMiddleware;
    }

    /**
     * 获取中间件组
     *
     * @return array<string>
     */
    public function getMiddlewareGroups(): array
    {
        return $this->middlewareGroups;
    }

    /**
     * 获取路由名称
     */
    public function getName(): ?string
    {
        return $this->param['name'] ?? null;
    }

    /**
     * 设置路由名称
     */
    public function name(string $name): self
    {
        $this->param['name'] = $name;
        RouteCollection::registerNamedRoute($name, $this);
        return $this;
    }

    /**
     * 添加正则约束
     */
    public function with(string $pattern): self
    {
        $this->addParam('preg', $pattern);
        return $this;
    }

    /**
     * 为指定参数添加正则约束
     */
    public function where(string $param, string $pattern): self
    {
        if (!isset($this->param['where'])) {
            $this->param['where'] = [];
        }
        $this->param['where'][$param] = $pattern;
        return $this;
    }

    /**
     * 设置中间件（兼容旧 API）
     *
     * @deprecated 使用 middleware() 替代
     */
    public function middle(array $middle): void
    {
        $this->mergeParam(['middle' => $middle]);

        // 同时维护新的 middleware 列表
        if (isset($middle['middle'])) {
            foreach ($middle['middle'] as $key => $value) {
                // 旧格式：['middle' => ['auth' => [...]]]
                if (is_string($key)) {
                    if (!in_array($key, $this->middleware, true)) {
                        $this->middleware[] = $key;
                    }
                } else {
                    if (!in_array($value, $this->middleware, true)) {
                        $this->middleware[] = $value;
                    }
                }
            }
        }
    }

    /**
     * 设置中间件（新 API，支持链式调用）
     *
     * 用法：
     *   $route->middleware('auth')
     *   $route->middleware('throttle:60,1')
     *   $route->middleware(['auth', 'throttle:60,1'])
     */
    public function middleware(string|array $middleware): static
    {
        $middleware = is_array($middleware) ? $middleware : [$middleware];

        foreach ($middleware as $m) {
            if (!in_array($m, $this->middleware, true)) {
                $this->middleware[] = $m;
            }
        }

        return $this;
    }

    /**
     * 排除中间件
     *
     * 用法：
     *   $route->withoutMiddleware('csrf')
     */
    public function withoutMiddleware(string|array $middleware): static
    {
        $middleware = is_array($middleware) ? $middleware : [$middleware];

        foreach ($middleware as $m) {
            if (!in_array($m, $this->excludedMiddleware, true)) {
                $this->excludedMiddleware[] = $m;
            }
        }

        return $this;
    }

    /**
     * 指定中间件组
     *
     * 用法：
     *   $route->middlewareGroup('web')
     */
    public function middlewareGroup(string|array $groups): static
    {
        $groups = is_array($groups) ? $groups : [$groups];

        foreach ($groups as $group) {
            if (!in_array($group, $this->middlewareGroups, true)) {
                $this->middlewareGroups[] = $group;
            }
        }

        return $this;
    }

    /**
     * 获取正则表达式
     */
    public function getPreg(): ?array
    {
        return $this->param['preg'] ?? null;
    }

    /**
     * 检查 URL 是否匹配
     */
    public function matches(string $url): bool
    {
        // 精确匹配
        if ($this->getPath() === $url) {
            return true;
        }

        // 正则匹配
        return $this->pregMatch($url);
    }

    /**
     * 正则匹配
     */
    private function pregMatch(string $url): bool
    {
        $patterns = $this->getPreg();
        if ($patterns === null) {
            return false;
        }

        // 提取路径前缀
        $prefix = preg_replace('/\{.+\}/', '', $this->getPath());
        $segments = array_filter(explode('/', $prefix));

        if ($segments !== []) {
            $pattern = '/^' . implode('\\/', array_map('preg_quote', $segments)) . '[\\/]?/';
            $url = preg_replace($pattern, '', $url, 1);
        }

        if ($url === '' || $url === $this->getPath()) {
            return false;
        }

        if ($url[0] === '/') {
            $url = substr($url, 1);
        }

        // 匹配参数
        $fullPattern = '/^' . implode('\\/', $patterns) . '$/';
        if (preg_match($fullPattern, $url, $matches) > 0) {
            App::getInstance()->make(Request::class)->setUrlParam(explode('/', $matches[0]));
            return true;
        }

        return false;
    }

    /**
     * 判断 url 是否满足正则（向后兼容）
     */
    public function withSuccess(string $url): bool
    {
        return $this->matches($url);
    }

    /**
     * 生成该路由的 URL
     */
    public function url(array $params = []): string
    {
        $url = $this->getPath();

        foreach ($params as $key => $value) {
            $url = str_replace('{' . $key . '}', (string) $value, $url);
        }

        return '/' . trim($url, '/');
    }
}
