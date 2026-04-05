<?php

declare(strict_types=1);

namespace App\Model;

use Bin\Database\Model;
use Bin\Database\Relations\BelongsTo;

class Post extends Model
{
    protected string $table = 'posts';

    protected array $fillable = ['title', 'content', 'user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
