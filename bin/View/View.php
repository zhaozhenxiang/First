<?php
namespace Bin\View;

class View
{
    const viewPath = BASE_PATH . '/views/';
    const viewSuffix = '.php';
    private static $targetView;
    private static $targetData = [];
    private static $instance;


    public function __construct()
    {

    }

    /**
     * @power 处理path
     * @param $path
     * @return View
     */
    public static function make($path)
    {
        if (FALSE == preg_match('/.+?\.php/', $path)) {
            $path .= \Bin\View\View::viewSuffix;
        }

        self::$targetView = self::viewPath . $path;

        return is_null(self::$instance) ? new self : self::$instance;
    }

    public function with($key, $value)
    {
        if (is_null(self::$targetView)) {
            throw new \Exception('WITH must after the MAKE', 1);
        }
        self::$targetData[$key] = $value;

        return $this;
    }

    public function getView()
    {
        return self::$targetView;
    }

    public function getData()
    {
        return self::$targetData;
    }

    public function __toString()
    {
        return (new Compiler($this))->getPHP();
    }
}