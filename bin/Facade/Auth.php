<?php

declare(strict_types=1);

namespace Bin\Facade;

use Bin\Auth\AuthManager;

/**
 * Auth Facade - 静态代理认证管理器
 *
 * @method static bool attempt(array $credentials, bool $remember = false)
 * @method static void login(object $user, bool $remember = false)
 * @method static ?object loginUsingId(mixed $id, bool $remember = false)
 * @method static void logout()
 * @method static ?object user()
 * @method static mixed id()
 * @method static bool check()
 * @method static bool guest()
 * @method static bool validate(array $credentials)
 */
class Auth extends Facade
{
    protected function getClassName(): string
    {
        return AuthManager::class;
    }

    protected static function getInstance(): object
    {
        return AuthManager::getInstance();
    }
}
