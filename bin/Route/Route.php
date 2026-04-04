<?php

declare(strict_types=1);

namespace Bin\Route;

use Bin\App\App;
use Bin\Request\Request;

class Route
{
    /** @var array<string, mixed> */
    private array $param = [];

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
     * 获取中间件
     */
    public function getMiddle(): ?array
    {
        return $this->param['middle'] ?? null;
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
     * 设置中间件
     */
    public function middle(array $middle): void
    {
        $this->mergeParam(['middle' => $middle]);
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


