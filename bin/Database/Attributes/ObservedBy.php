<?php

declare(strict_types=1);

namespace Bin\Database\Attributes;

use Attribute;

/**
 * 声明模型观察者（属性式 observe）
 *
 * #[ObservedBy([UserObserver::class])]
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ObservedBy
{
    /**
     * @param list<class-string>|class-string $observers
     */
    public function __construct(public readonly array|string $observers)
    {
    }

    /**
     * @return list<class-string>
     */
    public function observerClasses(): array
    {
        return (array) $this->observers;
    }
}
