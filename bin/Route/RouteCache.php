<?php

declare(strict_types=1);

namespace Bin\Route;

use Bin\App\App;
use RuntimeException;

class RouteCache
{
    private const REQUIRED_ROUTE_KEYS = ['method', 'uri', 'action'];
    private const NULLABLE_STRING_KEYS = ['name', 'domain'];
    private const ARRAY_KEYS = ['where', 'middleware', 'middleware_groups', 'excluded_middleware'];
    private const NULLABLE_ARRAY_KEYS = ['preg'];

    public static function path(?App $app = null): string
    {
        $app ??= App::getInstance();

        return $app->storagePath('routes.php');
    }

    public static function exists(?App $app = null): bool
    {
        return is_file(self::path($app));
    }

    /**
     * @return array{routes: array<int, array<string, mixed>>, fallback: array<string, mixed>|null}|null
     */
    public static function load(?App $app = null): ?array
    {
        $path = self::path($app);

        if (!is_file($path)) {
            return null;
        }

        $payload = require $path;

        if (!is_array($payload)) {
            throw new RuntimeException('Route cache file is invalid: ' . $path);
        }

        if (!array_key_exists('routes', $payload) || !is_array($payload['routes'])) {
            throw new RuntimeException('Route cache file is missing a routes array: ' . $path);
        }

        foreach ($payload['routes'] as $index => $route) {
            self::validateRoute($route, 'route entry [' . $index . ']', $path);
        }

        $fallback = $payload['fallback'] ?? null;

        if ($fallback !== null) {
            self::validateRoute($fallback, 'fallback route', $path);
        }

        return [
            'routes' => $payload['routes'],
            'fallback' => $fallback,
        ];
    }

    /**
     * @param array{routes: array<int, array<string, mixed>>, fallback: array<string, mixed>|null} $payload
     */
    public static function write(array $payload, ?App $app = null): void
    {
        $path = self::path($app);
        $directory = dirname($path);

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create route cache directory: ' . $directory);
        }

        $temporary = $path . '.tmp';
        $contents = self::compile($payload);
        $bytesWritten = @file_put_contents($temporary, $contents);

        if ($bytesWritten === false || $bytesWritten !== strlen($contents)) {
            self::removeTemporaryFile($temporary);

            throw new RuntimeException('Unable to write route cache file: ' . $temporary);
        }

        if (!@rename($temporary, $path)) {
            self::removeTemporaryFile($temporary);

            throw new RuntimeException('Unable to move route cache file into place: ' . $path);
        }
    }

    public static function clear(?App $app = null): bool
    {
        $path = self::path($app);

        if (!is_file($path)) {
            return false;
        }

        if (!@unlink($path)) {
            if (is_file($path)) {
                throw new RuntimeException('Unable to clear route cache file: ' . $path);
            }

            return false;
        }

        return true;
    }

    private static function validateRoute(mixed $route, string $label, string $path): void
    {
        if (!is_array($route)) {
            throw new RuntimeException('Route cache file has invalid ' . $label . ': ' . $path);
        }

        foreach (self::REQUIRED_ROUTE_KEYS as $key) {
            if (!array_key_exists($key, $route)) {
                throw new RuntimeException('Route cache file has invalid ' . $label . ': missing ' . $key . ' in ' . $path);
            }
        }

        if (!is_string($route['method']) || trim($route['method']) === '') {
            throw new RuntimeException('Route cache file has invalid ' . $label . ': method must be a non-empty string in ' . $path);
        }

        if (!is_string($route['uri']) || trim($route['uri']) === '') {
            throw new RuntimeException('Route cache file has invalid ' . $label . ': uri must be a non-empty string in ' . $path);
        }

        if (!self::isCacheableAction($route['action'])) {
            throw new RuntimeException('Route cache file has invalid ' . $label . ': action must be cacheable in ' . $path);
        }

        foreach (self::NULLABLE_STRING_KEYS as $key) {
            if (array_key_exists($key, $route) && $route[$key] !== null && !is_string($route[$key])) {
                throw new RuntimeException('Route cache file has invalid ' . $label . ': ' . $key . ' must be null or string in ' . $path);
            }
        }

        foreach (self::ARRAY_KEYS as $key) {
            if (array_key_exists($key, $route) && !is_array($route[$key])) {
                throw new RuntimeException('Route cache file has invalid ' . $label . ': ' . $key . ' must be an array in ' . $path);
            }
        }

        foreach (self::NULLABLE_ARRAY_KEYS as $key) {
            if (array_key_exists($key, $route) && $route[$key] !== null && !is_array($route[$key])) {
                throw new RuntimeException('Route cache file has invalid ' . $label . ': ' . $key . ' must be null or array in ' . $path);
            }
        }
    }

    private static function isCacheableAction(mixed $action): bool
    {
        if (is_string($action) && $action !== '') {
            return true;
        }

        if (!is_array($action)) {
            return false;
        }

        return isset($action[0], $action[1])
            && is_string($action[0])
            && $action[0] !== ''
            && is_string($action[1])
            && $action[1] !== '';
    }

    private static function removeTemporaryFile(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @param array{routes: array<int, array<string, mixed>>, fallback: array<string, mixed>|null} $payload
     */
    private static function compile(array $payload): string
    {
        return "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . 'return ' . var_export($payload, true) . ";\n";
    }
}
