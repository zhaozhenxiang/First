<?php

declare(strict_types=1);

namespace Bin\Auth;

/**
 * Policy 基类
 *
 * 面向对象的授权策略
 */
abstract class Policy
{
    /**
     * 预检查权限
     *
     * 返回 null 时继续检查具体方法
     * 返回 true/false 时直接使用该结果
     */
    public function before(?object $user, string $ability, object $model): ?bool
    {
        return null;
    }

    /**
     * 检查用户是否是管理员
     */
    protected function hasAdminRole(?object $user): bool
    {
        if ($user === null) {
            return false;
        }

        return isset($user->role) && $user->role === 'admin';
    }

    /**
     * 检查用户是否拥有指定角色
     */
    protected function hasRole(?object $user, string $role): bool
    {
        if ($user === null) {
            return false;
        }

        return isset($user->role) && $user->role === $role;
    }

    /**
     * 检查用户是否拥有任一角色
     */
    protected function hasAnyRole(?object $user, array $roles): bool
    {
        if ($user === null) {
            return false;
        }

        if (!isset($user->role)) {
            return false;
        }

        return in_array($user->role, $roles, true);
    }

    /**
     * 检查用户是否是资源的所有者
     */
    protected function isOwner(?object $user, object $model): bool
    {
        if ($user === null) {
            return false;
        }

        $userId = $user->id ?? null;
        $modelUserId = $model->user_id ?? $model->created_by ?? null;

        return $userId !== null && $modelUserId !== null && $userId === $modelUserId;
    }
}
