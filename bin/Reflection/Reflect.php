<?php

declare(strict_types=1);

namespace Bin\Reflection;

trait Reflect
{
    /** @var array<string, object> 全部被反射的类的实例 */
    private static array $classInstance = [];
    /** @var array<string, \ReflectionClass> 全部被反射的类的反射对象 */
    private static array $classReflection = [];
    /** @var array<string, array> 全部被反射的类的方法参数 */
    private static array $classMethod = [];

    /**
     * 根据 class 得到反射类（带缓存）
     */
    public function getAbstractReflectionClass(string $class): ?\ReflectionClass
    {
        // 先在缓存中查找
        if (isset(self::$classReflection[$class])) {
            return self::$classReflection[$class];
        }

        try {
            $reflection = new \ReflectionClass($class);
        } catch (\Exception $e) {
            return null;
        }

        $this->setClassReflection($class, $reflection);

        return $reflection;
    }

    /**
     * 设置 class 的反射类
     */
    private function setClassReflection(string $class, \ReflectionClass $reflection): void
    {
        self::$classReflection[$class] = $reflection;
    }

    /**
     * 设置 class 的实例
     */
    private function setClassInstance(string $class, object $instance): void
    {
        self::$classInstance[$class] = $instance;
    }

    /**
     * 设置 class 的方法参数
     */
    private function setClassMethod(string $class, string $method, array $param): void
    {
        self::$classMethod[$class . $method] = $param;
    }

    /**
     * 获取缓存的方法参数
     */
    protected function getCachedMethodParam(string $class, string $method): ?array
    {
        $key = $class . $method;
        return self::$classMethod[$key] ?? null;
    }

    /**
     * 清除反射缓存
     */
    public static function clearReflectionCache(): void
    {
        self::$classReflection = [];
        self::$classMethod = [];
        self::$classInstance = [];
    }
}