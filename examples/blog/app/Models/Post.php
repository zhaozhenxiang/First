<?php

declare(strict_types=1);

namespace App;

use Bin\Database\Model;

class Post extends Model
{
    protected string $table = 'posts';

    protected array $fillable = ['title', 'content', 'user_id', 'status', 'published_at'];

    protected array $casts = [
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // 关系：文章属于用户
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // 获取已发布的文章
    public static function published()
    {
        return self::where('status', 'published');
    }
}
