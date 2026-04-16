<?php

declare(strict_types=1);

namespace Bin\Response;

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

        if (is_array($payload) && !isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json';
        }

        return new Response($payload, $status, $headers);
    }

    public function json(array $payload, int $status = 200, array $headers = []): Response
    {
        return $this->make($payload, $status, ['Content-Type' => 'application/json'] + $headers);
    }

    public function redirect(string $url, int $status = 302, array $headers = []): Response
    {
        return $this->make('', $status, ['Location' => $url] + $headers);
    }
}
