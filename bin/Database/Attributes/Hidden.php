<?php

declare(strict_types=1);

namespace Bin\Database\Attributes;

use Attribute;

/**
 * 序列化隐藏/显示/追加（属性式 $hidden/$visible/$appends）
 *
 * #[Hidden(['password'])]
 * #[Visible(['id', 'name'])]
 * #[Appends(['full_name'])]
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Hidden
{
    /**
     * @param list<string> $fields
     */
    public function __construct(public readonly array $fields)
    {
    }
}
