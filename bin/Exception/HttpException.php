<?php

declare(strict_types=1);

namespace Bin\Exception;

use Exception;
use Throwable;

/**
 * HTTP 异常基类
 */
class HttpException extends Exception
{
    protected int $statusCode;
    protected array $headers;

    public function __construct(
        int $statusCode = 500,
        string $message = '',
        ?Throwable $previous = null,
        array $headers = []
    ) {
        $this->statusCode = $statusCode;
        $this->headers = $headers;

        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
