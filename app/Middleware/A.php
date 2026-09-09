<?php

declare(strict_types=1);

namespace App\Middleware;

use Bin\Middleware\Middleware;
use Closure;

class A extends Middleware
{
    public function handle(mixed $request, Closure $next): mixed
    {
        return $next($request);
    }
}
