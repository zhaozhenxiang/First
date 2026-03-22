<?php

declare(strict_types=1);

namespace Bin\Exception;

use Throwable;

/**
 * 401 Unauthorized 异常
 */
class AuthenticationException extends HttpException
{
    public function __construct(
        string $message = 'Unauthenticated',
        ?Throwable $previous = null,
        array $headers = []
    ) {
        parent::__construct(401, $message, $previous, $headers);
    }
}
