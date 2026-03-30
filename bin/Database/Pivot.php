<?php

declare(strict_types=1);

namespace Bin\Database;

/**
 * 中间表 Pivot 模型
 */
class Pivot extends Model
{
    /**
     * 是否自增主键
     */
    protected bool $incrementing = false;

    /**
     * 是否使用时间戳
     */
    protected bool $timestamps = false;

    /**
     * Pivot 的父模型
     */
    protected ?Model $pivotParent = null;

    /**
     * 额外的 pivot 列
     */
    protected array $pivotColumns = [];

    /**
     * 外键名
     */
    protected string $foreignKey;

    /**
     * 相关键名
     */
    protected string $relatedKey;

    /**
     * 构造函数
     */
    public function __construct(array $attributes = [], ?string $table = null)
    {
        $this->guarded = [];

        parent::__construct($attributes);

        if ($table !== null) {
            $this->table = $table;
        }
    }

    /**
     * 设置父模型
     */
    public function setPivotParent(Model $parent): self
    {
        $this->pivotParent = $parent;
        return $this;
    }

    /**
     * 获取父模型
     */
    public function getPivotParent(): ?Model
    {
        return $this->pivotParent;
    }

    /**
     * 设置外键名
     */
    public function setForeignKey(string $key): self
    {
        $this->foreignKey = $key;
        return $this;
    }

    /**
     * 获取外键名
     */
    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    /**
     * 设置相关键名
     */
    public function setRelatedKey(string $key): self
    {
        $this->relatedKey = $key;
        return $this;
    }

    /**
     * 获取相关键名
     */
    public function getRelatedKey(): string
    {
        return $this->relatedKey;
    }

    /**
     * 删除 pivot 记录
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $query = static::query();
        $query->where($this->foreignKey, $this->getAttribute($this->foreignKey));
        $query->where($this->relatedKey, $this->getAttribute($this->relatedKey));

        return $query->delete() > 0;
    }
}
