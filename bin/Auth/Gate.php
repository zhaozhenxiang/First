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
    private array $abilities = [];

    /**
     * 授权策略映射
     */
    private array $policies = [];

    /**
     * 默认拒绝状态
     */
    private bool $defaultDeny = true;

    /**
     * 当前用户获取器
     */
    private $userResolver = null;

    /** @var self|null 单例实例 */
    private static ?self $instance = null;

    public function __construct()
    {
    }

    /**
     * 获取单例实例
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * 重置单例（用于测试）
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * 设置用户解析器
     */
    public function setUserResolverFor(callable $resolver): void
    {
        $this->userResolver = $resolver;
    }

    /**
     * 获取当前用户
     */
    public function userFor(): ?object
    {
        if ($this->userResolver !== null) {
            return call_user_func($this->userResolver);
        }

        return AuthManager::user();
    }

    /**
     * 定义授权能力
     */
    public function defineFor(string $ability, callable $callback): void
    {
        $this->abilities[$ability] = $callback;
    }

    /**
     * 批量定义授权能力
     */
    public function defineAbilitiesFor(array $abilities): void
    {
        foreach ($abilities as $name => $callback) {
            $this->defineFor($name, $callback);
        }
    }

    /**
     * 注册策略类
     */
    public function policyFor(string $class, string $policy): void
    {
        $this->policies[$class] = $policy;
    }

    /**
     * 批量注册策略
     */
    public function definePoliciesFor(array $policies): void
    {
        $this->policies = array_merge($this->policies, $policies);
    }

    /**
     * 检查权限
     */
    public function checkFor(string $ability, mixed $arguments = []): bool
    {
        $user = $this->userFor();

        if (isset($this->abilities[$ability])) {
            $result = call_user_func($this->abilities[$ability], $user, $arguments);

            return (bool) $result;
        }

        if (is_object($arguments)) {
            $policy = $this->getPolicyFor($arguments);

            if ($policy !== null) {
                return $this->checkPolicy($policy, $ability, $user, $arguments);
            }
        }

        return !$this->defaultDeny;
    }

    /**
     * 检查策略权限
     */
    protected function checkPolicy(
        string $policy,
        string $ability,
        ?object $user,
        object $model
    ): bool {
        if (!class_exists($policy)) {
            return !$this->defaultDeny;
        }

        $policyInstance = new $policy();

        if (method_exists($policyInstance, 'before')) {
            $result = $policyInstance->before($user, $ability, $model);

            if ($result !== null) {
                return (bool) $result;
            }
        }

        $method = $ability;

        if (!method_exists($policyInstance, $method)) {
            return !$this->defaultDeny;
        }

        $result = $policyInstance->$method($user, $model);

        return (bool) $result;
    }

    /**
     * 获取模型的策略
     */
    protected function getPolicyFor(object $model): ?string
    {
        $class = get_class($model);

        foreach ($this->policies as $modelClass => $policy) {
            if ($class === $modelClass || is_subclass_of($class, $modelClass)) {
                return $policy;
            }
        }

        $policy = str_replace('App\\Model\\', 'App\\Policies\\', $class) . 'Policy';

        if (class_exists($policy)) {
            return $policy;
        }

        return null;
    }

    /**
     * 检查多个权限（任一通过）
     */
    public function anyFor(array $abilities, mixed $arguments = []): bool
    {
        foreach ($abilities as $ability) {
            if ($this->checkFor($ability, $arguments)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查多个权限（全部通过）
     */
    public function allFor(array $abilities, mixed $arguments = []): bool
    {
        foreach ($abilities as $ability) {
            if (!$this->checkFor($ability, $arguments)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 设置默认拒绝状态
     */
    public function setDefaultDenyFor(bool $deny): void
    {
        $this->defaultDeny = $deny;
    }

    /**
     * 清除所有注册
     */
    public function clearFor(): void
    {
        $this->abilities = [];
        $this->policies = [];
    }

    /**
     * 获取所有已定义的能力
     */
    public function abilitiesFor(): array
    {
        return $this->abilities;
    }

    /**
     * 获取所有策略
     */
    public function policiesFor(): array
    {
        return $this->policies;
    }

    /**
     * 检查能力是否存在
     */
    public function hasFor(string $ability): bool
    {
        return isset($this->abilities[$ability]);
    }

    // ─── @deprecated 静态兼容层 ───────────────────────────

    /**
     * @deprecated 使用 Gate::getInstance()->setUserResolverFor()
     */
    public static function setUserResolver(callable $resolver): void
    {
        self::getInstance()->setUserResolverFor($resolver);
    }

    /**
     * @deprecated 使用 Gate::getInstance()->userFor()
     */
    public static function user(): ?object
    {
        return self::getInstance()->userFor();
    }

    /**
     * @deprecated 使用 Gate::getInstance()->defineFor()
     */
    public static function define(string $ability, callable $callback): void
    {
        self::getInstance()->defineFor($ability, $callback);
    }

    /**
     * @deprecated 使用 Gate::getInstance()->defineAbilitiesFor()
     */
    public static function defineAbilities(array $abilities): void
    {
        self::getInstance()->defineAbilitiesFor($abilities);
    }

    /**
     * @deprecated 使用 Gate::getInstance()->policyFor()
     */
    public static function policy(string $class, string $policy): void
    {
        self::getInstance()->policyFor($class, $policy);
    }

    /**
     * @deprecated 使用 Gate::getInstance()->definePoliciesFor()
     */
    public static function definePolicies(array $policies): void
    {
        self::getInstance()->definePoliciesFor($policies);
    }

    /**
     * @deprecated 使用 Gate::getInstance()->checkFor()
     */
    public static function allows(string $ability, mixed $arguments = []): bool
    {
        return self::getInstance()->checkFor($ability, $arguments);
    }

    /**
     * @deprecated 使用 Gate::getInstance()->checkFor()
     */
    public static function denies(string $ability, mixed $arguments = []): bool
    {
        return !self::getInstance()->checkFor($ability, $arguments);
    }

    /**
     * @deprecated 使用 Gate::getInstance()->checkFor()
     */
    public static function check(string $ability, mixed $arguments = []): bool
    {
        return self::getInstance()->checkFor($ability, $arguments);
    }

    /**
     * @deprecated 使用 Gate::getInstance()->anyFor()
     */
    public static function any(array $abilities, mixed $arguments = []): bool
    {
        return self::getInstance()->anyFor($abilities, $arguments);
    }

    /**
     * @deprecated 使用 Gate::getInstance()->allFor()
     */
    public static function all(array $abilities, mixed $arguments = []): bool
    {
        return self::getInstance()->allFor($abilities, $arguments);
    }

    /**
     * @deprecated 使用 Gate::getInstance()->setDefaultDenyFor()
     */
    public static function setDefaultDeny(bool $deny): void
    {
        self::getInstance()->setDefaultDenyFor($deny);
    }

    /**
     * @deprecated 使用 Gate::getInstance()->clearFor()
     */
    public static function clear(): void
    {
        self::getInstance()->clearFor();
    }

    /**
     * @deprecated 使用 Gate::getInstance()->abilitiesFor()
     */
    public static function abilities(): array
    {
        return self::getInstance()->abilitiesFor();
    }

    /**
     * @deprecated 使用 Gate::getInstance()->policiesFor()
     */
    public static function policies(): array
    {
        return self::getInstance()->policiesFor();
    }

    /**
     * @deprecated 使用 Gate::getInstance()->hasFor()
     */
    public static function has(string $ability): bool
    {
        return self::getInstance()->hasFor($ability);
    }
}
