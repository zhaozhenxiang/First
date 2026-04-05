<?php

declare(strict_types=1);

if (!function_exists('gate')) {
    /**
     * 获取 Gate 实例
     */
    function gate(): \Bin\Auth\Gate
    {
        static $gate = null;

        if ($gate === null) {
            $gate = new \Bin\Auth\Gate();
        }

        return $gate;
    }
}

if (!function_exists('can')) {
    /**
     * 检查当前用户是否有权限
     */
    function can(string $ability, mixed $arguments = []): bool
    {
        return \Bin\Auth\Gate::check($ability, $arguments);
    }
}

if (!function_exists('cannot')) {
    /**
     * 检查当前用户是否无权限
     */
    function cannot(string $ability, mixed $arguments = []): bool
    {
        return \Bin\Auth\Gate::denies($ability, $arguments);
    }
}

if (!function_exists('allows')) {
    /**
     * 检查当前用户是否有权限
     */
    function allows(string $ability, mixed $arguments = []): bool
    {
        return \Bin\Auth\Gate::allows($ability, $arguments);
    }
}

if (!function_exists('denies')) {
    /**
     * 检查当前用户是否无权限
     */
    function denies(string $ability, mixed $arguments = []): bool
    {
        return \Bin\Auth\Gate::denies($ability, $arguments);
    }
}

if (!function_exists('has_permission')) {
    /**
     * 检查用户是否有指定权限（RBAC）
     */
    function has_permission(string $permission, mixed $userId = null): bool
    {
        $userId = $userId ?? auth_id();

        if ($userId === null) {
            return false;
        }

        return \Bin\Auth\Rbac::hasPermission($userId, $permission);
    }
}

if (!function_exists('has_role')) {
    /**
     * 检查用户是否有指定角色（RBAC）
     */
    function has_role(string $role, mixed $userId = null): bool
    {
        $userId = $userId ?? auth_id();

        if ($userId === null) {
            return false;
        }

        return \Bin\Auth\Rbac::hasRole($userId, $role);
    }
}

if (!function_exists('has_any_role')) {
    /**
     * 检查用户是否有任一角色（RBAC）
     */
    function has_any_role(array $roles, mixed $userId = null): bool
    {
        $userId = $userId ?? auth_id();

        if ($userId === null) {
            return false;
        }

        return \Bin\Auth\Rbac::hasAnyRole($userId, $roles);
    }
}
