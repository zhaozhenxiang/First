---
name: gen-test
description: 根据源文件生成符合框架测试规范的 TestCase 骨架文件
disable-model-invocation: true
context: fork
---

# 生成测试文件

根据用户指定的源文件路径，自动生成符合本框架测试规范的 TestCase 文件。

## 输入

用户通过 `/gen-test <源文件路径>` 调用，例如：
- `/gen-test bin/Cache/CacheManager.php`
- `/gen-test bin/Database/QueryBuilder.php`

参数 `$ARGUMENTS` 即为源文件路径。

## 执行步骤

### 1. 读取源文件

读取指定路径的 PHP 源文件，提取：
- 命名空间（namespace）
- 类名（class）
- 所有 public/protected 方法（含参数、返回类型）
- 依赖的其他类

### 2. 确定测试文件路径

按照项目约定：
- 源文件：`bin/Foo/Bar.php`（namespace `Bin\Foo\Bar`）
- 测试文件：`tests/BarTest.php`

如果同名测试文件已存在，提示用户确认是否覆盖。

### 3. 生成测试类

生成符合以下规范的测试文件：

```php
<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
// 按需导入被测类和其他依赖

class {ClassName}Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // 初始化被测对象
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // 清理资源
    }

    // 为每个 public 方法生成测试骨架
    public function test{MethodName}{Scenario}(): void
    {
        // TODO: 实现测试逻辑
        $this->assertTrue(true);
    }
}
```

### 4. 测试方法命名规范

根据源文件中每个 public 方法生成至少一个测试方法：
- 正常路径：`test{Method}Returns{Expected}`
- 边界情况：`test{Method}WithEmpty{Param}`
- 异常路径：`test{Method}Throws{Exception}`（如果方法有 throw 声明）

### 5. 使用项目测试框架 API

生成的测试代码使用 `Bin\Testing\TestCase` 提供的断言方法：
- `assertSame($expected, $actual)` - 严格相等
- `assertEquals($expected, $actual)` - 宽松相等
- `assertTrue($condition)` / `assertFalse($condition)`
- `assertNull($value)` / `assertNotNull($value)`
- `assertEmpty($value)` / `assertNotEmpty($value)`
- `assertContains($needle, $haystack)` / `assertNotContains($needle, $haystack)`
- `assertArrayHasKey($key, $array)` / `assertArrayNotHasKey($key, $array)`
- `assertCount($expected, $array)`
- `assertInstanceOf($expected, $actual)`
- `assertThrows($exceptionClass, $callback)`
- `assertMatchesRegularExpression($pattern, $string)`
- `assertStringContainsString($needle, $haystack)`
- `assertGreaterThan($expected, $actual)` / `assertLessThan($expected, $actual)`
- `assertFileExists($file)` / `assertFileDoesNotExist($file)`
- `mock($class)` - 创建 Mock 对象
- `partialMock($class, $methods)` - 部分模拟
- `spy($class)` - 监听对象

### 6. 验证生成的测试

生成后运行：
```bash
php -l tests/{ClassName}Test.php
```
确保无语法错误。

## 注意事项

- 所有文件必须以 `declare(strict_types=1);` 开头
- 测试方法必须以 `test` 前缀命名
- 不要生成 trivial 测试（如只测 getter/setter），除非有逻辑验证
- 对于需要数据库/HTTP 的测试，参考 `tests/IocBehaviorTest.php` 的端到端测试模式
- 优先为 public 方法中包含业务逻辑的方法生成测试
