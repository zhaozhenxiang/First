# M1 改造顺序

## 关联文档

- 主 spec： [M1 主 spec](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-spec.md)
- 任务拆解： [M1 任务拆解](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-tasks.md)
- 测试清单： [M1 测试清单](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-tests.md)
- 索引页： [M1 文档索引](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-index.md)

## 原则

`M1` 必须按依赖顺序推进，不能看到哪里不顺眼就先改哪里。

## 推荐顺序

1. Bootstrap Pipeline
2. Container 装配中心
3. HTTP Kernel 主链路
4. 异常统一出口
5. Response 归一化
6. 路由绑定与 URL 生成

## 为什么这样排

## 1. 先启动，再装配

如果启动流程没统一，后面所有行为都会夹杂时序偶然性。

## 2. 先装配，再主链路

如果容器不是统一装配中心，HTTP 主链路很难真正收口。

## 3. 先主链路，再出口

异常和响应是主链路的出口，不先把主路径固定，出口就只能不断兼容分支。

## 4. 最后固化路由基础设施

URL 生成、绑定语义依赖主链路、异常和响应都已经稳定。

## 不建议的错误顺序

### 错误顺序 1

先补 URL 生成，再改主链路。

问题：

- 会先把表面能力做出来
- 后面主链路一改，URL 和绑定又要返工

### 错误顺序 2

先做大量 Response 子类，再补异常出口。

问题：

- 会得到很多响应类型
- 但依然没有统一出口

### 错误顺序 3

一边做 `M1`，一边扩 Session、Auth、Queue。

问题：

- 主链路问题会被新功能掩盖
- 改动面会迅速失控

## 每一步的停顿检查点

## 第一步后检查

- HTTP / Console 是否共用启动骨架
- Provider 生命周期是否稳定

## 第二步后检查

- 核心服务是否已经统一由容器装配
- 主链路中是否还存在关键直接实例化

## 第三步后检查

- 请求生命周期是否已固定
- 中间件和控制器调度是否清晰

## 第四步后检查

- 异常是否已经统一出口
- HTTP / Console 是否都有稳定错误语义

## 第五步后检查

- 控制器返回值是否都能归一化
- 异常输出是否复用同一响应体系

## 第六步后检查

- 命名路由是否稳定
- URL 生成和绑定是否成为基础设施

## 一句话执行建议

`M1` 的正确做法不是“多做”，而是“按顺序把骨架做稳”。顺序对了，后面的模块自然会更容易接入。
