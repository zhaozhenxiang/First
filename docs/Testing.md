# 测试系统使用指南

本框架提供了完整的单元测试系统，类似于 PHPUnit 的功能。

## 目录

- [快速开始](#快速开始)
- [编写测试](#编写测试)
- [断言](#断言)
- [Mock 对象](#mock-对象)
- [数据库测试](#数据库测试)
- [运行测试](#运行测试)

## 快速开始

### 创建测试

在 `tests/` 目录下创建测试文件：

```php
<?php

namespace Tests;

use Bin\Testing\TestCase;

class UserTest extends TestCase
{
    public function testCreateUser(): void
    {
        $user = new User();
        $user->name = 'John';
        $user->email = 'john@example.com';

        $this->assertEquals('John', $user->name);
        $this->assertEquals('john@example.com', $user->email);
    }
}
```

### 运行测试

```bash
# 运行所有测试
php test

# 详细输出
php test --verbose

# 失败时停止
php test --stop-on-failure

# 运行指定测试文件
php test tests/UserTest.php
```

## 编写测试

### 测试类结构

```php
<?php

namespace Tests;

use Bin\Testing\TestCase;

class MyTest extends TestCase
{
    // 所有测试开始前执行一次
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // 初始化代码
    }

    // 所有测试结束后执行一次
    public static function tearDownAfterClass(): void
    {
        // 清理代码
        parent::tearDownAfterClass();
    }

    // 每个测试方法前执行
    protected function setUp(): void
    {
        parent::setUp();
        // 初始化代码
    }

    // 每个测试方法后执行
    protected function tearDown(): void
    {
        // 清理代码
        parent::tearDown();
    }

    // 测试方法（必须以 test 开头）
    public function testSomething(): void
    {
        $this->assertTrue(true);
    }
}
```

## 断言

### 基础断言

```php
// 相等
$this->assertEquals(4, 2 + 2);        // 宽松比较
$this->assertSame(4, 2 + 2);          // 严格比较
$this->assertNotEquals(5, 2 + 2);
$this->assertNotSame(5, 2 + 2);

// 布尔值
$this->assertTrue(true);
$this->assertFalse(false);

// 空值检查
$this->assertNull(null);
$this->assertNotNull('value');

// 空/非空
$this->assertEmpty([]);
$this->assertNotEmpty([1]);

// 包含
$this->assertContains(2, [1, 2, 3]);
$this->assertNotContains(5, [1, 2, 3]);
$this->assertStringContainsString('world', 'hello world');
```

### 数组断言

```php
$this->assertArrayHasKey('key', ['key' => 'value']);
$this->assertArrayNotHasKey('foo', ['key' => 'value']);
$this->assertCount(3, [1, 2, 3]);
```

### 字符串断言

```php
$this->assertStringContainsString('needle', 'haystack');
$this->assertStringStartsWith('hello', 'hello world');
$this->assertStringEndsWith('world', 'hello world');
$this->assertMatchesRegularExpression('/\d+/', '123');
```

### 数值断言

```php
$this->assertGreaterThan(5, 10);
$this->assertGreaterThanOrEqual(5, 5);
$this->assertLessThan(10, 5);
$this->assertLessThanOrEqual(5, 5);
```

### 类型断言

```php
$this->assertInstanceOf(\ArrayObject::class, new \ArrayObject());
$this->assertIsType('array', [1, 2, 3]);
$this->assertIsType('string', 'hello');
$this->assertIsType('int', 123);
```

### 文件断言

```php
$this->assertFileExists('/path/to/file');
$this->assertFileDoesNotExist('/path/to/file');
$this->assertDirectoryExists('/path/to/dir');
```

### 异常断言

```php
$this->assertThrows(\InvalidArgumentException::class, function () {
    throw new \InvalidArgumentException('Test');
});

// 自定义异常消息
$this->assertThrows(\RuntimeException::class, function () {
    throw new \RuntimeException('Error occurred');
});
```

### 其他断言

```php
// 跳过测试
$this->markTestSkipped('原因说明');

// 标记测试为不完整
$this->markTestIncomplete('待实现');

// 直接失败
$this->fail('强制失败');
```

## Mock 对象

### 创建 Mock

```php
// 创建模拟对象
$userMock = $this->mock(User::class);

// 设置期望
$userMock->expects('save')->once()->andReturn(true);

// 获取模拟实例
$user = $userMock->make();
$user->save(); // 会检查期望
```

### 方法期望

```php
$mock = $this->mock(User::class);

// 期望被调用一次
$mock->expects('getName')->once()->andReturn('John');

// 期望被调用两次
$mock->expects('save')->twice();

// 期望被调用指定次数
$mock->expects('getEmail')->times(3);

// 至少调用一次
$mock->allows('getName')->atLeast()->once();

// 设置返回值
$mock->expects('getAge')->andReturn(25);

// 设置返回回调
$mock->expects('calculate')->andReturnReturn(function ($a, $b) {
    return $a + $b;
});

// 期望指定参数
$mock->expects('update')->with('John', 'john@example.com');
```

### Spy（监听）

```php
// 创建 spy - 记录调用但不检查期望
$spy = $this->spy(User::class);
$user = $spy->make();

// ... 执行代码 ...

// 验证调用次数
$this->assertSame(1, $spy->getCallsCount('save'));
```

## 数据库测试

### 数据库测试基类

继承 `TestSuite` 类自动处理数据库迁移：

```php
<?php

namespace Tests;

use Bin\Testing\TestSuite;
use App\Model\User;

class UserModelTest extends TestSuite
{
    public function testCreateUser(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->assertNotNull($user->id);
        $this->assertEquals('John Doe', $user->name);
    }

    public function testQueryUser(): void
    {
        User::create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
        ]);

        $user = User::where('email', 'jane@example.com')->first();

        $this->assertNotNull($user);
        $this->assertEquals('Jane', $user->name);
    }
}
```

### 数据库断言

```php
// 断言表存在
$this->assertTableExists('users');

// 断言表不存在
$this->assertTableNotExists('posts');

// 断言列存在
$this->assertColumnExists('users', 'email');

// 断言列不存在
$this->assertColumnNotExists('users', 'password');

// 断言记录存在
$this->assertDatabaseHas('users', [
    'email' => 'john@example.com'
]);

// 断言记录不存在
$this->assertDatabaseMissing('users', [
    'email' => 'notfound@example.com'
]);

// 断言记录数量
$this->assertDatabaseCount('users', 5);
```

## 运行测试

### 命令行

```bash
# 运行所有测试
php test

# 详细输出
php test --verbose

# 失败时停止
php test --stop-on-failure

# 运行指定测试文件
php test tests/UserTest.php
```

### 输出示例

```
Testing Framework
==================

Tests\UserTest
  ✓ testCreateUser
  ✓ testQueryUser

Tests\CollectionTest
  ✓ testFilter
  ✓ testMap
  ...

Tests:  25, ✓ 25 passed in 123.45ms
```

### 失败输出

```
Tests\UserTest
  ✗ testCreateUser
      Failed asserting that two values are equal.
      Expected: 'John'
      Actual: 'Jane'
      at tests/UserTest.php:15

Tests:  25, ✗ 1 failed

1) Tests\UserTest::testCreateUser
   Failed asserting that two values are equal.
   Expected: 'John'
   Actual: 'Jane'
   at tests/UserTest.php:15

Tests:  25, ✗ 1 failed, ✓ 24 passed in 123.45ms
```

## 最佳实践

### 1. 测试命名

```php
// 好的命名
public function testCreateUserWithValidData(): void { }
public function testUserCannotBeCreatedWithoutEmail(): void { }
public function testUserCanBeDeleted(): void { }

// 避免模糊的命名
public function testUser(): void { }  // 不清楚测试什么
public function test1(): void { }      // 没有意义
```

### 2. 一个测试一个行为

```php
// 好的做法
public function testUserCreationSavesToDatabase(): void
{
    $user = User::create(['name' => 'John']);
    $this->assertDatabaseHas('users', ['name' => 'John']);
}

public function testUserCreationReturnsUserInstance(): void
{
    $user = User::create(['name' => 'John']);
    $this->assertInstanceOf(User::class, $user);
}

// 避免在测试中测试多个行为
public function testUser(): void
{
    $this->assertNotNull(User::create([]));
    $this->assertTrue(User::find(1)->delete());
    // 太多行为，难以定位问题
}
```

### 3. 使用 setUp/tearDown

```php
class UserServiceTest extends TestCase
{
    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UserService();
    }

    protected function tearDown(): void
    {
        unset($this->service);
        parent::tearDown();
    }

    public function testServiceCanCreateUser(): void
    {
        $user = $this->service->create('John', 'john@example.com');
        $this->assertInstanceOf(User::class, $user);
    }
}
```

### 4. 测试边界条件

```php
public function testValidationAcceptsValidEmail(): void
{
    $result = $this->validator->validate(['email' => 'john@example.com']);
    $this->assertTrue($result);
}

public function testValidationRejectsInvalidEmail(): void
{
    $result = $this->validator->validate(['email' => 'not-an-email']);
    $this->assertFalse($result);
}

public function testValidationRejectsEmptyEmail(): void
{
    $result = $this->validator->validate(['email' => '']);
    $this->assertFalse($result);
}

public function testValidationRejectsNullEmail(): void
{
    $result = $this->validator->validate(['email' => null]);
    $this->assertFalse($result);
}
```

### 5. 使用数据提供者（手动实现）

```php
public function testEmailValidation(): void
{
    $cases = [
        ['john@example.com', true],
        ['not-an-email', false],
        ['', false],
        ['@example.com', false],
    ];

    foreach ($cases as [$email, $expected]) {
        $result = $this->validator->validate(['email' => $email]);
        $this->assertSame($expected, $result, "Email: {$email}");
    }
}
```

## 测试套件

### 单元测试

```php
class CollectionTest extends TestCase
{
    public function testFilter(): void
    {
        $collection = Collection::make([1, 2, 3, 4]);
        $filtered = $collection->filter(fn($n) => $n > 2);
        $this->assertEquals([3, 4], array_values($filtered));
    }
}
```

### 集成测试

```php
class UserIntegrationTest extends TestSuite
{
    public function testUserCreationFlow(): void
    {
        // 创建用户
        $user = User::create([
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        // 验证数据库
        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);

        // 验证查询
        $found = User::find($user->id);
        $this->assertEquals('John', $found->name);
    }
}
```

## 常见问题

### Q: 如何跳过测试？

```php
public function testNotImplemented(): void
{
    $this->markTestSkipped('功能尚未实现');
}
```

### Q: 如何测试异常？

```php
public function testThrowsException(): void
{
    $this->assertThrows(\InvalidArgumentException::class, function () {
        throw new \InvalidArgumentException('Invalid argument');
    });
}
```

### Q: 如何测试私有方法？

优先通过公共接口测试私有行为。私有方法通常是实现细节，直接测试会让
测试和内部结构耦合。

如果在受支持的 PHP 版本上确实需要使用反射，可以直接调用目标方法：

```php
public function testPrivateMethod(): void
{
    $class = new MyClass();
    $method = new \ReflectionMethod($class, 'privateMethod');
    $result = $method->invoke($class);
    $this->assertEquals('expected', $result);
}
```

### Q: 如何模拟时间？

使用依赖注入和时间接口：

```php
class TimeServiceTest extends TestCase
{
    public function testGetCurrentTime(): void
    {
        $time = new \DateTime('2024-01-01 12:00:00');
        $service = new TimeService($time);
        $this->assertEquals('2024-01-01 12:00:00', $service->now());
    }
}
```

## Runtime Compatibility

First supports PHP 8.3 and newer. Framework code must not emit PHP deprecation
warnings when linted with `E_ALL` on the currently supported runtime.

Runtime compatibility checks:

```bash
php test --pattern=RuntimeCompatibilityTest.php
```

The compatibility test lints all files under `bin/` with deprecation reporting
enabled and scans `bin/` plus `tests/` for deprecated reflection
`setAccessible()` calls. When PHP introduces new deprecations, fix framework
signatures or test helpers instead of suppressing warnings globally.
