<?php

declare(strict_types=1);

namespace Bin\Exception;

class UrlGenerationException extends \InvalidArgumentException
{
    public static function forMissingParameters(string $routePath, array $missing): self
    {
        return new self(
            sprintf(
                'Missing required parameters for route [%s]: %s',
                $routePath,
                implode(', ', $missing)
            )
        );
    }
}
