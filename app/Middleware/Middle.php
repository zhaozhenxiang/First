<?php

/************************************************************
 * Copyright (C), 2015-2016, Everyoo Tech. Co., Ltd.
 * @FileName: middle.php
 * @Author: zhaozhenxiang       Version :   V.1.0.0       Date: 2017/5/18
 * @Description:     // 模块描述
 ***********************************************************/
namespace App\Middleware;

class Middle
{
    private $mapping = [
        'a' => \App\Middleware\A::class,
    ];

    /**
     * @power 获取name
     * @param $name
     * @return bool
     */
    public function getClass($name)
    {
        return isset($this->mapping[$name]) ? $this->mapping[$name] : FALSE;
    }
}