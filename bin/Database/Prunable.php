<?php

declare(strict_types=1);

namespace Bin\Database;

use Bin\Database\Collection;

/**
 * 可修剪模型 Trait（逐实例删除，触发模型事件）
 *
 * 定义 prunable() 返回待清理模型的查询，pruning() 在删除前清理关联资源；
 * 通过 `php command model:prune` 定期执行。
 */
trait Prunable
{
    /**
     * 待修剪模型的查询（由使用类定义）
     */
    abstract public function prunable(): QueryBuilder;

    /**
     * 修剪前钩子（删除关联资源等）
     */
    protected function pruning(): void
    {
    }

    /**
     * 修剪所有到期模型（分块逐个删除，触发事件与 pruning 钩子）
     */
    public function pruneAll(): int
    {
        $count = 0;

        $this->prunable()->chunkById(1000, function (Collection $models) use (&$count): void {
            foreach ($models as $model) {
                $model->pruning();

                if ($model->delete()) {
                    $count++;
                }
            }
        });

        return $count;
    }
}
