<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;

/**
 * IoC 容器行为测试（HTTP 端到端）
 *
 * 启动 PHP 内置服务器，通过 HTTP 请求验证 IoC 容器各项功能
 *
 * 运行方式：php test tests/IocBehaviorTest.php
 * 需要端口 9876 可用
 */
class IocBehaviorTest extends TestCase
{
    private static string $serverUrl = 'http://127.0.0.1:9876';

    private static bool $serverStarted = false;

    private static bool $serverChecked = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureServerStarted();
    }

    /**
     * 确保 PHP 内置服务器已启动（每个测试文件只启动一次）
     */
    private function ensureServerStarted(): void
    {
        if (self::$serverChecked) {
            if (!self::$serverStarted) {
                $this->markTestSkipped('Test server is not available');
            }
            return;
        }

        self::$serverChecked = true;

        $docRoot = basePath('/public');

        // 先检查端口是否已有服务器在运行
        $fp = @fsockopen('127.0.0.1', 9876, $errno, $errstr, 1);
        if ($fp !== false) {
            fclose($fp);
            self::$serverStarted = true;
            return;
        }

        // 启动 PHP 内置服务器
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', '/dev/null', 'w'],
        ];

        $process = proc_open(
            "php -S 127.0.0.1:9876 -t {$docRoot}",
            $descriptors,
            $pipes
        );

        if (!is_resource($process)) {
            $this->markTestSkipped('Could not start PHP built-in server');
            return;
        }

        // 等待服务器就绪（最多 2 秒）
        for ($i = 0; $i < 20; $i++) {
            usleep(100000);
            $fp = @fsockopen('127.0.0.1', 9876, $errno, $errstr, 1);
            if ($fp !== false) {
                fclose($fp);
                self::$serverStarted = true;
                return;
            }
        }

        $this->markTestSkipped('PHP built-in server did not start in time');
    }

    // ===== 测试方法 =====

    public function testBindMake(): void
    {
        $data = $this->getJson('/ioc/bind');

        $this->assertEquals('bind_make', $data['test']);
        $this->assertTrue($data['different_instances']);
        $this->assertEquals('stdClass', $data['class']);
    }

    public function testSingleton(): void
    {
        $data = $this->getJson('/ioc/singleton');

        $this->assertEquals('singleton', $data['test']);
        $this->assertTrue($data['same_instance']);
    }

    public function testTaggedBindings(): void
    {
        $data = $this->getJson('/ioc/tagged');

        $this->assertEquals('tagged_bindings', $data['test']);
        $this->assertEquals(2, $data['count']);
    }

    public function testResolvingCallbacks(): void
    {
        $data = $this->getJson('/ioc/resolving');

        $this->assertEquals('resolving_callbacks', $data['test']);
        $this->assertContains('global_resolving', $data['order']);
        $this->assertContains('per_abstract_resolving', $data['order']);
        $this->assertContains('global_after_resolving', $data['order']);
    }

    public function testScopedBinding(): void
    {
        $data = $this->getJson('/ioc/scoped');

        $this->assertEquals('scoped_binding', $data['test']);
        $this->assertTrue($data['same_in_scope']);
        $this->assertTrue($data['different_after_reset']);
    }

    public function testScopedWithSingleton(): void
    {
        $data = $this->getJson('/ioc/scoped-singleton');

        $this->assertEquals('scoped_with_singleton', $data['test']);
        $this->assertTrue($data['singleton_survives']);
        $this->assertTrue($data['scoped_resets']);
    }

    public function testConditionalBinding(): void
    {
        $data = $this->getJson('/ioc/conditional');

        $this->assertEquals('conditional_binding', $data['test']);
        $this->assertStringContainsString('FileLogger', $data['default_logger']);
        $this->assertStringContainsString('CloudLogger', $data['audit_logger']);
    }

    public function testPsr11(): void
    {
        $data = $this->getJson('/ioc/psr11');

        $this->assertEquals('psr11', $data['test']);
        $this->assertTrue($data['implements_interface']);
        $this->assertTrue($data['has_bound']);
        $this->assertFalse($data['has_missing']);
        $this->assertTrue($data['get_returns_instance']);
        $this->assertTrue($data['get_throws_on_missing']);
    }

    public function testCircularDependency(): void
    {
        $data = $this->getJson('/ioc/circular');

        $this->assertEquals('circular_dependency', $data['test']);
        $this->assertTrue($data['exception_thrown']);
        $this->assertTrue($data['message_contains_circular']);
    }

    public function testMethodInjection(): void
    {
        $data = $this->getJson('/ioc/method-injection');

        $this->assertEquals('method_injection', $data['test']);
        $this->assertEquals('file:hello', $data['call_result']);
    }

    public function testRebindingCallback(): void
    {
        $data = $this->getJson('/ioc/rebinding');

        $this->assertEquals('rebinding_callback', $data['test']);
        $this->assertTrue($data['callback_fired']);
        $this->assertStringContainsString('FileLogger', $data['new_instance_type']);
    }

    public function testExtendDecorator(): void
    {
        $data = $this->getJson('/ioc/extend');

        $this->assertEquals('extend_decorator', $data['test']);
        $this->assertTrue($data['has_extra']);
        $this->assertEquals('decorated', $data['extra_value']);
    }

    public function testAppFacade(): void
    {
        $data = $this->getJson('/ioc/app-facade');

        $this->assertEquals('app_facade', $data['test']);
        $this->assertTrue($data['singleton_works']);
        $this->assertEquals('app_facade', $data['source']);
    }

    public function testConditionalRegister(): void
    {
        $data = $this->getJson('/ioc/bind-if');

        $this->assertEquals('conditional_register', $data['test']);
        $this->assertTrue($data['bindIf_no_override']);
        $this->assertTrue($data['singletonIf_no_override']);
    }

    // ===== 辅助方法 =====

    private function getJson(string $path): array
    {
        $url = self::$serverUrl . $path;

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $ctx);

        if ($response === false || $response === '') {
            $this->fail("Failed to get response from {$url}");
        }

        $data = json_decode(trim($response), true);

        if (!is_array($data)) {
            $this->fail("Invalid JSON from {$path}: " . substr($response, 0, 200));
        }

        return $data;
    }
}
