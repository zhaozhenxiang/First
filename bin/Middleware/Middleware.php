<?php

/************************************************************
 * Copyright (C), 2015-2016, Everyoo Tech. Co., Ltd.
 * @FileName: Middleware.php
 * @Author: zhaozhenxiang       Version :   V.1.0.0       Date: 2017/5/18
 * @Description:     // 模块描述
 ***********************************************************/
namespace Bin\Middleware;

abstract class Middleware
{

    /**
     * @power 获取上下文
     */
    protected function getContext()
    {
        return;
    }

    /**
     * @power
     * @return 该函数函数返回TRUE该可以继续
     */
    protected abstract function handle(array $param);

    /**
     * @power 调用入口
     * @return mixed
     */
    public function run(array $param)
    {

        return $assert = static::handle($param);

        //todo 处理handle的返回值
        if (TRUE ===  $assert) {

        }
    }
}