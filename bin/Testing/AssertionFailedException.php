<?php

declare(strict_types=1);

namespace Bin\Testing;

use Exception;

/**
 * 断言失败异常
 */
class AssertionFailedException extends Exception
{
    protected string $file = '';

    protected int $line = 0;

    public function __construct(string $message, ?string $file = null, ?int $line = null)
    {
        parent::__construct($message);

        if ($file !== null) {
            $this->file = $file;
        }

        if ($line !== null) {
            $this->line = $line;
        }
    }
}
