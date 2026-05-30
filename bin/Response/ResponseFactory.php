<?php

declare(strict_types=1);

namespace Bin\Response;

use JsonSerializable;

class ResponseFactory
{
    public function make(mixed $payload = '', int $status = 200, array $headers = []): Response
    {
        if ($payload instanceof Response) {
            foreach ($headers as $name => $value) {
                $payload->setHeader($name, $value);
            }

            return $payload;
        }

        if (is_array($payload) || $payload instanceof JsonSerializable) {
            return $this->json($payload, $status, $headers);
        }

        if (is_scalar($payload)) {
            $payload = (string) $payload;
        }

        return new Response($payload, $status, $headers);
    }

    public function json(mixed $payload, int $status = 200, array $headers = []): Response
    {
        $headers = array_merge(['Content-Type' => 'application/json'], $headers);

        return new Response(json_encode($payload, JSON_THROW_ON_ERROR), $status, $headers);
    }

    public function redirect(string $url, int $status = 302, array $headers = []): Response
    {
        return $this->make('', $status, ['Location' => $url] + $headers);
    }
}
