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
     * 分块处理查询结果
     */
    public function chunk(int $count, callable $callback): bool
    {
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
}
