<?php

declare(strict_types=1);

namespace Bin\Database\Attributes;

use Attribute;

/**
 * 批量赋值黑名单（属性式 $guarded）
 *
 * #[Guarded(['is_admin'])]
 * #[Guarded(['*'])]
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Guarded
{
    /**
     * @param list<string> $fields
     */
    public function __construct(public readonly array $fields)
    {
    }
}
