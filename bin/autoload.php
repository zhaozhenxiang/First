<?php
require BASE_PATH .  '/vendor/autoload.php';



//spl_autoload_register(array('\Bin\App\App', 'findClass'), true, false);
spl_autoload_register(array('\Bin\App\App', 'make'), true, false);
spl_autoload_register(array('\Bin\App\App', 'facade'), true, false);
//应该在这里加载路由类
require basePath() . '/app/routes.php';