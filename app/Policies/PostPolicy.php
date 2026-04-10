<?php

declare(strict_types=1);

namespace App\Policies;

use Bin\Auth\Policy;

/**
 * PostPolicy 授权策略
 */
class PostPolicy extends Policy
{
    /**
     * 查看权限
     */
    public function view(?object $user, object $post): bool
    {
        return $this->isOwner($user, $post);
    }

    /**
     * 创建权限
     */
    public function create(?object $user): bool
    {
        return $user !== null;
    }

    /**
     * 更新权限
     */
    public function update(?object $user, object $post): bool
    {
        return $this->isOwner($user, $post);
    }

    /**
     * 删除权限
     */
    public function delete(?object $user, object $post): bool
    {
        return $this->isOwner($user, $post);
    }
}
