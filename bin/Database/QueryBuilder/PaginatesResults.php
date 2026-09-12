<?php

declare(strict_types=1);

namespace Bin\Database\QueryBuilder;

use Bin\Database\CursorPaginator;
use Bin\Database\LengthAwarePaginator;
use Bin\Database\Model;
use Bin\Database\Paginator;
use InvalidArgumentException;

/**
 * 分页查询 Trait
 *
 * 包含完整分页、简单分页、游标分页。
 * 使用主类的 $pageResolver 静态属性。
 */
trait PaginatesResults
{
    /**
     * 分页查询
     *
     * @param int $perPage 每页数量
     * @param array $columns 查询列
     * @param string $pageName 页码参数名
     * @param int|null $page 当前页码（null 时自动获取）
     * @return LengthAwarePaginator
     */
    public function paginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null): LengthAwarePaginator
    {
        $page = $page ?? $this->resolveCurrentPage($pageName);

        $total = $this->getCountForPagination();

        // 克隆查询构建器，避免修改原实例
        $query = $this->clone();
        $query->columns = $columns;

        $results = $query->forPage($page, $perPage)->get();

        return new LengthAwarePaginator(
            $results->toArray(),
            $total,
            $perPage,
            $page,
            [
                'path' => $this->resolvePath(),
                'pageName' => $pageName,
                'query' => $this->resolveQuery(),
            ]
        );
    }

    /**
     * 简单分页（无总数统计）
     *
     * @param int $perPage 每页数量
     * @param array $columns 查询列
     * @param string $pageName 页码参数名
     * @param int|null $page 当前页码
     * @return Paginator
     */
    public function simplePaginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null): Paginator
    {
        $page = $page ?? $this->resolveCurrentPage($pageName);

        // 克隆查询构建器，避免修改原实例
        $query = $this->clone();
        $query->columns = $columns;

        // 使用原始 perPage 计算 offset，然后多取一条
        $offset = ($page - 1) * $perPage;
        $results = $query->offset($offset)->limit($perPage + 1)->get();

        $hasMore = $results->count() > $perPage;

        if ($hasMore) {
            $results = $results->pop();
        }

        return new Paginator(
            $results->toArray(),
            $perPage,
            $page,
            [
                'path' => $this->resolvePath(),
                'pageName' => $pageName,
                'query' => $this->resolveQuery(),
            ],
            $hasMore
        );
    }

    /**
     * 光标分页
     *
     * 支持任意排序列与方向（取查询的第一个 orderBy，缺省按 id 升序），
     * 游标携带排序列值与方向标记，next/prev 双向导航。
     */
    public function cursorPaginate(int $perPage = 15, array $columns = ['*'], string $cursorName = 'cursor', ?string $cursor = null): CursorPaginator
    {
        $query = $this->clone();
        $query->columns = $columns;

        [$orderColumn, $ascendingOrder] = $this->resolveCursorOrder($query);

        $direction = 'next';

        if ($cursor !== null) {
            $decoded = json_decode(base64_decode($cursor), true);

            if (!is_array($decoded) || !array_key_exists($orderColumn, $decoded)) {
                throw new InvalidArgumentException('Invalid cursor token: unable to decode.');
            }

            $direction = $decoded['__direction'] ?? 'next';
            $cursorValue = $decoded[$orderColumn];

            // prev 方向反转比较与排序，取回后反转结果恢复正序
            $ascending = $direction === 'prev' ? !$ascendingOrder : $ascendingOrder;

            $query->where($orderColumn, $ascending ? '>' : '<', $cursorValue);
            $query->resetOrders()->orderBy($orderColumn, $ascending ? 'asc' : 'desc');
        }

        // 多取一条判断是否有下一页
        $results = $query->limit($perPage + 1)->get();

        $hasMore = $results->count() > $perPage;

        if ($hasMore) {
            $results = $results->pop();
        }

        if ($direction === 'prev') {
            $results = $results->reverse();
        }

        $makeCursor = function ($item, string $dir) use ($orderColumn): ?string {
            $value = $item instanceof Model ? $item->getAttribute($orderColumn) : ($item[$orderColumn] ?? null);

            if ($value === null) {
                return null;
            }

            return base64_encode(json_encode([$orderColumn => $value, '__direction' => $dir]));
        };

        $firstItem = $results->isEmpty() ? null : $results->first();
        $lastItem = $results->isEmpty() ? null : $results->last();

        // next 游标：当前页最后一行（向后推进；prev 请求回正序后仍可继续向后）
        $nextCursor = ($hasMore || $direction === 'prev') && $lastItem !== null
            ? $makeCursor($lastItem, 'next')
            : null;

        // prev 游标：当前页第一行（向前推进；首页无更早数据）
        $previousCursor = $cursor !== null && $firstItem !== null
            ? $makeCursor($firstItem, 'prev')
            : null;

        return new CursorPaginator(
            $results->toArray(),
            $perPage,
            $cursor,
            $nextCursor,
            [
                'path' => $this->resolvePath(),
                'cursorName' => $cursorName,
                'query' => $this->resolveQuery(),
                'previousCursor' => $previousCursor,
            ]
        );
    }

    /**
     * 解析游标分页的排序列与方向（第一个 orderBy，缺省 id asc）
     *
     * @return array{0: string, 1: bool} [列名, 是否升序]
     */
    protected function resolveCursorOrder(self $query): array
    {
        foreach ($query->orders as $order) {
            if (!isset($order['type'])) {
                return [$order['column'], strtolower($order['direction'] ?? 'asc') === 'asc'];
            }
        }

        return ['id', true];
    }

    /**
     * 设置分页页码
     */
    public function forPage(int $page, int $perPage = 15): self
    {
        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    /**
     * 获取分页总数
     */
    protected function getCountForPagination(): int
    {
        $query = $this->clone();

        // 移除不需要的属性
        $query->columns = ['*'];
        $query->orders = [];
        $query->limit = null;
        $query->offset = null;
        $query->aggregate = null;

        return $query->count();
    }

    /**
     * 设置页码解析回调
     */
    public static function setPageResolver(\Closure $resolver): void
    {
        static::$pageResolver = $resolver;
    }

    /**
     * 恢复默认页码解析
     */
    public static function disablePageResolver(): void
    {
        static::$pageResolver = null;
    }

    /**
     * 解析当前页码
     */
    protected function resolveCurrentPage(string $pageName = 'page'): int
    {
        if (static::$pageResolver !== null) {
            $page = (static::$pageResolver)($pageName);
            if (!is_int($page) || $page < 1) {
                return 1;
            }
            return $page;
        }

        $page = $_GET[$pageName] ?? 1;

        if (filter_var($page, FILTER_VALIDATE_INT) !== false && (int) $page >= 1) {
            return (int) $page;
        }

        return 1;
    }

    /**
     * 解析当前路径
     */
    protected function resolvePath(): string
    {
        if (static::$pageResolver !== null) {
            return '/';
        }

        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    /**
     * 解析查询参数
     */
    protected function resolveQuery(): array
    {
        if (static::$pageResolver !== null) {
            return [];
        }

        $query = $_GET;
        unset($query['page']);

        return $query;
    }
}
