<?php
namespace Bin\Reflection;

trait Reflect
{
    //全部被反射的类的key value
    private static $classInstance = [];
    //全部被反射的类的key value
    private static $classReflection = [];
    //全部被反射的类的key value
    private static $classMethod = [];
    public function getDoc($class, $method)
    {

    }

    public function getMethodParam($class, $method)
    {

    }

    public function call($class, $method)
    {

    }

    /**
     * @power 根据class得到反射类
     * @param $class
     * @return null|\ReflectionClass
     */
    public function getAbstractReflectionClass($class)
    {
        //@todo 先在self::$class里面查找
        $keys = array_keys(self::$classReflection);
        $search = array_search($class, $keys);

        if (FALSE !== $search) {
            return self::$class[$keys[$search]];
        } else {
            unset($keys);
            unset($search);
        }

        //使用try
        try {
            $reflection = new \ReflectionClass($class);
        } catch(\Exception $e) {
            return NULL;
        }

        $this->setClassReflection($class, $reflection);

        return $reflection;
    }

    /*
     * @power 设置class的反射类
     * @return void
     */
    private function setClassReflection($class, \ReflectionClass $classInstance = null)
    {
        //如果之前有值会被更新
        self::$classReflection[$class] = $classInstance;
    }
    /*
     * @power 设置class的实例
     * @return void
     */
    private function setClassInstance($class, $classInstance = null)
    {
        //如果之前有值会被更新
        self::$classInstance[$class] = $classInstance;
    }

    /*
     * @power 设置class的实例
     * @return void
     */
    private function setClassMethod($class, $method = null, array $param = null)
    {
        //如果之前有值会被更新
        self::$classMethod[$class . $method] = (array)$param;
    }

    private function getMethodParamType(\ReflectionFunctionAbstract $abstract, $method)
    {
        //获取被反射函数的数据类型
    }

    private function getMethodParamMatch(array $paramType)
    {
        //根据函数参数来尽量配置参数
    }
}