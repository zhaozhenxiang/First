# Tasks: console-artisan-parity

## Phase 1: Console 生命周期

- [x] 定义 Console Kernel 与 CLI 入口职责
- [x] 让命令运行经过统一应用 bootstrapping

## Phase 2: 命令模型

- [x] 规范命令发现、注册、别名与实例化行为
- [x] 明确命令签名、输入输出、交互与程序化调用边界

## Phase 3: 容器与扩展

- [x] 让命令执行尽量走容器解析
- [x] 为 closure command 或等价简写命令保留设计空间
- [x] 校正 `make:*` 命令在新 Console 生命周期中的行为

## Phase 4: 验证

- [x] 扩展 Console 与 make command 测试
- [x] 验证与 application-lifecycle、container integration 的联动
