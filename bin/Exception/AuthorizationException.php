<?php

declare(strict_types=1);

namespace Bin\Exception;

use Throwable;

/**
 * 403 Forbidden 异常
 */
class AuthorizationException extends HttpException
{
    public function __construct(
        string $message = 'This action is unauthorized',
        ?Throwable $previous = null
    ) {
        parent::__construct(403, $message, $previous);
    }
}
