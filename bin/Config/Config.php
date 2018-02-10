<?php

/************************************************************
 * Copyright (C), 2017-2018
 * @FileName: Config.php
 * @Author: zzx       Version :   V.1.0.0       Date: 2018/2/10
 * @Description: 获取配置的方法。直接include全部文件
 * 在调用时判断key是否在static变量中存在，如果存在，则返回
 * ArrayObject可以在不存在key的情况下写入key，存在key的情况下更新key
 ***********************************************************/
namespace Bin\Config;

class Config extends \ArrayObject
{
    public function offsetExists($offset)
    {
    }

    public function offsetGet($offset)
    {
    }

    public function offsetSet($offset, $value)
    {
    }

    public function offsetUnset($offset)
    {
    }
}