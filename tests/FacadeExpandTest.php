<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Facade\Cache;
use Bin\Facade\Config;
use Bin\Facade\Log;
use Bin\Facade\Session;
use Bin\Facade\Auth;
use Bin\Facade\Gate;
use Bin\Facade\Hash;
use Bin\Facade\DB;
use Bin\Facade\Cookie;
use Bin\Facade\Route;
use Bin\Facade\URL;
use Bin\Facade\View;
use Bin\Facade\Validator;
use Bin\Exception\ValidationException;

/**
 * Facade 扩展测试
 */
class FacadeExpandTest extends TestCase
{
    // ================================================================
    // Facade 类存在性测试
    // ================================================================

    public function testCacheFacadeExists(): void
    {
        $this->assertTrue(class_exists(Cache::class));
    }

    public function testConfigFacadeExists(): void
    {
        $this->assertTrue(class_exists(Config::class));
    }

    public function testLogFacadeExists(): void
    {
        $this->assertTrue(class_exists(Log::class));
    }

    public function testSessionFacadeExists(): void
    {
        $this->assertTrue(class_exists(Session::class));
    }

    public function testAuthFacadeExists(): void
    {
        $this->assertTrue(class_exists(Auth::class));
    }

    public function testGateFacadeExists(): void
    {
        $this->assertTrue(class_exists(Gate::class));
    }

    public function testHashFacadeExists(): void
    {
        $this->assertTrue(class_exists(Hash::class));
    }

    public function testDBFacadeExists(): void
    {
        $this->assertTrue(class_exists(DB::class));
    }

    public function testCookieFacadeExists(): void
    {
        $this->assertTrue(class_exists(Cookie::class));
    }

    public function testRouteFacadeExists(): void
    {
        $this->assertTrue(class_exists(Route::class));
    }

    public function testURLFacadeExists(): void
    {
        $this->assertTrue(class_exists(URL::class));
    }

    public function testViewFacadeExists(): void
    {
        $this->assertTrue(class_exists(View::class));
    }

    public function testValidatorFacadeExists(): void
    {
        $this->assertTrue(class_exists(Validator::class));
    }

    // ================================================================
    // getClassName 测试（通过反射访问 protected 方法）
    // ================================================================

    private function getClassNameViaReflection(object $facade): string
    {
        $ref = new \ReflectionClass($facade);
        $method = $ref->getMethod('getClassName');
        return $method->invoke($facade);
    }

    public function testCacheGetClassName(): void
    {
        $this->assertEquals(\Bin\Cache\CacheManager::class, $this->getClassNameViaReflection(new Cache()));
    }

    public function testConfigGetClassName(): void
    {
        $this->assertEquals(\Bin\Config\ConfigRepository::class, $this->getClassNameViaReflection(new Config()));
    }

    public function testAuthGetClassName(): void
    {
        $this->assertEquals(\Bin\Auth\AuthManager::class, $this->getClassNameViaReflection(new Auth()));
    }

    public function testGateGetClassName(): void
    {
        $this->assertEquals(\Bin\Auth\Gate::class, $this->getClassNameViaReflection(new Gate()));
    }

    public function testHashGetClassName(): void
    {
        $this->assertEquals(\Bin\Auth\HashManager::class, $this->getClassNameViaReflection(new Hash()));
    }

    public function testDBGetClassName(): void
    {
        $this->assertEquals(\Bin\Database\ConnectionManager::class, $this->getClassNameViaReflection(new DB()));
    }

    public function testCookieGetClassName(): void
    {
        $this->assertEquals(\Bin\Cookie\CookieManager::class, $this->getClassNameViaReflection(new Cookie()));
    }

    public function testRouteGetClassName(): void
    {
        $this->assertEquals(\Bin\Route\RouteCollection::class, $this->getClassNameViaReflection(new Route()));
    }

    public function testValidatorGetClassName(): void
    {
        $this->assertEquals(\Bin\Validation\ValidationManager::class, $this->getClassNameViaReflection(new Validator()));
    }

