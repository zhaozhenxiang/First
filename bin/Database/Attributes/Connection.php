<?php

declare(strict_types=1);

namespace Bin\Database\Attributes;

use Attribute;

/**
 * 指定模型连接（属性式；多命名连接见 Model::on()）
 *
 * #[Connection('report')]
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Connection
{
    public function __construct(public readonly string $name)
    {
    }
}
