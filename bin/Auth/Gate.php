<?php

declare(strict_types=1);

namespace Bin\Auth;

/**
 * Gate 授权管理器
 *
 * 简单的闭包授权系统
 */
class Gate
{
    /**
     * 已注册的授权能力
     */
    private static array $abilities = [];

    /**
     * 授权策略映射
     */
    private static array $policies = [];

    /**
     * 默认拒绝状态
     */
    private static bool $defaultDeny = true;

    /**
     * 当前用户获取器
     */
    private static $userResolver = null;

    /**
     * 设置用户解析器
     */
    public static function setUserResolver(callable $resolver): void
    {
        self::$userResolver = $resolver;
    }

    /**
     * 获取当前用户
     */
    public static function user(): ?object
    {
        if (self::$userResolver !== null) {
            return call_user_func(self::$userResolver);
        }

        return AuthManager::user();
    }

    /**
     * 定义授权能力
     */
    public static function define(string $ability, callable $callback): void
    {
        self::$abilities[$ability] = $callback;
    }

    /**
     * 批量定义授权能力
     */
    public static function defineAbilities(array $abilities): void
    {
        foreach ($abilities as $name => $callback) {
            self::define($name, $callback);
        }
    }

    /**
     * 注册策略类
     */
    public static function policy(string $class, string $policy): void
    {
        self::$policies[$class] = $policy;
    }

    /**
     * 批量注册策略
     */
    public static function definePolicies(array $policies): void
    {
        self::$policies = array_merge(self::$policies, $policies);
    }

    /**
     * 检查权限
     */
    public static function allows(string $ability, mixed $arguments = []): bool
    {
        return self::check($ability, $arguments);
    }

    /**
     * 检查权限（拒绝）
     */
    public static function denies(string $ability, mixed $arguments = []): bool
    {
        return !self::check($ability, $arguments);
    }

    /**
     * 检查权限
     */
    public static function check(string $ability, mixed $arguments = []): bool
    {
        $user = self::user();

        // 首先检查已定义的能力
        if (isset(self::$abilities[$ability])) {
            $result = call_user_func(self::$abilities[$ability], $user, $arguments);

            return (bool) $result;
        }

        // 尝试从策略中查找
        if (is_object($arguments)) {
            $policy = self::getPolicyFor($arguments);

            if ($policy !== null) {
                return self::checkPolicy($policy, $ability, $user, $arguments);
            }
        }

        // 使用默认设置
        return !self::$defaultDeny;
    }

    /**
     * 检查策略权限
     */
    protected static function checkPolicy(
        string $policy,
        string $ability,
        ?object $user,
        object $model
    ): bool {
        if (!class_exists($policy)) {
            return !self::$defaultDeny;
        }

        $policyInstance = new $policy();

        // 检查 before 方法
        if (method_exists($policyInstance, 'before')) {
            $result = $policyInstance->before($user, $ability, $model);

            if ($result !== null) {
                return (bool) $result;
            }
        }

        // 检查具体的方法
        $method = $ability;

        if (!method_exists($policyInstance, $method)) {
            return !self::$defaultDeny;
        }

        $result = $policyInstance->$method($user, $model);

        return (bool) $result;
    }

    /**
     * 获取模型的策略
     */
    protected static function getPolicyFor(object $model): ?string
    {
        $class = get_class($model);

        foreach (self::$policies as $modelClass => $policy) {
            if ($class === $modelClass || is_subclass_of($class, $modelClass)) {
                return $policy;
            }
        }

        // 尝试自动发现策略
        $policy = str_replace('App\\Model\\', 'App\\Policies\\', $class) . 'Policy';

        if (class_exists($policy)) {
            return $policy;
        }

        return null;
    }

    /**
     * 检查多个权限（任一通过）
     */
    public static function any(array $abilities, mixed $arguments = []): bool
    {
        foreach ($abilities as $ability) {
            if (self::check($ability, $arguments)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查多个权限（全部通过）
     */
    public static function all(array $abilities, mixed $arguments = []): bool
    {
        foreach ($abilities as $ability) {
            if (!self::check($ability, $arguments)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 设置默认拒绝状态
     */
    public static function setDefaultDeny(bool $deny): void
    {
        self::$defaultDeny = $deny;
    }

    /**
     * 清除所有注册
     */
    public static function clear(): void
    {
        self::$abilities = [];
        self::$policies = [];
    }

    /**
     * 获取所有已定义的能力
     */
    public static function abilities(): array
    {
        return self::$abilities;
    }

    /**
     * 获取所有策略
     */
    public static function policies(): array
    {
        return self::$policies;
    }

    /**
     * 检查能力是否存在
     */
    public static function has(string $ability): bool
    {
        return isset(self::$abilities[$ability]);
    }
}
