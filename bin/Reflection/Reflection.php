<?php
/************************************************************
 * Copyright (C), 2015-2016, Everyoo Tech. Co., Ltd.
 * @FileName: Reflection.php
 * @Author: zhaozhenxiang       Version :   V.1.0.0       Date: 2017/5/19
 * @Description:     // 模块描述
 ***********************************************************/

namespace Bin\Reflection;


class Reflection
{
    use Reflect;

    /**
     * @power 得到class的method的param的参数的获取
     * @param $class
     * @param $method
     * @return array
     * @throws \Exception
     */
    public function getClassMethodParamInject($class, $method)
    {
        //反射的类,查看该文件是否存在
        $reClass = $this->getAbstractReflectionClass($class);

        return $this->getParameter($reClass->getMethod($method)->getParameters());
    }

    public function getCallBackParam(\Closure $f)
    {
        $ReflectionParameter = (new \ReflectionFunction($f))->getParameters();
        return $this->getParameter($ReflectionParameter);
    }

    /**
     * @power 处理反射的参数,每一个元素都是ReflectionParameter
     * @param array $param
     */
    private function getParameter(array $param)
    {
        //todo 没有处理class的构造函数的参数
        $order = [];
        $nullCount = 0;
        foreach ($param as $key => $item) {
            //没有找到该参数的类型=>null，表示该函数的参数类型是php内置类型
            $tmp = $item->getClass();
            if (is_null($tmp)) {
                $nullCount ++;
                $order[] = NULL;
                $tmp[] = NULL;
                continue;
            }

            $order[] = $tmp->getName();
        }
        //拿到url中的数据
        $urlParam = \Bin\App\App::make(\Bin\Request\Request::class)->getUrlParam();
        if ($nullCount > count($urlParam)) {
            throw new \Exception('param is not enough');
        }

        $param = [];
        $meetCount = 0;
        for ($i = 0, $count = count($order); $i < $count; $i ++) {
            if (is_null($order[$i])) {
                $param[] = $urlParam[$meetCount];
                $meetCount ++;
            } else {
                $param[] = app($order[$i]);
            }
        }

        return $param;
    }
}