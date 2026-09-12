<?php

declare(strict_types=1);

namespace Bin\Database\QueryBuilder;

use Bin\Database\Collection;
use Bin\Database\Model;

/**
 * 分块处理 Trait
 *
 * 包含 chunk/chunkById/each/eachById 方法。
 */
trait ChunksResults
{
    /**
     * 分块大小下限校验
     *
     * 负数 LIMIT 在 SQLite/MySQL 语义中等于无限制，chunk/lazy 会死循环或全量拉取。
     */
    protected function assertChunkSize(int $chunkSize): void
    {
        if ($chunkSize < 1) {
            throw new \InvalidArgumentException('Chunk size must be at least 1, got [' . $chunkSize . '].');
        }
    }

    /**
     * 分块处理查询结果
     */
    public function chunk(int $count, callable $callback): bool
    {
        $this->assertChunkSize($count);

        $page = 1;

        do {
            $query = $this->clone()->forPage($page, $count);
            $results = $query->get();

            if ($results->isEmpty()) {
                break;
            }

            if ($callback($results) === false) {
                return false;
            }

            unset($results);
            $page++;
        } while (true);

        return true;
    }

    /**
     * 按 ID 分块处理（更高效，不会偏移遗漏）
     */
    public function chunkById(int $count, callable $callback, string $column = 'id'): bool
    {
        $this->assertChunkSize($count);

        $lastId = 0;

        do {
            // 每轮克隆：既不污染调用方 builder，也让本轮追加的 where/orderBy 随轮丢弃；
            // 重置已有排序，保证按 ID 分块的顺序确定性
            $results = $this->clone()
                ->where($column, '>', $lastId)
                ->resetOrders()
                ->orderBy($column)
                ->limit($count)
                ->get();

            if ($results->isEmpty()) {
                break;
            }

            if ($callback($results) === false) {
                return false;
            }

            $lastItem = $results->last();

            if ($lastItem instanceof Model) {
                $lastId = $lastItem->getKey();
            } else {
                $lastId = $lastItem[$column] ?? 0;
            }

            unset($results);
        } while (true);

        return true;
    }

    /**
     * 逐条迭代处理
     */
    public function each(callable $callback, int $count = 1000): bool
    {
        return $this->chunk($count, function (Collection $results) use ($callback) {
            foreach ($results as $key => $item) {
                if ($callback($item, $key) === false) {
                    return false;
                }
            }
        });
    }

    /**
     * 按 ID 逐条迭代处理
     */
    public function eachById(callable $callback, int $count = 1000, string $column = 'id'): bool
    {
        return $this->chunkById($count, function (Collection $results) use ($callback) {
            foreach ($results as $key => $item) {
                if ($callback($item, $key) === false) {
                    return false;
                }
            }
        }, $column);
    }

    /**
     * 游标迭代：单条 SQL，逐行 fetch 并惰性水合
     *
     * 内存中同一时刻只保留一行（PDO 内部缓冲仍持有全部原始结果，这是驱动层
     * 行为）。不支持 eager load——需要关系请用 lazy()。
     */
    public function cursor(): \Generator
    {
        $this->applyScopes();

        $sql = $this->toSql();
        $bindings = $this->getBindings();

        $startTime = microtime(true);
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($bindings);

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                yield $this->modelClass && class_exists($this->modelClass)
                    ? $this->hydrateModel($row)
                    : $row;
            }

            $this->logQuery($sql, $bindings, (microtime(true) - $startTime) * 1000);
        } catch (\Throwable $e) {
            $this->logQuery($sql, $bindings, (microtime(true) - $startTime) * 1000, 0, false, $e->getMessage());
            throw $e;
        }
    }

    /**
     * 惰性迭代：按页分块查询，以生成器流式产出（无 LazyCollection，返回 \Generator）
     */
    public function lazy(int $chunkSize = 1000): \Generator
    {
        $this->assertChunkSize($chunkSize);

        $page = 1;

        do {
            $results = $this->clone()->forPage($page, $chunkSize)->get();

            if ($results->isEmpty()) {
                return;
            }

            yield from $results;
            $page++;
        } while (true);
    }

    /**
     * 惰性迭代（按 ID 前进，边遍历边更新筛选列时不会偏移遗漏）
     */
    public function lazyById(int $chunkSize = 1000, string $column = 'id'): \Generator
    {
        $this->assertChunkSize($chunkSize);

        $lastId = 0;

        do {
            $results = $this->clone()
                ->where($column, '>', $lastId)
                ->resetOrders()
                ->orderBy($column)
                ->limit($chunkSize)
                ->get();

            if ($results->isEmpty()) {
                return;
            }

            yield from $results;

            $lastItem = $results->last();
            $lastId = $lastItem instanceof Model ? $lastItem->getKey() : ($lastItem[$column] ?? 0);
        } while (true);
    }
}
