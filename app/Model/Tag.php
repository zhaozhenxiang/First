<?php

declare(strict_types=1);

namespace App\Model;

use Bin\Database\Model;

/**
 * Tag 模型
 *
 * @property int $id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Tag extends Model
{
    /**
     * 表名
     */
    protected string $table = 'tags';

    /**
     * 可批量赋值的字段
     *
     * @var array<int, string>
     */
    protected array $fillable = [
        // 'name',
        // 'email',
    ];

    /**
     * 字段类型转换
     *
     * @var array<string, string>
     */
    protected array $casts = [];
}
