<?php

declare(strict_types=1);

namespace Bin\Database;

use DateTimeInterface;

/**
 * 软删除 Trait
 *
 * 在模型中使用:
 * class User extends Model {
 *     use SoftDeletes;
 * }
 */
trait SoftDeletes
{
    /**
     * 软删除字段名
     */
    protected string $deletedAtColumn = 'deleted_at';

    /**
     * 是否已软删除
     */
    protected bool $isSoftDeleted = false;

    /**
     * 软删除模型
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        // 触发 deleting 事件
        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        // 如果启用了软删除，设置 deleted_at
        $this->setAttribute($this->getDeletedAtColumn(), $this->freshTimestamp());

        $result = $this->save();

        if ($result) {
            $this->isSoftDeleted = true;
            // 触发 deleted 事件
            $this->fireModelEvent('deleted');
        }

        return $result;
    }

    /**
     * 强制删除模型（永久删除）
     */
    public function forceDelete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        // 触发 deleting 事件
        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        // 临时禁用软删除
        $this->isSoftDeleted = false;

        $result = static::query()->where($this->getKeyName(), $this->getKey())->delete() > 0;

        if ($result) {
            $this->exists = false;
            // 触发 deleted 事件
            $this->fireModelEvent('deleted');
        }

        return $result;
    }

    /**
     * 恢复软删除的模型
     */
    public function restore(): bool
    {
        if (!$this->isSoftDeleted()) {
            return false;
        }

        // 触发 restoring 事件
        if ($this->fireModelEvent('restoring') === false) {
            return false;
        }

        $this->setAttribute($this->getDeletedAtColumn(), null);

        $result = $this->save();

        if ($result) {
            $this->isSoftDeleted = false;
            // 触发 restored 事件
            $this->fireModelEvent('restored');
        }

        return $result;
    }

    /**
     * 检查是否已软删除
     */
    public function isSoftDeleted(): bool
    {
        return $this->getAttribute($this->getDeletedAtColumn()) !== null;
    }

    /**
     * 检查模型是否被软删除（别名）
     */
    public function trashed(): bool
    {
        return $this->isSoftDeleted();
    }

    /**
     * 获取软删除字段名
     */
    public function getDeletedAtColumn(): string
    {
        return $this->deletedAtColumn;
    }

    /**
     * 设置软删除字段名
     */
    public function setDeletedAtColumn(string $column): self
    {
        $this->deletedAtColumn = $column;
        return $this;
    }

    /**
     * 获取软删除字段的限定名
     */
    public function getQualifiedDeletedAtColumn(): string
    {
        return $this->getTable() . '.' . $this->getDeletedAtColumn();
    }

    /**
     * 获取当前时间戳
     */
    protected function freshTimestamp(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * 包含软删除记录
     */
    public static function withTrashed(): QueryBuilder
    {
        return static::query()->withoutGlobalScope('soft_delete');
    }

    /**
     * 只获取软删除记录
     */
    public static function onlyTrashed(): QueryBuilder
    {
        return static::query()->withoutGlobalScope('soft_delete')
            ->whereNotNull((new static())->getQualifiedDeletedAtColumn());
    }

    /**
     * 只获取未删除记录（默认行为）
     */
    public static function withoutTrashed(): QueryBuilder
    {
        return static::query();
    }

    /**
     * 恢复多个软删除记录
     */
    public static function restoreMany(array $ids): int
    {
        $instance = new static();
        $deletedAt = $instance->getDeletedAtColumn();

        return static::onlyTrashed()
            ->whereIn($instance->getKeyName(), $ids)
            ->update([$deletedAt => null]);
    }

    /**
     * 强制删除多个记录
     */
    public static function forceDeleteMany(array $ids): int
    {
        return static::withTrashed()
            ->whereIn((new static())->getKeyName(), $ids)
            ->delete();
    }

    /**
     * 模型初始化时添加软删除全局作用域
     */
    protected static function bootSoftDeletes(): void
    {
        static::addGlobalScope('soft_delete', function (QueryBuilder $query) {
            $model = new static();
            $query->whereNull($model->getQualifiedDeletedAtColumn());
        });
    }
}