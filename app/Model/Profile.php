<?php

declare(strict_types=1);

namespace App\Model;

use Bin\Database\Model;
use Bin\Database\Relations\BelongsTo;

class Profile extends Model
{
    protected string $table = 'profiles';

    protected array $fillable = ['bio', 'user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
