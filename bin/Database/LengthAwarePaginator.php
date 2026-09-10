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
 * 分页器类 - 支持完整的分页信息和导航
 */
class LengthAwarePaginator implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
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
     * 总记录数
     */
    protected int $total;

    /**
     * 最后页码
     */
    protected int $lastPage;

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
    public function __construct(array $items, int $total, int $perPage, int $currentPage = 1, array $options = [])
    {
        $this->items = $items;
        $this->total = $total;
        $this->perPage = $perPage;
        $this->currentPage = $currentPage;
        $this->lastPage = max((int) ceil($total / $perPage), 1);

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
     * 获取总记录数
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * 获取最后页码
     */
    public function lastPage(): int
    {
        return $this->lastPage;
    }

    /**
     * 是否有更多页
     */
    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    /**
     * 是否有上一页
     */
    public function onFirstPage(): bool
    {
        return $this->currentPage <= 1;
    }

    /**
     * 是否有下一页
     */
    public function onLastPage(): bool
    {
        return $this->currentPage >= $this->lastPage;
    }

    /**
     * 获取第一页 URL
     */
    public function firstPageUrl(): string
    {
        return $this->url(1);
    }

    /**
     * 获取最后一页 URL
     */
    public function lastPageUrl(): string
    {
        return $this->url($this->lastPage);
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
        if ($this->onLastPage()) {
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

        // 剥离 path 中可能携带的 query string，避免拼出 /search?q=foo?page=2
        $url = strtok($this->path, '?') ?: $this->path;

        if (!empty($query)) {
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
     * 获取页码范围
     */
    public function getPageRange(int $window = 3): array
    {
        $start = max(1, $this->currentPage - $window);
        $end = min($this->lastPage, $this->currentPage + $window);

        return range($start, $end);
    }

    /**
     * 获取页码链接数组
     */
    public function linkCollection(int $window = 3): array
    {
        $links = [];

        // 第一页
        if ($this->currentPage > $window + 1) {
            $links[] = [
                'url' => $this->url(1),
                'label' => 1,
                'active' => false,
            ];

            if ($this->currentPage > $window + 2) {
                $links[] = [
                    'url' => null,
                    'label' => '...',
                    'active' => false,
                ];
            }
        }

        // 当前页附近的页码
        foreach ($this->getPageRange($window) as $page) {
            $links[] = [
                'url' => $this->url($page),
                'label' => $page,
                'active' => $page === $this->currentPage,
            ];
        }

        // 最后一页
        if ($this->currentPage < $this->lastPage - $window) {
            if ($this->currentPage < $this->lastPage - $window - 1) {
                $links[] = [
                    'url' => null,
                    'label' => '...',
                    'active' => false,
                ];
            }

            $links[] = [
                'url' => $this->url($this->lastPage),
                'label' => $this->lastPage,
                'active' => false,
            ];
        }

        return $links;
    }

    /**
     * 渲染分页 HTML
     */
    public function render(int $window = 3): string
    {
        if ($this->lastPage <= 1) {
            return '';
        }

        $html = '<nav aria-label="pagination"><ul class="pagination">';

        // 上一页
        if ($this->onFirstPage()) {
            $html .= '<li class="disabled"><span>&laquo;</span></li>';
        } else {
            $html .= '<li><a href="' . htmlspecialchars($this->previousPageUrl()) . '">&laquo;</a></li>';
        }

        // 页码
        foreach ($this->linkCollection($window) as $link) {
            if ($link['url'] === null) {
                $html .= '<li class="disabled"><span>' . htmlspecialchars((string) $link['label']) . '</span></li>';
            } elseif ($link['active']) {
                $html .= '<li class="active"><span>' . $link['label'] . '</span></li>';
            } else {
                $html .= '<li><a href="' . htmlspecialchars($link['url']) . '">' . $link['label'] . '</a></li>';
            }
        }

        // 下一页
        if ($this->onLastPage()) {
            $html .= '<li class="disabled"><span>&raquo;</span></li>';
        } else {
            $html .= '<li><a href="' . htmlspecialchars($this->nextPageUrl()) . '">&raquo;</a></li>';
        }

        $html .= '</ul></nav>';

        return $html;
    }

    /**
     * 渲染简洁分页 HTML（只有上一页/下一页）
     */
    public function renderSimple(): string
    {
        if ($this->lastPage <= 1) {
            return '';
        }

        $html = '<nav aria-label="pagination"><ul class="pagination">';

        // 上一页
        if ($this->onFirstPage()) {
            $html .= '<li class="disabled"><span>&laquo; Previous</span></li>';
        } else {
            $html .= '<li><a href="' . htmlspecialchars($this->previousPageUrl()) . '">&laquo; Previous</a></li>';
        }

        // 下一页
        if ($this->onLastPage()) {
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
            'first_page_url' => $this->firstPageUrl(),
            'from' => $this->firstItem(),
            'last_page' => $this->lastPage,
            'last_page_url' => $this->lastPageUrl(),
            'links' => $this->linkCollection(),
            'next_page_url' => $this->nextPageUrl(),
            'path' => $this->path,
            'per_page' => $this->perPage,
            'prev_page_url' => $this->previousPageUrl(),
            'to' => $this->lastItem(),
            'total' => $this->total,
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