    // ================================================================
    // 代理调用测试（通过 mock 验证 __callStatic 正常工作）
    // ================================================================

    public function testHashFacadeProxiesToHashManager(): void
    {
        // HashManager::make 是真实静态方法，可直接测试
        $hash = Hash::make('password');
        $this->assertTrue(is_string($hash));
        $this->assertTrue(Hash::check('password', $hash));
        $this->assertFalse(Hash::check('wrong', $hash));
    }

    public function testHashFacadeNeedsRehash(): void
    {
        $hash = Hash::make('password');
        $this->assertFalse(Hash::needsRehash($hash));
    }

    public function testURLFacadeGeneratesPath(): void
    {
        $url = URL::to('posts/1');
        $this->assertEquals('/posts/1', $url);
    }

    public function testURLFacadeGeneratesRootPath(): void
    {
        $url = URL::to('/');
        $this->assertEquals('/', $url);
    }

    public function testValidatorFacadeMake(): void
    {
        $v = Validator::make(['name' => 'test'], ['name' => 'required']);
        $this->assertInstanceOf(\Bin\Validation\ValidationManager::class, $v);
    }

    public function testValidatorFacadeMakeAcceptsMessagesAndAttributes(): void
    {
        $validator = Validator::make(
            ['email' => 'not-an-email', 'name' => ''],
            ['email' => 'email', 'name' => 'required'],
            ['email.email' => 'Email must be valid'],
            ['name' => 'Display name']
        );

        $validator->validate();

        $this->assertEquals('Email must be valid', $validator->getError('email')[0] ?? '');
        $this->assertStringContainsString('Display name', $validator->getError('name')[0] ?? '');
    }

    public function testValidatorFacadeValidateReturnsValidatedData(): void
    {
        $validated = Validator::validate(
            ['name' => 'Ada', 'email' => 'ada@example.com'],
            ['name' => 'required|string', 'email' => 'required|email']
        );

        $this->assertEquals([
            'name' => 'Ada',
            'email' => 'ada@example.com',
        ], $validated);
    }

    public function testValidatorFacadeValidateThrowsWithMessagesAndAttributes(): void
    {
        try {
            Validator::validate(
                ['email' => 'not-an-email', 'name' => ''],
                ['email' => 'email', 'name' => 'required'],
                ['email.email' => 'Email must be valid'],
                ['name' => 'Display name']
            );
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();

            $this->assertEquals('Email must be valid', $errors['email'][0] ?? '');
            $this->assertStringContainsString('Display name', $errors['name'][0] ?? '');
        }
    }

    public function testRouteFacadeRegistersGet(): void
    {
        RouteCollectionFacadeTestHelper::clearRoutes();
        Route::get('/facade-test', function () {
            return 'ok';
        });
        $routes = \Bin\Route\RouteCollection::getRoutes();
        $found = false;
        foreach ($routes as $route) {
            if ($route->getPath() === '/facade-test') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Route facade should register GET route');
    }

    public function testViewFacadeCreatesView(): void
    {
        $view = View::make('layout');
        $this->assertInstanceOf(\Bin\View\View::class, $view);
    }

    // ================================================================
    // App 注册测试
    // ================================================================

    public function testAppCoreAliasesIncludesNewServices(): void
    {
        $app = \Bin\App\App::getInstance();
        // 验证新服务已注册
        $this->assertTrue($app->bound('cache'));
        $this->assertTrue($app->bound('config'));
        $this->assertTrue($app->bound('log'));
        $this->assertTrue($app->bound('auth'));
        $this->assertTrue($app->bound('gate'));
        $this->assertTrue($app->bound('db'));
    }

    // ================================================================
    // Facade 清除测试
    // ================================================================

    public function testFacadeClearWorks(): void
    {
        \Bin\Facade\Facade::clear();
        // 清除后不应有缓存实例
        $this->assertTrue(true); // 如果没抛异常就通过
    }
}

/**
 * 路由测试辅助：清除路由
 */
class RouteCollectionFacadeTestHelper
{
    public static function clearRoutes(): void
    {
        \Bin\Route\RouteCollection::clear();
    }
}
