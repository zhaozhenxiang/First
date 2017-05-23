<?php
namespace Bin\Route;

class RouteCollection /*implements \Iterator*/
{
    //使用单例模式
    private static $instance;
    private static $route = [];
    private static $method = [
        'get',
        'post'
    ];

    private static $routeIndex = NULL;
    public function __construct()
    {
        // if (self::$instance instanceof $this) {
        // return self::$instance;
        // }

    }

//    public function current()
//    {
//
//    }
//
//    public function next()
//    {
//
//    }
//
//    public function key()
//    {
//
//    }
//
//    public function valid()
//    {
//
//    }
//
//    public function rewind()
//    {
//
//    }

    /**
     * @power 获取匹配到的路由
     * @return mixed
     * @throws \Exception
     */
    public static function getRoute()
    {
        $path = getUrl();
        $method = getMethod();

        return self::match($method, $path);
    }

    private static function match($method, $path)
    {

        foreach (self::$route as $key => $value) {
            //需要index.php来控制rewrite
//            if ($value['method'] == $method && ($value['path'] === $path || '/index.php' . $value['path'] === $path)) {
            if ($value->getMethod() == $method && ($value->getPath() === $path || '/index.php' . $value->getPath() === $path)) {
                return $value;
            }

//            var_dump($value, $value->getPreg());
            //判断是否存在preg
            if (FALSE == is_null($value->getPreg())) {
                if (TRUE == $value->withSuccess((\Bin\App\App::make(\Bin\Request\Request::class)->getPath()))) {
                    return $value;
                }
            }
        }

        throw new \Exception("ROUTE NO MATCH", 1);
    }

    /*    public function getInstance()
        {
            if (self::$instance instanceof $this) {
                return self::$instance;
            }

            return new static;
        }*/


    private static function action($method, $path, $action)
    {
//        $action = new Route(['method' => $method, 'path' => $path, 'action' => $action]);
        $action = new Route($method, $path, $action);
        array_push(self::$route, $action);
        return $action;
    }

    /**
     * @power 处理middle
     * @param array $param
     * @param \Closure $callback
     */
    public static function middle(array $param, \Closure $callback)
    {
        //先获取当前的路由个数，在获取之后的路由个数，然后给最后获取的路由处理一下
        $currentRouteCount = count(self::$route);
        //先获取本次的结果
        $callback();
        $nowRouteCount = count(self::$route);

        for ($i = $currentRouteCount; $i < $nowRouteCount; $i++) {
            self::$route[$i]->middle(['middle' => $param]);
        }
    }

    /**
     * @power 处理多个get的router
     * @param array $param
     */
    public static function getArray(array $param)
    {
        foreach ($param as $key => $item) {
            self::action('GET', $key, $item);
        }
    }

    public static function __callStatic($method, $param)
    {
        if (!in_array(strtolower($method), self::$method)) {
            throw new Exception("REQUEST METHOD NOT MATCH", 1);
        }

        //@todo 解析$param 。 这的param应该是array
        //@todo 大概是可以这么解析吧
        self::action($method, $param[0], $param[1]);
    }

    public static function get($path, $param)
    {
        return self::action('GET', $path, $param);
    }

    public static function post($path, $param)
    {
        return self::action('POST', $path, $param);
    }
}