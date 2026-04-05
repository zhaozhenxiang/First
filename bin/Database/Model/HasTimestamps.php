<?php

declare(strict_types=1);

namespace Bin\Database\Model;

/**
 * 时间戳处理 Trait
 */
trait HasTimestamps
{
    /**
     * 设置创建时间
     */
    protected function setCreatedAt(): void
    {
        $this->setAttribute(self::CREATED_AT, date('Y-m-d H:i:s'));
    }

    /**
     * 设置更新时间
     */
    protected function setUpdatedAt(): void
    {
        $this->setAttribute(self::UPDATED_AT, date('Y-m-d H:i:s'));
    }

    /**
     * 获取创建时间
     */
    public function getCreatedAt(): ?string
    {
        return $this->getAttribute(self::CREATED_AT);
    }

    /**
     * 获取更新时间
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getAttribute(self::UPDATED_AT);
    }

    /**
     * 更新时间戳
     */
    public function touch(): bool
    {
        if (!$this->timestamps) {
            return false;
        }

        $this->setUpdatedAt();

        return $this->save();
    }

    /**
     * 软删除 - 如果模型支持的话
     */
    public function trashed(): bool
    {
        return false;
    }
}
