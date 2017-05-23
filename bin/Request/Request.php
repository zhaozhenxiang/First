<?php

namespace Bin\Request;

/**
 * @power 显示请求头的封装
 * Class Request
 * @package Bin\Request
 */
class Request implements \ArrayAccess, \Iterator
{
    //数据存放属性
    protected $data = [];
    //头信息存放属性
    protected $header = [];
    //路径信息存放属性
    protected $path = NULL;

    //初始化信息
    public function __construct()
    {
        foreach ($_REQUEST as $key => $item) {
            $this->$key = $item;
        }


    }

    //获取头信息
    public function getHeader()
    {
        return $_SERVER;
    }

    //获取请求路径
    public function getPath()
    {
        return trim($_SERVER['PATH_INFO'], '/');
    }

    //请求开始时间
    public function getStartTime($format = 'Y-m-d H:i:s')
    {
        return date($format, START_TIME);
    }

    //获取请求类型
    public function getRequestType()
    {
        return trim($_SERVER['REQUEST_METHOD'], '/');
    }

    //获取请求全部信息
    public function getData()
    {
        return $this->data;
    }

    //获取指定字段数据
    public function getField($field)
    {
        return $this->data[$field];
    }


    public function setUrlParam(array $v)
    {
        return $this->urlMatch = $v;
    }

    public function all()
    {
        return $this->data;
    }
    public function getUrlParam()
    {
        return $this->urlMatch;
    }
    public function &__get($key)
    {
        return $this->data[$key];
    }

    public function __set($key, $value)
    {
        $this->data[$key] = $value;
    }

    public function __isset($key)
    {
        return isset($this->data[$key]);
    }

    public function __unset($key)
    {
        unset($this->data[$key]);
    }

    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    public function offsetUnset($offset)
    {
        if ($this->offsetExists($offset)) {
            unset($this->data[$offset]);
        }
    }

    public function offsetGet($offset)
    {
        return $this->offsetExists($offset) ? $this->data[$offset] : null;
    }


    public function rewind()
    {
        return reset($this->data);
    }

    public function current()
    {
        return current($this->data);
    }

    public function key()
    {
        return key($this->data);
    }

    public function next()
    {
        return next($this->data);
    }

    public function valid()
    {
        return key($this->data) !== null;
    }
}