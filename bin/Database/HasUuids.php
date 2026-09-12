<?php

declare(strict_types=1);

namespace Bin\Database;

/**
 * UUID 主键 Trait
 *
 * 零依赖生成 UUIDv7（时间戳前缀，可按字典序排序，利于索引局部性）。
 * 默认填充主键；覆写 newUniqueId()/uniqueIds() 可自定义。
 */
trait HasUuids
{
    /**
     * UUID 主键非自增
     */
    public function getIncrementing(): bool
    {
        return false;
    }

    /**
     * UUID 主键为 string 类型
     */
    public function getKeyType(): string
    {
        return 'string';
    }

    /**
     * 生成新的唯一标识（UUIDv7）
     */
    public function newUniqueId(): string
    {
        return static::uuid7();
    }

    /**
     * 需要填充唯一标识的列（默认主键）
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return [$this->getKeyName()];
    }

    /**
     * UUIDv7：48bit 毫秒时间戳 + 版本/变体位 + 随机位
     */
    public static function uuid7(): string
    {
        $time = (int) (microtime(true) * 1000);

        return sprintf(
            '%08x-%04x-%04x-%04x-%012x',
            $time & 0xFFFFFFFF,
            ($time >> 32) & 0xFFFF,
            (($time >> 48) & 0x0FFF) | 0x7000,
            random_int(0, 0x3FFF) | 0x8000,
            random_int(0, 0xFFFFFFFFFFFF),
        );
    }

    /**
     * creating 时为空键填充 UUID
     */
    protected static function bootHasUuids(): void
    {
        static::creating(function (Model $model): void {
            foreach ($model->uniqueIds() as $column) {
                if ($model->getAttribute($column) === null) {
                    $model->setAttribute($column, $model->newUniqueId());
                }
            }
        });
    }
}
