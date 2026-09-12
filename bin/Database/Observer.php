<?php

declare(strict_types=1);

namespace Bin\Database;

/**
 * 模型观察者基类
 *
 * 子类可实现以下方法来监听模型事件:
 * - retrieved(Model $model): void
 * - creating(Model $model): void
 * - created(Model $model): void
 * - updating(Model $model): void
 * - updated(Model $model): void
 * - saving(Model $model): void
 * - saved(Model $model): void
 * - deleting(Model $model): void
 * - deleted(Model $model): void
 * - restoring(Model $model): void
 * - restored(Model $model): void
 * - trashed(Model $model): void（软删除后）
 * - forceDeleting(Model $model): void
 * - forceDeleted(Model $model): void
 */
class Observer
{
    /**
     * 支持的事件列表
     */
    public const EVENTS = [
        'retrieved', 'creating', 'created', 'updating', 'updated',
        'saving', 'saved', 'deleting', 'deleted', 'restoring', 'restored',
        'trashed', 'forceDeleting', 'forceDeleted',
    ];
}
