### 我想要写一个WEB框架(基本思想抄袭laravel) ###  

#### DOC ####  
```
目前只能写成一个简单的API框架，目标是一个全栈的框架。
```

- 访问方式:http://domain/index.php
- 一切从Route开始
callback Route
```
    $route = new \Bin\Route\RouteCollection();
    $route::get('/get/callback', function(){
        return __LINE__;
    });
```

正则匹配 Route
```
    $route::get('/callback/{no}', function($a){
        return __LINE__ . $a;
    })->with('[0-9]+');
```

Class Route
```
    $route::get('/get/view', 'AA@BB');
```

多匹配 Route
```
$route::getArray(['/rel1' => 'AA@rel', '/a' => 'AA@postA']);
```

Middleware Route

```
//此处'a'对应的是\App\Middleware\A
//A的handle方法返回值为TRUE表示通过中间件
$route::middle(['a' => [1, 2]], function() use ($route){
    $route::get('/middle/1', 'AA@middle');
});

```

#### 接下来要完善的功能 #####  
* reflection(包含ioc、di部分功能)
* aotuload
* request
* response
* middleware
* ioc
* di
* db builder
* view template
* helper function
* db model
* cache
* route regex match(正则匹配)
* route collection
* route group
* exception
* log
* session
* php cli mode

#### 已完成的功能 ####


