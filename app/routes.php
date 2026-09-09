<?php

declare(strict_types=1);

use App\Model\User;
use Bin\App\App;
use Bin\Request\Request;
use Bin\Response\Response;
use Bin\Route\RouteCollection as Route;

Route::get('/get/callback', function(){
    return __LINE__;
});

Route::get('/callback/{no}', function($a){
    return __LINE__ . $a;
})->with('[0-9]+');

Route::get('/get/view', 'AA@index');
Route::get('/rel', 'AA@rel');

//post请求
Route::post('/post/1', 'AA@postA');

//处理middle相关的代码
Route::middle(['a' => [1, 2]], function(){
    Route::get('/middle/1', 'AA@middle');
});

//todo 处理多个router
Route::getArray(['/rel1' => 'AA@rel', '/a' => 'AA@postA']);

//设置status
Route::get('/get/200', function(){
    return ((new Response())->setStatus(200));
});

//使用app容器类
Route::get('/get/app', function(){
    var_dump(App::getInstance()->make(User::class) === App::getInstance()->make(User::class));
});

//request
Route::get('/get/request', function(){
    $a = new Request;
    var_dump($a->header());
    var_dump($a->getPath());
    var_dump($a->getStartTime());
    var_dump($a->method());
    var_dump($a->all());
    var_dump($a->input('a'));
    var_dump(app('Request'));
    app('Request')->getPath();
    app('Request')->getPath();
});

Route::get('/pick/{no}', 'AA@pickOne')->with('[0-9]+');
Route::get('/pick2/{no}/{no}', 'AA@pick')->with('[0-9]+')->with('[0-9]+');

//di
Route::get('/get/di', 'BB@request');

//var_dump(preg_match('/^\/pick2\/[0-9]+\/[0-9]+$/', '/pick2/1/1'));
//die;

Route::get('/a/a/{no}', function($a){
    return $a;
})->with('[0-9]+');

// ===== IoC 容器行为测试路由 =====
Route::get('/ioc/bind', 'IocTestController@bindMake');
Route::get('/ioc/singleton', 'IocTestController@singleton');
Route::get('/ioc/tagged', 'IocTestController@taggedBindings');
Route::get('/ioc/resolving', 'IocTestController@resolvingCallbacks');
Route::get('/ioc/scoped', 'IocTestController@scopedBinding');
Route::get('/ioc/scoped-singleton', 'IocTestController@scopedWithSingleton');
Route::get('/ioc/conditional', 'IocTestController@conditionalBinding');
Route::get('/ioc/psr11', 'IocTestController@psr11');
Route::get('/ioc/circular', 'IocTestController@circularDependency');
Route::get('/ioc/method-injection', 'IocTestController@methodInjection');
Route::get('/ioc/rebinding', 'IocTestController@rebindingCallback');
Route::get('/ioc/extend', 'IocTestController@extendDecorator');
Route::get('/ioc/app-facade', 'IocTestController@appFacade');
Route::get('/ioc/bind-if', 'IocTestController@conditionalRegister');
Route::get('/ioc/alias', 'IocTestController@aliasResolution');
Route::get('/ioc/batch', 'IocTestController@batchOperations');
Route::get('/ioc/contextual-tagged', 'IocTestController@contextualGiveTagged');
Route::get('/ioc/inspection', 'IocTestController@inspectionMethods');
Route::get('/ioc/flush-forget', 'IocTestController@flushForget');
Route::get('/ioc/mock', 'IocTestController@mockService');
Route::get('/ioc/facade-resolve', 'IocTestController@facadeResolve');
Route::get('/ioc/call-variants', 'IocTestController@callVariants');
Route::get('/ioc/instance', 'IocTestController@instanceBinding');
Route::get('/ioc/multi-extender', 'IocTestController@multiExtender');
Route::get('/ioc/resolving-detail', 'IocTestController@resolvingDetail');
Route::get('/ioc/deep-injection', 'IocTestController@deepInjection');
Route::get('/ioc/closure-factory', 'IocTestController@closureFactory');
Route::get('/ioc/container-flush', 'IocTestController@containerFlush');
Route::get('/ioc/dependency-override', 'IocTestController@dependencyOverride');
Route::get('/ioc/build-stack', 'IocTestController@buildStackInspection');