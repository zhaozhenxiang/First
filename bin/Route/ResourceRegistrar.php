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

    /**
     * 始终保留嵌套前缀的动作（需要父资源上下文）
     * shallow 模式下 show/edit/update/destroy 会去掉父前缀
     */
    protected const array NESTED_ACTIONS = ['index', 'create', 'store'];

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
        $shallow = (bool) ($options['shallow'] ?? false);

        // 点分名称按段拆分：photos.comments → 祖先段各贡献 /{段}/{参数}
        $segments = explode('.', $name);
        $lastSegment = $segments[count($segments) - 1];

        $parentPrefix = '';
        foreach (array_slice($segments, 0, -1) as $ancestor) {
            $ancestor = trim($ancestor, '/');
            $parentPrefix .= '/' . $ancestor . '/{' . $this->getParameterName($ancestor, $parameters) . '}';
        }

        $paramName = $this->getParameterName($lastSegment, $parameters);
        $base = '/' . trim($lastSegment, '/');

        foreach (self::RESOURCE_ACTIONS as $action => [$method, $uriSuffix]) {
            if ($only !== null && !in_array($action, $only, true)) {
                continue;
            }
            if (in_array($action, $except, true)) {
                continue;
            }

            // shallow 模式下成员动作（show/edit/update/destroy）去掉父前缀
            $keepNesting = !$shallow || in_array($action, self::NESTED_ACTIONS, true);

            $path = ($keepNesting ? $parentPrefix : '') . $base . $uriSuffix;
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
        $resource = trim($resource, '/');

        if (isset($parameters[$resource])) {
            $param = $parameters[$resource];
            if (str_contains($param, ':')) {
                return explode(':', $param)[1];
            }
            return $param;
        }

        return static::singularize($resource);
    }

    public static function singularize(string $word): string
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
