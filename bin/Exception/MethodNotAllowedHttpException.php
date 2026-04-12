<?php

declare(strict_types=1);

namespace Bin\Exception;

use Throwable;

/**
 * 405 Method Not Allowed 异常
 */
class MethodNotAllowedHttpException extends HttpException
{
    /**
     * @param array<string> $allowedMethods  允许的 HTTP 方法列表
     */
    public function __construct(
        string $message = 'Method Not Allowed',
        array $allowedMethods = [],
        ?Throwable $previous = null
    ) {
        $headers = [];
        if ($allowedMethods !== []) {
            $headers['Allow'] = implode(', ', $allowedMethods);
        }

        parent::__construct(405, $message, $previous, $headers);
    }
}
