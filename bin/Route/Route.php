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

    /** @var array<string, string> 参数正则约束 */
    private array $wheres = [];

    /** @var array<string, mixed>|null 匹配的参数（延迟设置到 Request） */
    private ?array $matchedParams = null;

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
     * 设置路由名称（触发命名注册，自动添加组前缀）
     */
    public function name(string $name): self
    {
        RouteCollection::registerNamedRoute($name, $this);
        return $this;
    }

    /**
     * 直接设置完整名称（由 registerNamedRoute 调用，不触发递归）
     */
    public function setRawName(string $name): void
    {
        $this->param['name'] = $name;
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
     *
     * 支持：
     *   $route->where('id', '[0-9]+')
     *   $route->where(['id' => '[0-9]+', 'slug' => '[a-z]+'])
     */
    public function where(string|array $param, ?string $pattern = null): self
    {
        if (is_array($param)) {
            foreach ($param as $key => $value) {
                $this->wheres[$key] = $value;
                if (!isset($this->param['where'])) {
                    $this->param['where'] = [];
                }
                $this->param['where'][$key] = $value;
            }
        } else {
            $this->wheres[$param] = $pattern;
            if (!isset($this->param['where'])) {
                $this->param['where'] = [];
            }
            $this->param['where'][$param] = $pattern;
        }
        return $this;
    }

    /**
     * 获取所有 where 约束
     *
     * @return array<string, string>
     */
    public function getWheres(): array
    {
        return $this->wheres;
    }

    /**
     * 合并 where 约束（用于组属性继承）
     */
    public function mergeWheres(array $wheres): void
    {
        foreach ($wheres as $key => $pattern) {
            if (!isset($this->wheres[$key])) {
                $this->wheres[$key] = $pattern;
            }
        }
        $this->param['where'] = array_merge($wheres, $this->param['where'] ?? []);
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

        // where 约束匹配（优先于旧 preg 模式）
        if ($this->wheres !== []) {
            return $this->matchWithConstraints($url);
        }

        // 旧正则匹配
        return $this->pregMatch($url);
    }

    /**
     * 使用 where 约束匹配动态路由
     *
     * 将路径模式如 /user/{id} 编译为正则，每个参数使用 where 约束或默认 [^/]+
     */
    private function matchWithConstraints(string $url): bool
    {
        $pathPattern = $this->getPath();

        // 编译路径为正则
        $regex = preg_replace_callback(
            '/\{(\w+)\}/',
            function (array $matches): string {
                $param = $matches[1];
                $constraint = $this->wheres[$param] ?? '[^/]+';
                return '(' . $constraint . ')';
            },
            $pathPattern
        );

        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $url, $matches) > 0) {
            $this->matchedParams = $this->extractParams($pathPattern, $matches);
            return true;
        }

        return false;
    }

    /**
     * 从正则匹配结果中提取命名参数
     *
     * @return array<string, string>
     */
    private function extractParams(string $pathPattern, array $matches): array
    {
        preg_match_all('/\{(\w+)\}/', $pathPattern, $paramNames);
        $params = [];
        foreach ($paramNames[1] as $index => $name) {
            if (isset($matches[$index + 1])) {
                $params[$name] = $matches[$index + 1];
            }
        }
        return $params;
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
            $this->matchedParams = explode('/', $matches[0]);
            return true;
        }

        return false;
    }

    /**
     * 判断 url 是否满足正则（向后兼容）
     *
     * 匹配成功后将参数设置到 Request
     */
    public function withSuccess(string $url): bool
    {
        $this->matchedParams = null;

        if ($this->matches($url)) {
            if ($this->matchedParams !== null && $this->matchedParams !== []) {
                App::getInstance()->make(Request::class)->setUrlParam($this->matchedParams);
            }
            return true;
        }

        return false;
    }

    /**
     * 生成该路由的 URL
     */
    public function url(array $params = []): string
    {
        $segments = explode('/', trim($this->getPath(), '/'));
        $missing = [];
        $resolvedSegments = [];

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            preg_match_all('/\{([^}]+)\}/', $segment, $matches);

            if ($matches[1] === []) {
                $resolvedSegments[] = $segment;
                continue;
            }

            $resolvedSegment = $segment;
            $skipSegment = false;

            foreach ($matches[1] as $raw) {
                $optional = str_ends_with($raw, '?');
                $name = rtrim($raw, '?');

                if (array_key_exists($name, $params)) {
                    $resolvedSegment = str_replace('{' . $raw . '}', (string) $params[$name], $resolvedSegment);
                    continue;
                }

                if ($optional) {
                    if ($segment === '{' . $raw . '}') {
                        $skipSegment = true;
                        break;
                    }

                    $resolvedSegment = str_replace('{' . $raw . '}', '', $resolvedSegment);
                    continue;
                }

                $missing[] = $name;
            }

            if (!$skipSegment && $resolvedSegment !== '') {
                $resolvedSegments[] = $resolvedSegment;
            }
        }

        if ($missing !== []) {
            throw \Bin\Exception\UrlGenerationException::forMissingParameters($this->getPath(), $missing);
        }

        if ($resolvedSegments === []) {
            return '/';
        }

        return '/' . implode('/', $resolvedSegments);
    }
}
