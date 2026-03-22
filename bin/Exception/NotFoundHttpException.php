<?php

declare(strict_types=1);

namespace Bin\Exception;

use Throwable;

/**
 * 404 Not Found 异常
 */
class NotFoundHttpException extends HttpException
{
    public function __construct(string $message = 'Resource not found', ?Throwable $previous = null)
    {
        parent::__construct(404, $message, $previous);
    }
}
