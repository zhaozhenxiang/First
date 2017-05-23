<?php
namespace Bin\App;

//好像没有什么作用
class App
{
    //使用单例模式
    private static $instance;
    //container
    private static $container = [];

    //自定义ioc的key
    private static $classMap = [
        'Request' => \Bin\Request\Request::class,
        'Response' => \Bin\Response\Response::class,
        'Route' => \Bin\Route\RouteCollection::class,
    ];

    private static $facadeMap = [
        'Request' => \Bin\Facade\Request::class,
    ];

    public function __construct()
    {
        if (self::$instance instanceof $this) {
            return self::$instance;
        }
    }

    public function getInstance()
    {
        if (self::$instance instanceof $this) {
            return self::$instance;
        }
    }

    //facade
    public static function facade($class)
    {
        //先判断是否已经加载了
        if (!isset(self::$facadeMap[$class])) {
            return;
        }

        if (is_file(BASE_PATH . DIRECTORY_SEPARATOR . self::$classMap[$class] . '.php')) {
            class_alias(self::$facadeMap[$class], $class);
            return self::$container[$class] = new $class;
        }
    }

    /**
     * @power 将给定的class加载到static中
     * @param $class
     */
    public static function make($class)
    {
        //先判断是否已经加载了
        if (isset(self::$container[$class])) {
            return self::$container[$class];
        }
        $instance = self::loadClass($class);
        if (!is_null($instance)) {
            //查找是否有mapping
            $mapping = array_filter(self::$classMap, function($v) use ($class) {
                return $class === $v;
            });

            //设置别名
            foreach ($mapping as $key => $val) {
                self::$container[$key] = $instance;
            }

            return self::$container[$class] = $instance;
        }
    }

/*    PRIVATE static function findClass($class)
    {
        //使用map
        //todo 这种信息是否应该放在composer中
        if (isset(self::$classMap[$class])) {
//            require_once BASE_PATH . DIRECTORY_SEPARATOR . self::$classMap[$class] . '.php';
            return class_alias(self::$classMap[$class], $class, TRUE, TRUE);
        }

        is_file(BASE_PATH . DIRECTORY_SEPARATOR . $class) && require_once BASE_PATH . DIRECTORY_SEPARATOR . $class . '.php';

    }*/

    /**
     * @power 加载一个类
     * todo 以后用到反射
     * @param $class
     * @return mixed
     */
    private static function loadClass($class)
    {
        if (is_file(BASE_PATH . DIRECTORY_SEPARATOR . $class . '.php')) {
            return new $class;
        }

        if (isset(self::$classMap[$class])) {
//            class_alias(self::$classMap[$class], $class, TRUE);
            if (is_file(BASE_PATH . DIRECTORY_SEPARATOR . self::$classMap[$class] . '.php') && require_once BASE_PATH . DIRECTORY_SEPARATOR . self::$classMap[$class] . '.php') {
                return new self::$classMap[$class];
            }
        }
    }
}