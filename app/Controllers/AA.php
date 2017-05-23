<?php
namespace App\Controllers;

use \App\Model\User;

class AA extends BaseController
{
    use \Bin\Reflection\Reflect;

    public function BB()
    {
        return \Bin\View\View::make('index.php')->with('a', User::select('select * from test', 'data'));
    }

    public function rel()
    {
        $class = $this->getAbstractReflectionClass('\App\Controllers\BB');
        var_dump($class);
        var_dump($class->isInstantiable());
        var_dump($class->getConstructor());
        var_dump($class->getMethod('test'));
        var_dump($class->getMethod('test')->getParameters());
        var_dump($class->getMethod('test')->isPublic());
        var_dump($class->getMethod('test')->getModifiers());
        var_dump($class->getMethod('test')->getDeclaringClass());
        var_dump($class->getMethod('request')->getParameters());
        foreach ($class->getMethod('request')->getParameters() as $key => $item) {
            var_dump($item->getClass());
        }

        foreach ($class->getMethod('test')->getParameters() as $key => $item) {
            var_dump($item->getClass());
        }
    }

    public function postA()
    {
        return __FILE__ . json_encode($_POST);
    }

    public function middle()
    {
        return 'middle';
    }

    public function pick($a, $b)
    {
        var_dump(app('Request')->getUrlParam(), \Request::getUrlParam());
//        var_dump(__FILE__ . __FUNCTION__ . 'request => ' . json_encode(\Bin\App\App::make(\Bin\Request\Request::class)['urlMatch']) . json_encode(\Bin\App\App::make(\Bin\Request\Request::class)->getUrlParam()) . json_encode(app('Request')->getUrlParam()) . json_encode(\Request::getUrlParam()));
//        var_dump('param 0 =>' . $a);
//        var_dump('param 1 =>' . $b);
    }

    public function pickOne($a)
    {
        var_dump(app('Request')->getUrlParam(), \Request::getUrlParam());
//        var_dump(__FILE__ . __FUNCTION__ . 'request => ' . json_encode(\Bin\App\App::make(\Bin\Request\Request::class)['urlMatch']) . json_encode(\Bin\App\App::make(\Bin\Request\Request::class)->getUrlParam()) . json_encode(app('Request')->getUrlParam()) . json_encode(\Request::getUrlParam()));
//        var_dump('param 0 =>' . $a);
//        var_dump('param 1 =>' . $b);
    }
}