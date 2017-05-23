<?php
namespace Bin\Route;

use Bin\Response\Response;
use \Bin\Reflection\Reflection;

class RouteAction
{
    public function __construct()
    {

    }

    public static function action()
    {
        $actionAy = RouteCollection::getRoute();

        //先处理middle
        if (FALSE == is_null($actionAy->getMiddle())) {
            $param = $actionAy->getMiddle();
            if (1 != count($param)) {
                throw new \Exception('middle param count must one');
            }
            //查看是否存在
            $class = (new \App\Middleware\Middle())->getClass(array_keys($param['middle'])[0]);
            if (FALSE == $class) {
                throw new \Exception('middleware miss');
            }
            //key为middleware名字，value为middleware参数
            $handleResult = (new $class)->run(array_shift($param));

            if (TRUE !== $handleResult) {
                return new \Bin\Response\Response($handleResult);
            }
        }

        //处理闭包
        if (is_callable($actionAy->getAction())) {
            return self::doCallBack($actionAy->getAction());
        }
        //处理常规
        if (is_string($actionAy->getAction())) {
            list($class, $method) = explode('@', $actionAy->getAction());
            return self::doClassMethod($class, $method);
        }


        throw new Exception("404", 1);
    }

    private static function doCallBack(callable $action)
    {

        return call_user_func_array($action, app(\Bin\Reflection\Reflection::class)->getCallBackParam($action));
        return $action(app('Request')->getUrlParam()[0]);
//        return $action();
    }

    //@todo 这里应该使用反射
//    private static function doClassMethod($class, $method)
//    {
//        $class = '\App\Controllers\\' . $class;
//        //todo 反射class的构造函数的参数
//        $instance = new $class;
//        //todo 反射method函数的参数
//        $response = $instance->$method();
//        echo new Response($response);
//    }
//
    private static function doClassMethod($class, $method)
    {
        $class = '\App\Controllers\\' . $class;
        echo new Response(call_user_func_array(array(new $class, $method), app(Reflection::class)->getClassMethodParamInject($class, $method)));
    }
}