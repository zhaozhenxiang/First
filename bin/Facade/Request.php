<?php
/************************************************************
 * Copyright (C), 2015-2016,
 * @FileName: Request.php
 * @Author: zhaozhenxiang       Version :   V.1.0.0       Date: 2017/5/19
 * @Description:     // 模块描述
 ***********************************************************/

namespace Bin\Facade;


class Request extends Facade
{
    protected function getClassName()
    {
        return \Bin\Request\Request::class;
    }
}