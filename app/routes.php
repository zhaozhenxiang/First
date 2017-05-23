<?php
$route = new \Bin\Route\RouteCollection();
$route::get('/get/callback', function(){
    return __LINE__;
});

$route::get('/callback/{no}', function($a){
    return __LINE__ . $a;
})->with('[0-9]+');

$route::get('/get/view', 'AA@BB');
$route::get('/rel', 'AA@rel');

//post请求
$route::post('/post/1', 'AA@postA');

//处理middle相关的代码
$route::middle(['a' => [1, 2]], function() use ($route){
    $route::get('/middle/1', 'AA@middle');
});

//todo 处理多个router
$route::getArray(['/rel1' => 'AA@rel', '/a' => 'AA@postA']);

//设置status
$route::get('/get/200', function(){
    return ((new \Bin\Response\Response())->setStatus('200'));
});

//使用app容器类
$route::get('/get/app', function(){
    var_dump(\Bin\App\App::make(\App\Model\User::class) === \Bin\App\App::make(\App\Model\User::class));
});

//request
$route::get('/get/request', function(){
    $a = new \Bin\Request\Request;
    var_dump($a->getHeader());
    var_dump($a->getPath());
    var_dump($a->getStartTime());
    var_dump($a->getRequestType());
    var_dump($a->getData());
    var_dump($a->getField('a'));
    spl_autoload_register();
    var_dump(spl_autoload_functions());
    var_dump(app('Request'));
    app('Request')->getPath();
    app('Request')->getPath();
    var_dump(\Request::getPath());
});

$route::get('/pick/{no}', 'AA@pickOne')->with('[0-9]+');
$route::get('/pick2/{no}/{no}', 'AA@pick')->with('[0-9]+')->with('[0-9]+');

//di
$route::get('/get/di', 'BB@request');

//var_dump(preg_match('/^\/pick2\/[0-9]+\/[0-9]+$/', '/pick2/1/1'));
//die;

$route::get('/a/a/{no}', function($a){
    return $a;
})->with('[0-9]+');