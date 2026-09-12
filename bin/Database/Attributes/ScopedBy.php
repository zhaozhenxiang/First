<?php

declare(strict_types=1);

namespace Bin\Database\Attributes;

use Attribute;

/**
 * 声明模型全局作用域（属性式 addGlobalScope）
 *
 * #[ScopedBy([AncientScope::class])]
 * #[ScopedBy(App\Scopes\TenantScope::class)]（单类也接受）
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ScopedBy
{
    /**
     * @param list<class-string>|class-string $scopes
     */
    public function __construct(public readonly array|string $scopes)
    {
    }

    /**
     * @return list<class-string>
     */
    public function scopeClasses(): array
    {
        return (array) $this->scopes;
    }
}
