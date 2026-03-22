<?php

declare(strict_types=1);

namespace App;

use Bin\Database\Model;

class User extends Model
{
    protected string $table = 'users';

    protected array $fillable = ['name', 'email', 'password'];

    protected array $hidden = ['password'];

    protected array $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // 关系：用户有多篇文章
    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id');
    }
}
