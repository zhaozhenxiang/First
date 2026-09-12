<?php

declare(strict_types=1);

namespace Bin\Database\Attributes;

use Attribute;

/**
 * 批量赋值白名单（属性式 $fillable）
 *
 * #[Fillable(['name', 'email'])]
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Fillable
{
    /**
     * @param list<string> $fields
     */
    public function __construct(public readonly array $fields)
    {
    }
}
