# facade-expand Tasks

- [x] 创建纯代理型 Facade：Cache、Config、Log、Session、View、Validator（6 个，仅 getClassName + @method 注解）
- [x] 创建 Singleton 型 Facade：Auth、Gate（2 个，通过 getInstance() 解析）
- [x] 创建静态类 Facade：Hash、Cookie、DB（3 个，重写 getInstance 直接返回/调用静态类）
- [x] 创建路由型 Facade：Route、URL（2 个，代理到 RouteCollection 静态方法）
- [x] 更新 App.php 的 $coreAliases 和 $facades 数组
- [x] 编写测试（tests/FacadeExpandTest.php）验证所有 Facade 代理正确
