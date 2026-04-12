<?php

declare(strict_types=1);

namespace Bin\Exception;

use Throwable;

/**
 * 429 Too Many Requests 异常
 */
class RateLimitExceededException extends HttpException
{
    public function __construct(
        string $message = 'Too Many Requests',
        ?Throwable $previous = null,
        array $headers = []
    ) {
        parent::__construct(429, $message, $previous, $headers);
    }
}
