<?php

declare(strict_types=1);

namespace Bin\Route;

/**
 * RESTful 资源路由注册器
 *
 * 将 Route::resource('posts', PostController::class) 展开为标准 CRUD 路由。
 */
class ResourceRegistrar
{
    protected const array RESOURCE_ACTIONS = [
        'index'   => ['GET', ''],
        'create'  => ['GET', '/create'],
        'store'   => ['POST', ''],
        'show'    => ['GET', '/{id}'],
        'edit'    => ['GET', '/{id}/edit'],
        'update'  => ['PUT', '/{id}'],
        'destroy' => ['DELETE', '/{id}'],
    ];

    /** API 排除的动作 */
    protected const array API_EXCLUDED = ['create', 'edit'];

    /** update 动作额外注册 PATCH */
    protected const array PATCH_ALIASES = ['update'];

    public function register(string $name, string $controller, array $options = []): array
    {
        return $this->buildRoutes($name, $controller, $options, []);
    }

    public function apiRegister(string $name, string $controller, array $options = []): array
    {
        return $this->buildRoutes($name, $controller, $options, self::API_EXCLUDED);
    }

    protected function buildRoutes(string $name, string $controller, array $options, array $excludedActions): array
    {
        $routes = [];
        $only = $options['only'] ?? null;
        $except = array_merge($options['except'] ?? [], $excludedActions);
        $names = $options['names'] ?? [];
        $parameters = $options['parameters'] ?? [];

        // 预计算参数名（只算一次）
        $paramName = $this->getParameterName($name, $parameters);
        $base = '/' . trim($name, '/');

        foreach (self::RESOURCE_ACTIONS as $action => [$method, $uriSuffix]) {
            if ($only !== null && !in_array($action, $only, true)) {
                continue;
            }
            if (in_array($action, $except, true)) {
                continue;
            }

            $path = $base . $uriSuffix;
            $path = str_replace('{id}', '{' . $paramName . '}', $path);

            $route = RouteCollection::action($method, $path, $controller . '@' . $action);
            $route->name($names[$action] ?? ($name . '.' . $action));
            $routes[] = $route;

            // PUT 动作额外注册 PATCH
            if (in_array($action, self::PATCH_ALIASES, true)) {
                $patchRoute = RouteCollection::action('PATCH', $path, $controller . '@' . $action);
                $patchRoute->name($names[$action] ?? ($name . '.' . $action));
                $routes[] = $patchRoute;
            }
        }

        return $routes;
    }

    protected function getParameterName(string $resource, array $parameters): string
    {
        if (isset($parameters[$resource])) {
            $param = $parameters[$resource];
            if (str_contains($param, ':')) {
                return explode(':', $param)[1];
            }
            return $param;
        }

        $singular = $this->singularize($resource);
        if (str_contains($singular, '.')) {
            $parts = explode('.', $singular);
            $singular = end($parts);
        }

        return $singular;
    }

    protected function singularize(string $word): string
    {
        if (str_ends_with($word, 'ies')) {
            return substr($word, 0, -3) . 'y';
        }
        if (str_ends_with($word, 'ses') || str_ends_with($word, 'xes') || str_ends_with($word, 'zes')) {
            return substr($word, 0, -2);
        }
        if (str_ends_with($word, 's') && !str_ends_with($word, 'ss')) {
            return substr($word, 0, -1);
        }
        return $word;
    }
}
