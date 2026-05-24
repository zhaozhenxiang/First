<?php

declare(strict_types=1);

namespace Bin\Route;

use Bin\Request\Request;
use DateTimeInterface;
use InvalidArgumentException;
use RuntimeException;

class SignedUrl
{
    public const SIGNATURE_KEY = 'signature';
    public const EXPIRES_KEY = 'expires';

    public static function signedRoute(string $name, array $parameters = []): string
    {
        return self::route($name, $parameters, null);
    }

    public static function temporarySignedRoute(string $name, DateTimeInterface|int $expiration, array $parameters = []): string
    {
        return self::route($name, $parameters, self::expirationTimestamp($expiration));
    }

    public static function hasValidSignature(Request $request): bool
    {
        $query = $request->query();

        if (
            !isset($query[self::SIGNATURE_KEY])
            || !is_string($query[self::SIGNATURE_KEY])
            || $query[self::SIGNATURE_KEY] === ''
        ) {
            return false;
        }

        $provided = $query[self::SIGNATURE_KEY];
        unset($query[self::SIGNATURE_KEY]);

        if (isset($query[self::EXPIRES_KEY])) {
            $expires = $query[self::EXPIRES_KEY];

            if (!is_scalar($expires) || !ctype_digit((string) $expires)) {
                return false;
            }

            if ((int) $expires < time()) {
                return false;
            }
        }

        $path = parse_url((string) $request->server('REQUEST_URI', '/'), PHP_URL_PATH) ?: '/';
        $canonical = self::canonicalUrl($path, $query);

        try {
            $expected = self::signature($canonical);
        } catch (RuntimeException) {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    private static function route(string $name, array $parameters, ?int $expires): string
    {
        self::assertNoReservedParameters($parameters);

        $url = RouteCollection::url($name, $parameters);
        [$path, $query] = self::splitUrl($url);

        if ($expires !== null) {
            $query[self::EXPIRES_KEY] = $expires;
        }

        $canonical = self::canonicalUrl($path, $query);
        $query[self::SIGNATURE_KEY] = self::signature($canonical);

        return self::canonicalUrl($path, $query);
    }

    private static function assertNoReservedParameters(array $parameters): void
    {
        foreach ([self::SIGNATURE_KEY, self::EXPIRES_KEY] as $reserved) {
            if (array_key_exists($reserved, $parameters)) {
                throw new InvalidArgumentException("Signed route parameters may not contain reserved key [{$reserved}].");
            }
        }
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private static function splitUrl(string $url): array
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $queryString = parse_url($url, PHP_URL_QUERY) ?? '';
        $query = [];

        if ($queryString !== '') {
            $query = self::parseQueryString($queryString);
        }

        return [$path, $query];
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseQueryString(string $queryString): array
    {
        $query = [];

        foreach (explode('&', $queryString) as $pair) {
            if ($pair === '') {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');

            $query[urldecode($key)] = urldecode($value);
        }

        return $query;
    }

    private static function expirationTimestamp(DateTimeInterface|int $expiration): int
    {
        return $expiration instanceof DateTimeInterface
            ? $expiration->getTimestamp()
            : $expiration;
    }

    private static function canonicalUrl(string $path, array $query): string
    {
        $path = '/' . ltrim($path, '/');
        self::sortQuery($query);

        $queryString = http_build_query($query);

        return $queryString === '' ? $path : $path . '?' . $queryString;
    }

    private static function sortQuery(array &$query): void
    {
        ksort($query);

        foreach ($query as &$value) {
            if (is_array($value)) {
                self::sortQuery($value);
            }
        }
    }

    private static function signature(string $canonicalUrl): string
    {
        return hash_hmac('sha256', $canonicalUrl, self::signingKey());
    }

    private static function signingKey(): string
    {
        $key = config('app.key');

        if (!is_string($key) || trim($key) === '') {
            throw new RuntimeException("Unable to sign URL because config('app.key') is not set.");
        }

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if ($decoded !== false && $decoded !== '') {
                return $decoded;
            }
        }

        return $key;
    }
}
