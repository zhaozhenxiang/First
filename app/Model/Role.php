<?php

declare(strict_types=1);

namespace App\Model;

use Bin\Database\Model;
use Bin\Database\Relations\BelongsToMany;

class Role extends Model
{
    protected string $table = 'roles';

    protected array $fillable = ['name'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }
}
