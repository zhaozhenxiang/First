<?php

declare(strict_types=1);

namespace Bin\Facade;

use Bin\Session\SessionManager;

/**
 * Session Facade - 静态代理 Session 管理器
 *
 * @method static bool start()
 * @method static mixed get(string $key, mixed $default = null)
 * @method static void set(string $key, mixed $value)
 * @method static bool has(string $key)
 * @method static void remove(string $key)
 * @method static mixed pull(string $key, mixed $default = null)
 * @method static void clear()
 * @method static array all()
 * @method static void flash(string $key, mixed $value)
 * @method static string getId()
 * @method static void setId(string $id)
 * @method static void save()
 */
class Session extends Facade
{
    protected function getClassName(): string
    {
        return SessionManager::class;
    }
}
