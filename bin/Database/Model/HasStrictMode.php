<?php

declare(strict_types=1);

namespace Bin\Database\Model;

use BadMethodCallException;
use InvalidArgumentException;
use LogicException;

/**
 * 严格模式 Trait — 防止开发时的常见错误
 *
 * 三种可独立控制的严格检查：
 * - preventLazyLoading: 防止懒加载关系（N+1 问题）
 * - preventSilentlyDiscardingAttributes: 防止 fill 时静默丢弃属性
 * - preventAccessingMissingAttributes: 防止访问不存在的属性
 */
trait HasStrictMode
{
    /**
     * 是否禁止懒加载关系
     */
    protected static bool $preventsLazyLoading = false;

    /**
     * 是否禁止静默丢弃属性
     */
    protected static bool $preventsSilentlyDiscardingAttributes = false;

    /**
     * 是否禁止访问不存在的属性
     */
    protected static bool $preventsAccessingMissingAttributes = false;

    /**
     * 启用所有严格模式
     */
    public static function shouldBeStrict(bool $shouldBeStrict = true): void
    {
        static::preventLazyLoading($shouldBeStrict);
        static::preventSilentlyDiscardingAttributes($shouldBeStrict);
        static::preventAccessingMissingAttributes($shouldBeStrict);
    }

    /**
     * 设置是否禁止懒加载
     */
    public static function preventLazyLoading(bool $value = true): void
    {
        static::$preventsLazyLoading = $value;
    }

    /**
     * 设置是否禁止静默丢弃属性
     */
    public static function preventSilentlyDiscardingAttributes(bool $value = true): void
    {
        static::$preventsSilentlyDiscardingAttributes = $value;
    }

    /**
     * 设置是否禁止访问不存在的属性
     */
    public static function preventAccessingMissingAttributes(bool $value = true): void
    {
        static::$preventsAccessingMissingAttributes = $value;
    }

    /**
     * 处理懒加载违规
     */
    protected function handleLazyLoadingViolation(string $relation): void
    {
        if (!static::$preventsLazyLoading) {
            return;
        }

        throw new LogicException(
            sprintf(
                'Lazy loading [%s] on [%s] is disabled. Use eager loading instead.',
                $relation,
                static::class
            )
        );
    }

    /**
     * 处理静默丢弃属性
     */
    protected function handleDiscardedAttribute(string $key): void
    {
        if (!static::$preventsSilentlyDiscardingAttributes) {
            return;
        }

        throw new InvalidArgumentException(
            sprintf(
                'Attribute [%s] was silently discarded during fill on [%s]. '
                . 'Add the attribute to the model\'s fillable array or disable strict mode.',
                $key,
                static::class
            )
        );
    }

    /**
     * 处理访问不存在的属性
     */
    protected function handleMissingAttributeViolation(string $key): void
    {
        if (!static::$preventsAccessingMissingAttributes) {
            return;
        }

        throw new InvalidArgumentException(
            sprintf(
                'Attribute [%s] does not exist on [%s]. '
                . 'Check for typos or add the attribute to the model.',
                $key,
                static::class
            )
        );
    }
}
