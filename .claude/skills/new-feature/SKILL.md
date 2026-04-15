---
name: new-feature
description: 创建新框架模块的完整脚手架（管理器类、接口、Facade、测试文件、配置、辅助函数）
disable-model-invocation: true
context: fork
---

# 新功能模块脚手架

根据用户指定的模块名称，创建符合本框架约定的一整套文件。

## 输入

用户通过 `/new-feature <ModuleName>` 调用，例如：
- `/new-feature Queue`
- `/new-feature Mail`
- `/new-feature Notification`

参数 `$ARGUMENTS` 即为模块名称（PascalCase）。

## 参考模块结构

典型的框架模块包含：

```
bin/{ModuleName}/
├── {ModuleName}Manager.php    # 主管理器类（单例模式）
├── {ModuleName}Interface.php  # 接口（如适用）
└── {SpecificClass}.php        # 具体实现类

tests/{ModuleName}Test.php     # 测试文件
config/{module}.php            # 配置文件（如需要）
```

## 执行步骤

### 1. 确认模块设计

在创建文件之前，先向用户确认：
- **模块名称**: 从参数获取，如 `Queue`
- **核心功能**: 简要描述模块需要做什么
- **是否需要配置文件**: 有外部配置需求时创建 `config/{module}.php`
- **是否需要接口**: 多驱动/多实现时创建接口

使用 AskUserQuestion 确认以上内容。

### 2. 创建目录结构

```bash
mkdir -p bin/{ModuleName}
```

### 3. 创建主管理器类

路径: `bin/{ModuleName}/{ModuleName}Manager.php`

```php
<?php

declare(strict_types=1);

namespace Bin\{ModuleName};

/**
 * {模块描述}管理器
 */
class {ModuleName}Manager
{
    /**
     * 单例实例
     */
    protected static ?self $instance = null;

    /**
     * 获取单例实例
     */
    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * 重置单例（测试用）
     */
    public static function resetInstance(): void
    {
        static::$instance = null;
    }

    // TODO: 实现核心方法
}
```

### 4. 创建测试文件

路径: `tests/{ModuleName}Test.php`

```php
<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\{ModuleName}\{ModuleName}Manager;

class {ModuleName}Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        {ModuleName}Manager::resetInstance();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        {ModuleName}Manager::resetInstance();
    }

    public function testGetInstanceReturnsSingleton(): void
    {
        $instance1 = {ModuleName}Manager::getInstance();
        $instance2 = {ModuleName}Manager::getInstance();

        $this->assertSame($instance1, $instance2);
    }
}
```

### 5. 创建配置文件（如需要）

路径: `config/{module}.php`

```php
<?php

declare(strict_types=1);

return [
    'default' => 'array',
    'connections' => [
        // TODO: 配置连接
    ],
];
```

### 6. 注册辅助函数（如需要）

在 `bin/Func/helpers.php` 中添加：

```php
function {module}(): {ModuleName}Manager
{
    return {ModuleName}Manager::getInstance();
}
```

### 7. 更新 CLAUDE.md

在 CLAUDE.md 中添加新模块的文档，包括：
- 文件路径说明
- 使用示例
- 配置说明

### 8. 验证

创建完成后运行：
```bash
php -l bin/{ModuleName}/{ModuleName}Manager.php
php -l tests/{ModuleName}Test.php
php test tests/{ModuleName}Test.php
```

## 模块命名规范

| 层级 | 命名 | 示例 |
|------|------|------|
| 目录 | PascalCase | `bin/Queue/` |
| 管理器 | {Name}Manager | `QueueManager` |
| 接口 | {Name}Interface | `QueueInterface` |
| 测试 | {Name}Test | `QueueTest` |
| 配置 | snake_case | `config/queue.php` |
| 辅助函数 | snake_case | `queue()` |
| 命名空间 | `Bin\{Name}` | `Bin\Queue` |

## 注意事项

- 所有文件必须以 `declare(strict_types=1);` 开头
- 管理器类使用单例模式（参考 `CacheManager`、`SessionManager`）
- 如果模块有多种驱动（如 cache 有 file/redis/array），创建接口 + 多个实现
- 测试模式需要支持 `resetInstance()` 以避免测试间相互影响
- 参考 CLAUDE.md 中的现有模块文档格式
