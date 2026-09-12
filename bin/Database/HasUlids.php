<?php

declare(strict_types=1);

namespace Bin\Database;

/**
 * ULID 主键 Trait
 *
 * 零依赖生成 ULID（26 位 Crockford base32，48bit 毫秒时间戳前缀，
 * 字典序可排序）。
 */
trait HasUlids
{
    /**
     * ULID 主键非自增
     */
    public function getIncrementing(): bool
    {
        return false;
    }

    /**
     * ULID 主键为 string 类型
     */
    public function getKeyType(): string
    {
        return 'string';
    }

    /**
     * 生成新的唯一标识（ULID）
     */
    public function newUniqueId(): string
    {
        return static::ulid();
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
     * ULID：6 字节时间戳 + 10 字节随机数（128bit），Crockford base32 编码为 26 位
     */
    public static function ulid(): string
    {
        $encoding = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

        $time = (int) (microtime(true) * 1000);

        // 48bit 大端时间戳 + 80bit 随机
        $bytes = pack('nN', ($time >> 32) & 0xFFFF, $time & 0xFFFFFFFF) . random_bytes(10);

        // 128bit 不足 26*5=130bit，前补 2 个 0 位后按 5bit 分组编码
        $bits = '00';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $ulid = '';
        foreach (str_split($bits, 5) as $chunk) {
            $ulid .= $encoding[(int) bindec($chunk)];
        }

        return $ulid;
    }

    /**
     * creating 时为空键填充 ULID
     */
    protected static function bootHasUlids(): void
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
