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
 * 游标分页器 - 适用于无限滚动和大数据集
 */
class CursorPaginator implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
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
     * 当前游标
     */
    protected ?string $cursor;

    /**
     * 下一页游标
     */
    protected ?string $nextCursor;

    /**
     * URL 路径
     */
    protected string $path = '/';

    /**
     * 游标参数名
     */
    protected string $cursorName = 'cursor';

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
        ?string $cursor = null,
        ?string $nextCursor = null,
        array $options = []
    ) {
        $this->items = $items;
        $this->perPage = $perPage;
        $this->cursor = $cursor;
        $this->nextCursor = $nextCursor;

        if (isset($options['path'])) {
            $this->path = $options['path'];
        }

        if (isset($options['cursorName'])) {
            $this->cursorName = $options['cursorName'];
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
     * 获取当前游标
     */
    public function cursor(): ?string
    {
        return $this->cursor;
    }

    /**
     * 获取下一页游标
     */
    public function nextCursor(): ?string
    {
        return $this->nextCursor;
    }

    /**
     * 是否有更多页
     */
    public function hasMorePages(): bool
    {
        return $this->nextCursor !== null;
    }

    /**
     * 获取上一页 URL
     */
    public function previousPageUrl(): ?string
    {
        // 游标分页不支持向后导航
        return null;
    }

    /**
     * 获取下一页 URL
     */
    public function nextPageUrl(): ?string
    {
        if (!$this->hasMorePages()) {
            return null;
        }

        return $this->buildUrl($this->nextCursor);
    }

    /**
     * 获取第一页 URL（清空游标）
     */
    public function firstPageUrl(): string
    {
        return $this->buildUrl(null);
    }

    /**
     * 构建 URL
     */
    protected function buildUrl(?string $cursor): string
    {
        $query = $this->query;

        if ($cursor !== null) {
            $query[$this->cursorName] = $cursor;
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
     * 设置游标参数名
     */
    public function setCursorName(string $name): self
    {
        $this->cursorName = $name;
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

        // 加载更多按钮
        if ($this->hasMorePages()) {
            $html .= '<li><a href="' . htmlspecialchars($this->nextPageUrl()) . '">Load More</a></li>';
        }

        $html .= '</ul></nav>';

        return $html;
    }

    /**
     * 渲染无限滚动脚本
     */
    public function renderInfiniteScroll(string $container = '.items-container'): string
    {
        if (!$this->hasMorePages()) {
            return '';
        }

        // JS 上下文内不能用 HTML 转义：用 json_encode 生成合法的 JS 字符串字面量，
        // 同时中和引号逃逸与 </script> 注入
        $containerJson = json_encode($container);
        $nextUrlJson = json_encode($this->nextPageUrl());

        return <<<HTML
<script>
(function() {
    var loading = false;
    var container = document.querySelector({$containerJson});

    window.addEventListener('scroll', function() {
        if (loading) return;

        if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 500) {
            loading = true;

            fetch({$nextUrlJson}, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.text())
            .then(html => {
                container.insertAdjacentHTML('beforeend', html);
                loading = false;
            })
            .catch(() => { loading = false; });
        }
    });
})();
</script>
HTML;
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'data' => $this->items,
            'path' => strtok($this->path, '?'),
            'per_page' => $this->perPage,
            'next_cursor' => $this->nextCursor,
            'next_page_url' => $this->nextPageUrl(),
            'prev_cursor' => $this->cursor,
            'prev_page_url' => $this->previousPageUrl(),
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