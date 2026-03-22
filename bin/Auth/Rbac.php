<?php

declare(strict_types=1);

namespace Bin\Auth;

/**
 * RBAC 基于角色的访问控制
 */
class Rbac
{
    /**
     * 角色权限映射
     */
    private static array $roles = [];

    /**
     * 用户角色映射
     */
    private static array $userRoles = [];

    /**
     * 角色继承关系
     */
    private static array $roleInheritance = [];

    /**
     * 定义角色及其权限
     */
    public static function defineRole(string $role, array $permissions = []): void
    {
        self::$roles[$role] = $permissions;
    }

    /**
     * 批量定义角色
     */
    public static function defineRoles(array $roles): void
    {
        foreach ($roles as $role => $permissions) {
            self::defineRole($role, $permissions);
        }
    }

    /**
     * 为用户分配角色
     */
    public static function assignRole(mixed $userId, string $role): void
    {
        if (!isset(self::$userRoles[$userId])) {
            self::$userRoles[$userId] = [];
        }

        if (!in_array($role, self::$userRoles[$userId], true)) {
            self::$userRoles[$userId][] = $role;
        }
    }

    /**
     * 移除用户角色
     */
    public static function removeRole(mixed $userId, string $role): void
    {
        if (isset(self::$userRoles[$userId])) {
            self::$userRoles[$userId] = array_values(array_filter(
                self::$userRoles[$userId],
                fn($r) => $r !== $role
            ));
        }
    }

    /**
     * 设置角色继承
     */
    public static function setInheritance(string $child, string $parent): void
    {
        self::$roleInheritance[$child] = $parent;
    }

    /**
     * 检查用户是否有权限
     */
    public static function hasPermission(mixed $userId, string $permission): bool
    {
        $roles = self::getUserRoles($userId);

        foreach ($roles as $role) {
            if (self::roleHasPermission($role, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查角色是否有权限（包括继承）
     */
    public static function roleHasPermission(string $role, string $permission): bool
    {
        $permissions = self::getRolePermissions($role);

        return in_array($permission, $permissions, true) || in_array('*', $permissions, true);
    }

    /**
     * 获取角色的所有权限（包括继承）
     */
    public static function getRolePermissions(string $role): array
    {
        $permissions = self::$roles[$role] ?? [];
        $currentRole = $role;

        // 检查继承
        while (isset(self::$roleInheritance[$currentRole])) {
            $parentRole = self::$roleInheritance[$currentRole];
            $permissions = array_merge($permissions, self::$roles[$parentRole] ?? []);
            $currentRole = $parentRole;
        }

        return array_unique($permissions);
    }

    /**
     * 获取用户的所有角色
     */
    public static function getUserRoles(mixed $userId): array
    {
        return self::$userRoles[$userId] ?? [];
    }

    /**
     * 检查用户是否有角色
     */
    public static function hasRole(mixed $userId, string $role): bool
    {
        return in_array($role, self::getUserRoles($userId), true);
    }

    /**
     * 检查用户是否有任一角色
     */
    public static function hasAnyRole(mixed $userId, array $roles): bool
    {
        return !empty(array_intersect(self::getUserRoles($userId), $roles));
    }

    /**
     * 检查用户是否有所有角色
     */
    public static function hasAllRoles(mixed $userId, array $roles): bool
    {
        $userRoles = self::getUserRoles($userId);

        foreach ($roles as $role) {
            if (!in_array($role, $userRoles, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 添加权限到角色
     */
    public static function addPermissionToRole(string $role, string $permission): void
    {
        if (!isset(self::$roles[$role])) {
            self::$roles[$role] = [];
        }

        if (!in_array($permission, self::$roles[$role], true)) {
            self::$roles[$role][] = $permission;
        }
    }

    /**
     * 从角色移除权限
     */
    public static function removePermissionFromRole(string $role, string $permission): void
    {
        if (isset(self::$roles[$role])) {
            self::$roles[$role] = array_values(array_filter(
                self::$roles[$role],
                fn($p) => $p !== $permission
            ));
        }
    }

    /**
     * 获取所有角色
     */
    public static function roles(): array
    {
        return self::$roles;
    }

    /**
     * 清除所有数据
     */
    public static function clear(): void
    {
        self::$roles = [];
        self::$userRoles = [];
        self::$roleInheritance = [];
    }

    /**
     * 从对象获取用户 ID
     */
    public static function getUserId(object $user): mixed
    {
        return $user->id ?? null;
    }

    /**
     * 检查用户对象是否有权限
     */
    public static function userHasPermission(?object $user, string $permission): bool
    {
        if ($user === null) {
            return false;
        }

        $userId = self::getUserId($user);

        if ($userId === null) {
            return false;
        }

        return self::hasPermission($userId, $permission);
    }

    /**
     * 检查用户对象是否有角色
     */
    public static function userHasRole(?object $user, string $role): bool
    {
        if ($user === null) {
            return false;
        }

        $userId = self::getUserId($user);

        if ($userId === null) {
            return false;
        }

        return self::hasRole($userId, $role);
    }
}
