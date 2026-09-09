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

    /** @var resource|null 本测试拉起的内置服务器进程（复用已有服务器时为 null） */
    private static $serverProcess = null;

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
            // exec 让 sh 直接替换为 php 进程，否则 proc_terminate 只能杀到 sh，php -S 会成为孤儿
            "exec php -S 127.0.0.1:9876 -t {$docRoot}",
            $descriptors,
            $pipes
        );

        if (!is_resource($process)) {
            $this->markTestSkipped('Could not start PHP built-in server');
            return;
        }

        // 测试进程结束时关闭自己拉起的服务器，避免遗留孤儿进程
        self::$serverProcess = $process;
        register_shutdown_function(static function (): void {
            if (is_resource(self::$serverProcess)) {
                proc_terminate(self::$serverProcess);
                proc_close(self::$serverProcess);
            }
        });

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

    public function testAliasResolution(): void
    {
        $data = $this->getJson('/ioc/alias');

        $this->assertEquals('alias_resolution', $data['test']);
        $this->assertTrue($data['alias_registered']);
        $this->assertTrue($data['original_not_alias']);
        $this->assertEquals('cache.redis', $data['alias_resolves_to']);
        $this->assertTrue($data['make_via_alias_works']);
    }

    public function testBatchOperations(): void
    {
        $data = $this->getJson('/ioc/batch');

        $this->assertEquals('batch_operations', $data['test']);
        $this->assertTrue($data['bind_array_a']);
        $this->assertTrue($data['bind_array_b']);
        $this->assertTrue($data['singleton_array_c']);
        $this->assertTrue($data['instance_array_d']);
        $this->assertTrue($data['bind_and_make']);
        $this->assertTrue($data['singleton_and_make']);
        $this->assertTrue($data['factory_works']);
    }

    public function testContextualGiveTagged(): void
    {
        $data = $this->getJson('/ioc/contextual-tagged');

        $this->assertEquals('contextual_give_tagged', $data['test']);
        $this->assertTrue($data['received_array']);
        $this->assertEquals(2, $data['count']);
    }

    public function testInspectionMethods(): void
    {
        $data = $this->getJson('/ioc/inspection');

        $this->assertEquals('inspection_methods', $data['test']);
        $this->assertTrue($data['bound_before']);
        $this->assertTrue($data['has_binding']);
        $this->assertTrue($data['not_bound']);
        $this->assertTrue($data['not_resolved_before_make']);
        $this->assertTrue($data['resolved_after_make']);
        $this->assertTrue($data['get_bindings_has_svc']);
        $this->assertTrue($data['has_instance']);
        $this->assertTrue($data['get_instance_of']);
        $this->assertTrue($data['no_instance_for_missing']);
    }

    public function testFlushForget(): void
    {
        $data = $this->getJson('/ioc/flush-forget');

        $this->assertEquals('flush_forget', $data['test']);
        $this->assertTrue($data['resolved_before_forget']);
        $this->assertTrue($data['resolved_after_forget']);
        $this->assertTrue($data['not_bound_after_forget']);
        $this->assertTrue($data['b_still_bound']);
        $this->assertTrue($data['b_not_bound_after_flush']);
    }

    public function testMockService(): void
    {
        $data = $this->getJson('/ioc/mock');

        $this->assertEquals('mock_service', $data['test']);
        $this->assertTrue($data['mock_is_correct_type']);
        $this->assertTrue($data['make_returns_mock']);
    }

    public function testFacadeResolve(): void
    {
        $data = $this->getJson('/ioc/facade-resolve');

        $this->assertEquals('facade_resolve', $data['test']);
        $this->assertTrue($data['facade_resolves']);
        $this->assertTrue($data['unknown_returns_null']);
    }

    public function testCallVariants(): void
    {
        $data = $this->getJson('/ioc/call-variants');

        $this->assertEquals('call_variants', $data['test']);
        $this->assertEquals('file_closure', $data['closure']);
        $this->assertEquals('file:array', $data['array']);
        $this->assertEquals('file:classat', $data['class_at']);
    }

    public function testInstanceBinding(): void
    {
        $data = $this->getJson('/ioc/instance');

        $this->assertEquals('instance_binding', $data['test']);
        $this->assertTrue($data['same_instance']);
        $this->assertTrue($data['value_preserved']);
    }

    public function testMultiExtender(): void
    {
        $data = $this->getJson('/ioc/multi-extender');

        $this->assertEquals('multi_extender', $data['test']);
        $this->assertEquals(2, $data['step']);
        $this->assertEquals('chained', $data['extra']);
        $this->assertTrue($data['has_extenders']);
    }

    public function testResolvingDetail(): void
    {
        $data = $this->getJson('/ioc/resolving-detail');

        $this->assertEquals('resolving_detail', $data['test']);
        $this->assertTrue($data['singleton_resolving_fired_once']);
    }

    public function testDeepInjection(): void
    {
        $data = $this->getJson('/ioc/deep-injection');

        $this->assertEquals('deep_injection', $data['test']);
        $this->assertTrue($data['a_has_b']);
        $this->assertTrue($data['b_has_c']);
        $this->assertTrue($data['c_has_logger']);
        $this->assertEquals('file', $data['logger_name']);
    }

    public function testClosureFactory(): void
    {
        $data = $this->getJson('/ioc/closure-factory');

        $this->assertEquals('closure_factory', $data['test']);
        $this->assertTrue($data['factory_creates_different']);
        $this->assertTrue($data['singleton_factory_same']);
    }

    public function testContainerFlush(): void
    {
        $data = $this->getJson('/ioc/container-flush');

        $this->assertEquals('container_flush', $data['test']);
        $this->assertTrue($data['not_bound_a']);
        $this->assertTrue($data['not_bound_b']);
        $this->assertTrue($data['no_alias']);
        $this->assertTrue($data['empty_bindings']);
    }

    public function testDependencyOverride(): void
    {
        $data = $this->getJson('/ioc/dependency-override');

        $this->assertEquals('dependency_override', $data['test']);
        $this->assertTrue($data['first_was_file']);
        $this->assertTrue($data['second_is_cloud']);
    }

    public function testBuildStackInspection(): void
    {
        $data = $this->getJson('/ioc/build-stack');

        $this->assertEquals('build_stack', $data['test']);
        $this->assertTrue($data['empty_initial']);
        $this->assertTrue($data['not_in_stack']);
        $this->assertTrue($data['after_resolving_fired']);
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
