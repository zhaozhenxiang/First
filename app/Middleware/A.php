<?php

/************************************************************
 * Copyright (C), 2015-2016,
 * @FileName: A.php
 * @Author: zhaozhenxiang       Version :   V.1.0.0       Date: 2017/5/18
 * @Description:     // 模块描述
 ***********************************************************/
namespace App\Middleware;

class A extends \Bin\Middleware\Middleware
{
    protected function handle(array $param)
    {
        var_dump($param);
//        return TRUE;
        return '没有通过middle';
    }
}