<?php

declare(strict_types=1);

namespace Bin\Facade;

use Bin\Auth\Gate as GateManager;

/**
 * Gate Facade - 静态代理授权管理器
 *
 * @method static bool check(string $ability, mixed $arguments = [])
 * @method static bool any(array $abilities, mixed $arguments = [])
 * @method static bool all(array $abilities, mixed $arguments = [])
 * @method static void define(string $ability, callable $callback)
 * @method static void policy(string $class, string $policy)
 * @method static bool has(string $ability)
 */
class Gate extends Facade
{
    protected function getClassName(): string
    {
        return GateManager::class;
    }

    protected static function getInstance(): object
    {
        return GateManager::getInstance();
    }
}
