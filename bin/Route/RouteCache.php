<?php

declare(strict_types=1);

namespace Bin\Route;

use Bin\App\App;
use RuntimeException;

class RouteCache
{
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

        return [
            'routes' => $payload['routes'],
            'fallback' => $payload['fallback'] ?? null,
        ];
    }

    /**
     * @param array{routes: array<int, array<string, mixed>>, fallback: array<string, mixed>|null} $payload
     */
    public static function write(array $payload, ?App $app = null): void
    {
        $path = self::path($app);
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $temporary = $path . '.tmp';
        file_put_contents($temporary, self::compile($payload));
        rename($temporary, $path);
    }

    public static function clear(?App $app = null): bool
    {
        $path = self::path($app);

        if (!is_file($path)) {
            return false;
        }

        unlink($path);

        return true;
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
