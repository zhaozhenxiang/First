<?php

declare(strict_types=1);

namespace Bin\Database\Attributes;

use Attribute;

/**
 * 可见属性（属性式）
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Visible
{
    /**
     * @param list<string>|array<string, mixed> $fields
     */
    public function __construct(public readonly array $fields)
    {
    }
}
