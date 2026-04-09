<?php

declare(strict_types=1);

namespace Bin\Facade;

use Bin\Route\RouteCollection;

/**
 * Route Facade - 静态代理路由集合
 *
 * @method static \Bin\Route\Route get(string $path, mixed $action)
 * @method static \Bin\Route\Route post(string $path, mixed $action)
 * @method static \Bin\Route\Route put(string $path, mixed $action)
 * @method static \Bin\Route\Route patch(string $path, mixed $action)
 * @method static \Bin\Route\Route delete(string $path, mixed $action)
 * @method static \Bin\Route\Route options(string $path, mixed $action)
 * @method static void group(array $attributes, \Closure $callback)
 * @method static void matchMethods(array $methods, string $path, mixed $action)
 * @method static void any(string $path, mixed $action)
 * @method static array resource(string $name, string $controller, array $options = [])
 * @method static array apiResource(string $name, string $controller, array $options = [])
 * @method static \Bin\Route\Route fallback(mixed $action)
 * @method static \Bin\Route\Route redirect(string $path, string $destination, int $status = 302)
 * @method static string url(string $name, array $params = [])
 * @method static void middle(array $param, \Closure $callback)
 * @method static void model(string $key, string $class, ?callable $callback = null)
 * @method static void bind(string $key, callable $resolver)
 */
class Route extends Facade
{
    protected function getClassName(): string
    {
        return RouteCollection::class;
    }

    protected static function getInstance(): object
    {
        // RouteCollection 是纯静态类
        static $instance;
        return $instance ??= new class {
            public function __call(string $method, array $args): mixed
            {
                return RouteCollection::{$method}(...$args);
            }
        };
    }
}
