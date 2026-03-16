<?php

declare(strict_types=1);

namespace Bin\Route;

use App\Middleware\Middle;
use Bin\Response\Response;
use Bin\Reflection\Reflection;
use Exception;

class RouteAction
{
    private function __construct()
    {
    }

    /**
     * 执行路由
     * @throws \Exception
     */
    public static function action(): mixed
    {
        $route = RouteCollection::getRoute();

        // 处理中间件
        $result = self::handleMiddleware($route);
        if ($result !== true) {
            return $result;
        }

        // 分发到 action
        $action = $route->getAction();

        return match (true) {
            is_callable($action) => self::doCallback($action),
            is_string($action) => self::doClassMethod($action),
            default => abort(404)
        };
    }

    /**
     * 处理中间件
     */
    private static function handleMiddleware(Route $route): mixed
    {
        $middle = $route->getMiddle();

        if ($middle === null) {
            return true;
        }

        if (count($middle) !== 1) {
            throw new Exception('middle param count must one');
        }

        $key = array_keys($middle['middle'])[0];
        $params = array_values($middle['middle'])[0];

        $className = (new Middle())->getClass($key);
        if ($className === false) {
            throw new Exception("middleware '{$key}' not found");
        }

        $instance = new $className();
        $result = $instance->run($params);

        return $result === true ? true : new Response($result);
    }

    /**
     * 执行闭包回调
     */
    private static function doCallback(callable $action): mixed
    {
        $params = app(Reflection::class)->getCallBackParam($action);
        return call_user_func_array($action, $params);
    }

    /**
     * 执行控制器方法
     * @throws \Exception
     */
    private static function doClassMethod(string $action): Response
    {
        [$class, $method] = explode('@', $action);
        $fullClass = '\App\Controllers\\' . $class;

        $params = app(Reflection::class)->getClassMethodParamInject($fullClass, $method);
        $instance = new $fullClass();

        return new Response(call_user_func_array([$instance, $method], $params));
    }
}
