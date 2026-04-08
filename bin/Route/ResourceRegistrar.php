<?php

declare(strict_types=1);

namespace Bin\Route;

/**
 * RESTful 资源路由注册器
 *
 * 将 Route::resource('posts', PostController::class) 展开为 7 条标准 CRUD 路由。
 */
class ResourceRegistrar
{
    /** 默认资源路由映射 */
    protected const array RESOURCE_MAP = [
        'index'   => ['GET', '', 'index'],
        'create'  => ['GET', '/create', 'create'],
        'store'   => ['POST', '', 'store'],
        'show'    => ['GET', '/{id}', 'show'],
        'edit'    => ['GET', '/{id}/edit', 'edit'],
        'update'  => ['PUT', '/{id}', 'update'],
        'destroy' => ['DELETE', '/{id}', 'destroy'],
    ];

    /** API 资源路由（排除 create/edit） */
    protected const array API_MAP = [
        'index'   => ['GET', '', 'index'],
        'store'   => ['POST', '', 'store'],
        'show'    => ['GET', '/{id}', 'show'],
        'update'  => ['PUT', '/{id}', 'update'],
        'destroy' => ['DELETE', '/{id}', 'destroy'],
    ];

    /**
     * 注册资源路由
     *
     * @param string $name 资源名称（如 'posts'）
     * @param string $controller 控制器类名
     * @param array $options 选项：only, except, names, parameters
     * @return Route[]
     */
    public function register(string $name, string $controller, array $options = []): array
    {
        return $this->buildRoutes(self::RESOURCE_MAP, $name, $controller, $options);
    }

    /**
     * 注册 API 资源路由（无 create/edit）
     */
    public function apiRegister(string $name, string $controller, array $options = []): array
    {
        return $this->buildRoutes(self::API_MAP, $name, $controller, $options);
    }

    /**
     * 构建资源路由
     */
    protected function buildRoutes(array $map, string $name, string $controller, array $options): array
    {
        $routes = [];
        $only = $options['only'] ?? null;
        $except = $options['except'] ?? [];
        $names = $options['names'] ?? [];
        $parameters = $options['parameters'] ?? [];

        foreach ($map as $action => [$method, $uriSuffix, $methodSuffix]) {
            // 过滤
            if ($only !== null && !in_array($action, $only, true)) {
                continue;
            }
            if (in_array($action, $except, true)) {
                continue;
            }

            // 构建路径
            $path = '/' . trim($name, '/');
            if ($uriSuffix !== '') {
                $path .= $uriSuffix;
            }

            // 自定义参数名
            $paramName = $this->getParameterName($name, $parameters);
            $path = str_replace('{id}', '{' . $paramName . '}', $path);

            // PATCH 也映射到 update
            $route = RouteCollection::action($method, $path, $controller . '@' . $methodSuffix);

            // 路由命名
            $routeName = $names[$action] ?? ($name . '.' . $action);
            $route->name($routeName);

            $routes[] = $route;
        }

        // PUT 和 PATCH 都映射到 update
        if (($only === null || in_array('update', $only, true)) && !in_array('update', $except, true)) {
            $paramName = $this->getParameterName($name, $parameters);
            $patchPath = '/' . trim($name, '/') . '/{' . $paramName . '}';
            $patchRoute = RouteCollection::action('PATCH', $patchPath, $controller . '@update');
            $routeName = $names['update'] ?? ($name . '.update');
            $patchRoute->name($routeName);
            $routes[] = $patchRoute;
        }

        return $routes;
    }

    /**
     * 获取参数名
     */
    protected function getParameterName(string $resource, array $parameters): string
    {
        if (isset($parameters[$resource])) {
            $param = $parameters[$resource];
            // 支持 'post:slug' 格式
            if (str_contains($param, ':')) {
                return explode(':', $param)[1];
            }
            return $param;
        }

        // 默认：资源名单数
        $singular = $this->singularize($resource);
        // 嵌套资源取最后一段
        if (str_contains($singular, '.')) {
            $parts = explode('.', $singular);
            $singular = end($parts);
        }

        return $singular;
    }

    /**
     * 简单单数化
     */
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
