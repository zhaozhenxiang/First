<?php

/************************************************************
 * Copyright (C), 2017-2018,
 * @FileName: Log.php
 * @Author: zzx       Version :   V.1.0.0       Date: 2018/2/10
 * @Description:
 ***********************************************************/
namespace Bin\Log;
class Log
{
    //单例
    private static $instance = NULL;

    //日志的字符串
    private $text = '';

    //日志的路径
    private $path = BASE_PATH . '/storage/file/';

    //日志级别
    private $level = [
        0 => 'INFO',
        1 => 'WARN',
        2 => 'ERROR',
    ];

    /**
     * @power 单例
     */
    public function __construct()
    {
        if (is_null(self::$instance)) {
            self::$instance = $this;
        }

        return self::$instance;
    }

    /**
     * @power 写入数据
     * @param $str
     * @param int $level
     * @return $this
     */
    private function write($str, $level = 0)
    {
        if (empty($this->text)) {
            $this->text .= START_DATE;
        }

        $this->text .= $this->level[$level] . ':' . (string)$str . "\r\n";
        return $this;
    }

    /**
     * @power 写入日志
     * @param $str
     * @return Log
     */
    public function info($str)
    {
        return $this->write($str, 0);
    }

    /**
     * @power 写入日志
     * @param $str
     * @return Log
     */
    public function warn($str)
    {
        return $this->write($str, 1);
    }

    /**
     * @power 写入日志
     * @param $str
     * @return Log
     */
    public function error($str)
    {
        return $this->write($str, 2);
    }

    public function __call($method, $param)
    {

    }

    /**
     * @power 销毁变量时尝试写入
     */
    public function __destory()
    {
        $this->done();
    }

    /**
     * @power 写入
     */
    public function done()
    {
        if (empty($this->text)) {
            return;
        }

        file_put_contents($this->path . date('Y-m-d', time()), $this->text, FILE_APPEND|LOCK_EX);
    }

}