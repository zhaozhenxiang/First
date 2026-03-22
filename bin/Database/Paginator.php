<?php

declare(strict_types=1);

namespace Bin\Database;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;
use ArrayIterator;

/**
 * 简单分页器 - 不统计总数，适用于大数据集
 */
class Paginator implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * 数据项
     */
    protected array $items = [];

    /**
     * 每页数量
     */
    protected int $perPage;

    /**
     * 当前页
     */
    protected int $currentPage;

    /**
     * 是否有更多页
     */
    protected bool $hasMore;

    /**
     * URL 路径
     */
    protected string $path = '/';

    /**
     * 查询参数名
     */
    protected string $pageName = 'page';

    /**
     * 额外查询参数
     */
    protected array $query = [];

    /**
     * 片段标识
     */
    protected ?string $fragment = null;

    /**
     * 构造函数
     */
    public function __construct(
        array $items,
        int $perPage,
        int $currentPage = 1,
        array $options = [],
        bool $hasMore = false
    ) {
        $this->items = $items;
        $this->perPage = $perPage;
        $this->currentPage = $currentPage;
        $this->hasMore = $hasMore;

        if (isset($options['path'])) {
            $this->path = $options['path'];
        }

        if (isset($options['pageName'])) {
            $this->pageName = $options['pageName'];
        }

        if (isset($options['query'])) {
            $this->query = $options['query'];
        }

        if (isset($options['fragment'])) {
            $this->fragment = $options['fragment'];
        }
    }

    /**
     * 获取数据项
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * 获取每页数量
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * 获取当前页
     */
    public function currentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * 是否有更多页
     */
    public function hasMorePages(): bool
    {
        return $this->hasMore;
    }

    /**
     * 是否有上一页
     */
    public function onFirstPage(): bool
    {
        return $this->currentPage <= 1;
    }

    /**
     * 获取上一页 URL
     */
    public function previousPageUrl(): ?string
    {
        if ($this->onFirstPage()) {
            return null;
        }

        return $this->url($this->currentPage - 1);
    }

    /**
     * 获取下一页 URL
     */
    public function nextPageUrl(): ?string
    {
        if (!$this->hasMore) {
            return null;
        }

        return $this->url($this->currentPage + 1);
    }

    /**
     * 获取指定页的 URL
     */
    public function url(int $page): string
    {
        if ($page <= 0) {
            $page = 1;
        }

        $query = $this->query;

        if ($page > 1) {
            $query[$this->pageName] = $page;
        }

        $url = $this->path;

        if (!empty($query)) {
            // 移除路径中的查询字符串
            $url = strtok($url, '?');
            $url .= '?' . http_build_query($query);
        }

        if ($this->fragment !== null) {
            $url .= '#' . $this->fragment;
        }

        return $url;
    }

    /**
     * 设置 URL 路径
     */
    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * 设置查询参数名
     */
    public function setPageName(string $name): self
    {
        $this->pageName = $name;
        return $this;
    }

    /**
     * 设置额外查询参数
     */
    public function appends(array|string $key, mixed $value = null): self
    {
        if (is_array($key)) {
            $this->query = array_merge($this->query, $key);
        } else {
            $this->query[$key] = $value;
        }

        return $this;
    }

    /**
     * 设置片段标识
     */
    public function fragment(?string $fragment): self
    {
        $this->fragment = $fragment;
        return $this;
    }

    /**
     * 渲染分页 HTML
     */
    public function render(): string
    {
        $html = '<nav aria-label="pagination"><ul class="pagination">';

        // 上一页
        if ($this->onFirstPage()) {
            $html .= '<li class="disabled"><span>&laquo; Previous</span></li>';
        } else {
            $html .= '<li><a href="' . htmlspecialchars($this->previousPageUrl()) . '">&laquo; Previous</a></li>';
        }

        // 下一页
        if (!$this->hasMore) {
            $html .= '<li class="disabled"><span>Next &raquo;</span></li>';
        } else {
            $html .= '<li><a href="' . htmlspecialchars($this->nextPageUrl()) . '">Next &raquo;</a></li>';
        }

        $html .= '</ul></nav>';

        return $html;
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'current_page' => $this->currentPage,
            'data' => $this->items,
            'first_page_url' => $this->url(1),
            'from' => $this->firstItem(),
            'next_page_url' => $this->nextPageUrl(),
            'path' => strtok($this->path, '?'),
            'per_page' => $this->perPage,
            'prev_page_url' => $this->previousPageUrl(),
            'to' => $this->lastItem(),
        ];
    }

    /**
     * 转换为 JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * JSON 序列化
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * 获取第一条记录的索引
     */
    public function firstItem(): ?int
    {
        if (empty($this->items)) {
            return null;
        }

        return ($this->currentPage - 1) * $this->perPage + 1;
    }

    /**
     * 获取最后一条记录的索引
     */
    public function lastItem(): ?int
    {
        if (empty($this->items)) {
            return null;
        }

        return $this->firstItem() + count($this->items) - 1;
    }

    // ArrayAccess 接口实现

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->items);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->items[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    // Countable 接口实现

    public function count(): int
    {
        return count($this->items);
    }

    // IteratorAggregate 接口实现

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}