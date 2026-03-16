<?php

declare(strict_types=1);

namespace Bin\Route;

use Bin\App\App;
use Bin\Request\Request;

class Route
{
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
     * 合并数据
     * @param array $param
     */
    private function mergeParam(array $param): void
    {
        $this->param = array_merge($this->param, $param);
    }

    /**
     * 为其中一项数据添加数据
     * @param array $param
     */
    private function addParam(string $key, mixed $param): void
    {
        if (!isset($this->param[$key])) {
            $this->param[$key] = [];
        }

        $this->param[$key][] = $param;
    }

    /**
     * 获取当前的 url
     */
    public function getPath(): string
    {
        return $this->param['path'];
    }

    /**
     * 获取当前的 METHOD
     */
    public function getMethod(): string
    {
        return $this->param['method'];
    }


    /**
     * 获取当前的 action
     */
    public function getAction(): mixed
    {
        return $this->param['action'];
    }


    /**
     * 获取当前的 middle
     */
    public function getMiddle(): ?array
    {
        return $this->param['middle'] ?? null;
    }

    /**
     * 判断 url 是否满足正则
     * @param string $preg
     * @return $this
     */
    public function with(string $preg): self
    {
        $this->addParam('preg', $preg);
        return $this;
    }

    /**
     * 设置 middle
     * @param array $middle
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
     * 判断 url 是否满足正则
     * @param string $url
     * @return bool
     */
    public function withSuccess(string $url): bool
    {
        // 匹配 url
        $prefixString = preg_replace('/\{.+\}/', '', $this->getPath());
        $prefixStringAy = explode('/', $prefixString);
        // 去掉一个空白元素
        '' === $prefixStringAy[0] && array_shift($prefixStringAy);
        $string = preg_replace('/' . join('\/', $prefixStringAy) . '[\/]?/', '', $url);

        if (strlen($string) < 1) {
            return false;
        }
        if ($string[0] === '/') {
            $string = substr($string, 1);
        }

        // url 模式匹配
        if (preg_match('/^' . join('\/', $this->getPreg()) . '$/', $string, $out) > 0) {
            App::make(Request::class)->setUrlParam(explode('/', $out[0]));
            return true;
        }

        return false;
    }


}

