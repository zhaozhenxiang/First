<?php

declare(strict_types=1);

namespace Bin\Database;

/**
 * 批量可修剪模型 Trait（单条 DELETE，不触发模型事件与 pruning 钩子）
 */
trait MassPrunable
{
    /**
     * 待修剪模型的查询（由使用类定义）
     */
    abstract public function prunable(): QueryBuilder;

    /**
     * 批量修剪（一条 DELETE 语句）
     */
    public function pruneAll(): int
    {
        return $this->prunable()->delete();
    }
}
