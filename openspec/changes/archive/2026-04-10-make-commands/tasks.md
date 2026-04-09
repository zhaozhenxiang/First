# make-commands Tasks

- [x] 创建 MakeCommand 抽象基类（validateName, ensureDirectory, writeFile, renderStub, resolveStubPath）
- [x] 创建 Stub 模板文件（bin/Console/Stubs/*.stub，13 个文件）
- [x] 实现 MakeModelCommand（含 --no-migration，自动创建 migration）
- [x] 实现 MakeControllerCommand（含 --resource/--api/--invokable 选项）
- [x] 实现 MakeMiddlewareCommand
- [x] 实现 MakeMigrationCommand（含 --create/--table，时间戳前缀）
- [x] 实现 MakeCommandCommand
- [x] 重构 MakeRequestCommand 继承 MakeCommand
- [x] 实现 MakeFactoryCommand、MakePolicyCommand、MakeObserverCommand
- [x] 编写测试（tests/MakeCommandsTest.php）并验证所有 make 命令
