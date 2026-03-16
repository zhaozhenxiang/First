<?php

declare(strict_types=1);

namespace Bin\Reflection;

use Bin\App\App;
use Bin\Request\Request;

class Reflection
{
    use Reflect;

    /**
     * 获取类方法的参数注入
     * @throws \Exception
     */
    public function getClassMethodParamInject(string $class, string $method): array
    {
        // 检查缓存
        $cached = $this->getCachedMethodParam($class, $method);
        if ($cached !== null) {
            return $cached;
        }

        $reClass = $this->getAbstractReflectionClass($class);
        if ($reClass === null) {
            throw new \Exception("Class {$class} not found");
        }

        $params = $this->getParameter($reClass->getMethod($method)->getParameters());

        // 缓存结果
        $this->setClassMethod($class, $method, $params);

        return $params;
    }

    /**
     * 获取闭包的参数
     */
    public function getCallBackParam(\Closure $closure): array
    {
        $parameters = (new \ReflectionFunction($closure))->getParameters();
        return $this->getParameter($parameters);
    }

    /**
     * 处理反射的参数
     * @param  \ReflectionParameter[]  $parameters
     * @throws \Exception
     */
    private function getParameter(array $parameters): array
    {
        $order = [];
        $nullCount = 0;

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type === null || !$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                $nullCount++;
                $order[] = null;
                continue;
            }

            $order[] = $type->getName();
        }

        // 获取 URL 中的参数
        $urlParam = App::make(Request::class)->getUrlParam();

        if ($nullCount > count($urlParam)) {
            throw new \Exception('param is not enough');
        }

        $params = [];
        $meetCount = 0;

        foreach ($order as $className) {
            if ($className === null) {
                $params[] = $urlParam[$meetCount];
                $meetCount++;
            } else {
                $params[] = app($className);
            }
        }

        return $params;
    }
}