<?php

declare(strict_types=1);

namespace Bin\Database\Attributes;

use Attribute;

/**
 * 模型表名/主键配置（属性式，与 protected 属性声明共存，属性优先）
 *
 * #[Table('my_flights')]
 * #[Table(key: 'flight_id')]
 * #[Table(key: 'uuid', keyType: 'string', incrementing: false, timestamps: false, dateFormat: 'U')]
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Table
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $key = null,
        public readonly ?string $keyType = null,
        public readonly ?bool $incrementing = null,
        public readonly ?bool $timestamps = null,
        public readonly ?string $dateFormat = null,
    ) {
    }
}
