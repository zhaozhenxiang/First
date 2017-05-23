<?php
if (!function_exists('getUrl')) {
    function getUrl()
    {
        return $_SERVER['PATH_INFO'];
    }
}

if (!function_exists('getMethod')) {
    function getMethod()
    {
        return $_SERVER['REQUEST_METHOD'];
    }
}
if (!function_exists('basePath')) {
    function basePath()
    {
        return BASE_PATH;
    }
}
//
// 还是等view类写出来之后直接在这个函数里面调用view类的内容
//if (!function_exists('view')) {
//    function view($path)
//    {
//
//    }
//}

if (!function_exists('config')) {
    function config($key, $value = null)
    {
        if (is_null($value)) {
            //按照':'分隔
            $layered = explode(':', $key);

            if (empty($layered)) {
                return null;
            }

            $config = include basePath() . '/config/' . $layered[0] . '.php';
            $tmp = $config;

            //从1开始
            for ($i = 1, $count = count($layered); $i < $count; $i ++) {
                $tmp = $tmp[$layered[$i]];
            }

            return $tmp;
        }
        //@todo 设置key值的段落
    }
}

if (!function_exists('app')) {
    /**
     * @power ioc一些class
     * @param $class
     * @return mixed
     */
    function app($class)
    {
        return \Bin\App\App::make($class);
    }
}

if (!function_exists('getKeyByArray')) {

    /**
     * @power array_search函数的变形，对二维数组中一个字段进行搜索。
     * todo 该函数遇到第一个匹配就会停止
     * @param $needle
     * @param $arr
     * @param $key_of_search
     * @return INT|NULL
     */
    function getKeyByArray($needle, $arr, $key_of_search)
    {
        if (is_array($arr)) {
            foreach ($arr as $key => $item) {
                $row = $item;
                if ($row[$key_of_search] === $needle)
                    return $key;
            }
        }
        return NULL;
    }

}