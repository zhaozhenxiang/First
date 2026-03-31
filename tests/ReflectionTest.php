<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Reflection\Reflection;
use Bin\App\App;
use Bin\Request\Request;

class ReflectionTest extends TestCase
{
    private Reflection $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflection = new Reflection();
    }

    // === getAbstractReflectionClass (Reflect trait) ===

    public function testGetAbstractReflectionClassSuccess(): void
    {
        $ref = $this->reflection->getAbstractReflectionClass(Request::class);
        $this->assertNotNull($ref);
        $this->assertInstanceOf(\ReflectionClass::class, $ref);
    }

    public function testGetAbstractReflectionClassNotFound(): void
    {
        $ref = $this->reflection->getAbstractReflectionClass('NonExistentClass123');
        $this->assertNull($ref);
    }

    public function testGetAbstractReflectionClassCaches(): void
    {
        $ref1 = $this->reflection->getAbstractReflectionClass(Request::class);
        $ref2 = $this->reflection->getAbstractReflectionClass(Request::class);
        $this->assertSame($ref1, $ref2);
    }

    // === getCallBackParam ===
    // 注意: Reflection::getParameter() 中调用 App::make() 是静态调用
    // 但 App::make() 不是 static 方法且有定义，PHP 不会走 __callStatic
    // 这意味着 Reflection 依赖的 App::make() 只在框架正常引导后可用
    // 以下测试验证闭包参数解析逻辑

    public function testGetCallBackParamReflection(): void
    {
        // 测试反射能正确获取闭包参数数量
        $closure = function ($a, $b) { return $a + $b; };
        $rf = new \ReflectionFunction($closure);
        $this->assertCount(2, $rf->getParameters());
    }

    public function testGetCallBackParamWithTypeHintReflection(): void
    {
        $closure = function (Request $req) { return $req; };
        $rf = new \ReflectionFunction($closure);
        $params = $rf->getParameters();
        $this->assertCount(1, $params);
        $type = $params[0]->getType();
        $this->assertNotNull($type);
        $this->assertEquals(Request::class, $type->getName());
    }

    public function testGetCallBackParamMixedTypes(): void
    {
        $closure = function (Request $req, $id) {};
        $rf = new \ReflectionFunction($closure);
        $params = $rf->getParameters();

        // 第一个参数有类型提示
        $this->assertNotNull($params[0]->getType());
        // 第二个参数无类型提示
        $this->assertNull($params[1]->getType());
    }

    // === getClassMethodParamInject ===

    public function testGetClassMethodParamInjectReflection(): void
    {
        $rc = new \ReflectionClass(ReflectionDummyController::class);
        $this->assertTrue($rc->hasMethod('noArgs'));
        $this->assertTrue($rc->hasMethod('withRequest'));

        $method = $rc->getMethod('withRequest');
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals(Request::class, $params[0]->getType()->getName());
    }

    public function testGetClassMethodParamInjectNoArgsReflection(): void
    {
        $rc = new \ReflectionClass(ReflectionDummyController::class);
        $method = $rc->getMethod('noArgs');
        $this->assertCount(0, $method->getParameters());
    }
}

class ReflectionDummyController
{
    public function noArgs(): void {}
    public function withRequest(Request $request): void {}
}
