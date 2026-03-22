<?php

declare(strict_types=1);

namespace Bin\Database\Schema;

/**
 * 外键定义
 */
class ForeignKey
{
    protected string $column;

    protected string $referencedTable;

    protected string $referencedColumn;

    protected ?string $onDelete = null;

    protected ?string $onUpdate = null;

    protected Blueprint $blueprint;

    public function __construct(string $column, string $referencedTable, string $referencedColumn, Blueprint $blueprint)
    {
        $this->column = $column;
        $this->referencedTable = $referencedTable;
        $this->referencedColumn = $referencedColumn;
        $this->blueprint = $blueprint;
    }

    /**
     * ON DELETE 约束
     */
    public function onDelete(string $action): self
    {
        $this->onDelete = $action;
        return $this;
    }

    /**
     * ON UPDATE 约束
     */
    public function onUpdate(string $action): self
    {
        $this->onUpdate = $action;
        return $this;
    }

    /**
     * CASCADE 删除
     */
    public function cascadeOnDelete(): self
    {
        return $this->onDelete('cascade');
    }

    /**
     * CASCADE 更新
     */
    public function cascadeOnUpdate(): self
    {
        return $this->onUpdate('cascade');
    }

    /**
     * RESTRICT 删除
     */
    public function restrictOnDelete(): self
    {
        return $this->onDelete('restrict');
    }

    /**
     * RESTRICT 更新
     */
    public function restrictOnUpdate(): self
    {
        return $this->onUpdate('restrict');
    }

    /**
     * SET NULL 删除
     */
    public function nullOnDelete(): self
    {
        return $this->onDelete('set null');
    }

    /**
     * SET NULL 更新
     */
    public function nullOnUpdate(): self
    {
        return $this->onUpdate('set null');
    }

    /**
     * 添加到蓝图
     */
    public function add(): void
    {
        $this->blueprint->foreignKey(
            $this->column,
            null,
            $this->referencedTable,
            $this->referencedColumn,
            $this->onDelete,
            $this->onUpdate
        );
    }

    /**
     * 获取列
     */
    public function getColumn(): string
    {
        return $this->column;
    }

    /**
     * 获取引用表
     */
    public function getReferencedTable(): string
    {
        return $this->referencedTable;
    }

    /**
     * 获取引用列
     */
    public function getReferencedColumn(): string
    {
        return $this->referencedColumn;
    }

    /**
     * 获取 ON DELETE
     */
    public function getOnDelete(): ?string
    {
        return $this->onDelete;
    }

    /**
     * 获取 ON UPDATE
     */
    public function getOnUpdate(): ?string
    {
        return $this->onUpdate;
    }
}
