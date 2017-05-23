<?php
/************************************************************
 * Copyright (C), 2015-2016, Everyoo Tech. Co., Ltd.
 * @FileName: BB.php
 * @Author: zzx       Version :   V.1.0.0       Date: 2016/2/29
 * @Description:     // 模块描述
 ***********************************************************/

namespace App\Controllers;

use \Bin\Request\Request;

class BB
{
    public function __construct()
    {
        return [];
    }

    public function test($a)
    {

    }

    public function request(Request $a)
    {
        var_dump($a, $a->a, $a->all());
    }
}