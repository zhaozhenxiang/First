<?php

declare(strict_types=1);

namespace Bin\Database\Attributes;

use Attribute;

/**
 * 类型转换映射（属性式）
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Casts
{
    /**
     * @param list<string>|array<string, mixed> $fields
     */
    public function __construct(public readonly array $fields)
    {
    }
}
