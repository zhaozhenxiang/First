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
            $message .= ' ' . implode(', ', array_map(static fn(mixed $id): string => self::formatId($id), $this->ids));
        }

        return $message;
    }

    protected static function formatId(mixed $id): string
    {
        if ($id === null) {
            return 'null';
        }

        if (is_bool($id)) {
            return $id ? 'true' : 'false';
        }

        if ($id === '') {
            return '""';
        }

        if (is_int($id) || is_float($id) || is_string($id)) {
            return (string) $id;
        }

        if ($id instanceof \Stringable) {
            return (string) $id;
        }

        if (is_array($id) || is_object($id)) {
            $encoded = json_encode($id, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (is_string($encoded)) {
                return $encoded;
            }
        }

        return get_debug_type($id);
    }
}
