<?php

declare(strict_types=1);

namespace App\Model;

use Bin\Database\Model;
use Bin\Database\Relations\HasMany;
use Bin\Database\Relations\BelongsToMany;

class User extends Model
{
    protected string $table = 'users';

    protected array $fillable = ['name', 'email', 'password'];

    protected array $hidden = ['password'];

    protected array $casts = [
        'is_admin' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 用户的所有文章
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * 用户的角色
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /**
     * 用户的个人资料
     */
    public function profile(): \Bin\Database\Relations\HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * 本地作用域 - 活跃用户
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * 本地作用域 - 管理员
     */
    public function scopeAdmin($query)
    {
        return $query->where('is_admin', true);
    }
}
