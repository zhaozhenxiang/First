<?php

declare(strict_types=1);

namespace Bin\Middleware;

use Bin\Exception\HttpException;
use Bin\Request\Request;
use Closure;

class ValidateSignature extends Middleware
{
    public function handle(mixed $request, Closure $next): mixed
    {
        if (!$request instanceof Request || !$request->hasValidSignature()) {
            throw new HttpException(403, 'Invalid signature.');
        }

        return $next($request);
    }
}
