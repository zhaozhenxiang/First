<?php

declare(strict_types=1);

namespace Bin\Database;

use InvalidArgumentException;

class ModelNotFoundException extends InvalidArgumentException
{
    /**
     * @param array<int, mixed> $ids
     */
    public function __construct(
        protected string $model,
        protected array $ids = []
    ) {
        parent::__construct($this->buildMessage());
    }

    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * @return array<int, mixed>
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    protected function buildMessage(): string
    {
        $message = "No query results for model [{$this->model}]";

        if ($this->ids !== []) {
            $message .= ' ' . implode(', ', array_map(static fn(mixed $id): string => (string) $id, $this->ids));
        }

        return $message;
    }
}
