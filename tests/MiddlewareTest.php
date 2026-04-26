<?php

declare(strict_types=1);

namespace Tests;

use Bin\App\App;
use Bin\Middleware\Middleware;
use Bin\Middleware\SessionMiddleware;
use Bin\Request\Request;
use Bin\Response\Response;
use Bin\Session\FileSessionHandler;
use Bin\Session\SessionManager;
use Bin\Testing\TestCase;

class MiddlewareTest extends TestCase
{
    // === Middleware 基类行为 ===

    public function testHandlePassesThroughByDefault(): void
    {
        $middleware = new class extends Middleware {
            // 使用默认 handle()，直接 $next($request)
        };

        $result = $middleware->handle('request', fn($r) => 'response');
        $this->assertEquals('response', $result);
    }

    public function testHandleCanShortCircuit(): void
    {
        $middleware = new class extends Middleware {
            public function handle(mixed $request, \Closure $next): mixed
            {
                return 'blocked';
            }
        };

        $result = $middleware->handle('request', fn($r) => 'response');
        $this->assertEquals('blocked', $result);
    }

    public function testSetOptionsReturnsSelf(): void
    {
        $middleware = new class extends Middleware {};
        $result = $middleware->setOptions(['key' => 'value']);
        $this->assertSame($middleware, $result);
    }

    public function testTerminateDoesNothingByDefault(): void
    {
        $middleware = new class extends Middleware {};
        // 不应抛异常
        $middleware->terminate('request', 'response');
        $this->assertTrue(true);
    }

    public function testHandleCanWrapResponse(): void
    {
        $middleware = new class extends Middleware {
            public function handle(mixed $request, \Closure $next): mixed
            {
                $response = $next($request);
                return strtoupper((string) $response);
            }
        };

        $result = $middleware->handle('request', fn($r) => 'hello');
        $this->assertEquals('HELLO', $result);
    }

    public function testSessionMiddlewareRestoresSessionFromRequestCookieAndQueuesResponseCookie(): void
    {
        $tempPath = sys_get_temp_dir() . '/session_middleware_' . uniqid();
        mkdir($tempPath, 0777, true);
        $app = App::getInstance();
        $previous = $app->make(SessionManager::class);

        try {
            $seed = new SessionManager();
            $seed->setHandler(new FileSessionHandler($tempPath));
            $seed->start();
            $seed->set('user_id', 7);
            $seedId = $seed->getId();
            $seed->save();

            $bound = new SessionManager();
            $bound->setHandler(new FileSessionHandler($tempPath));
            $app->instance(SessionManager::class, $bound);

            $request = new Request(
                query: [],
                post: [],
                server: ['REQUEST_METHOD' => 'GET'],
                cookies: [$bound->getName() => $seedId]
            );

            $middleware = new SessionMiddleware();

            $response = $middleware->handle($request, function () use ($bound) {
                return ['user_id' => $bound->get('user_id')];
            });

            $this->assertInstanceOf(Response::class, $response);
            $this->assertSame(7, json_decode($response->getContent(), true)['user_id']);
            $this->assertNotEmpty($response->getHeaderLines('Set-Cookie'));
        } finally {
            $app->instance(SessionManager::class, $previous);
            array_map('unlink', glob($tempPath . '/sess_*') ?: []);
            @rmdir($tempPath);
        }
    }

    // === CsrfMiddleware 静态验证 ===

    public function testCsrfTokenGeneration(): void
    {
        $token = \Bin\Middleware\CsrfMiddleware::generateToken();
        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token));
    }

    public function testCsrfTokenValidationSuccess(): void
    {
        $token = \Bin\Middleware\CsrfMiddleware::generateToken();
        $this->assertTrue(\Bin\Middleware\CsrfMiddleware::validateToken($token));
    }

    public function testCsrfTokenValidationFailsWithWrongToken(): void
    {
        \Bin\Middleware\CsrfMiddleware::generateToken();
        $this->assertFalse(\Bin\Middleware\CsrfMiddleware::validateToken('wrong_token'));
    }

    public function testCsrfTokenValidationFailsWithNull(): void
    {
        \Bin\Middleware\CsrfMiddleware::generateToken();
        $this->assertFalse(\Bin\Middleware\CsrfMiddleware::validateToken(null));
    }

    public function testCsrfFieldContainsToken(): void
    {
        $field = \Bin\Middleware\CsrfMiddleware::field();
        $this->assertStringContainsString('_csrf_token', $field);
        $this->assertStringContainsString('hidden', $field);
    }

    public function testCsrfTokenGenerationStoresTokenInSessionManager(): void
    {
        $app = App::getInstance();
        $previous = $app->make(SessionManager::class);

        try {
            $session = new SessionManager();
            $app->instance(SessionManager::class, $session);

            $token = \Bin\Middleware\CsrfMiddleware::generateToken();

            $this->assertSame($token, $session->getCsrfToken());
            $this->assertSame($token, \Bin\Middleware\CsrfMiddleware::generateToken());
        } finally {
            $app->instance(SessionManager::class, $previous);
        }
    }

    public function testCsrfMiddlewareThrowsHttpExceptionOnInvalidToken(): void
    {
        $app = App::getInstance();
        $previous = $app->make(SessionManager::class);

        try {
            $session = new SessionManager();
            $app->instance(SessionManager::class, $session);
            $session->start();
            $session->putCsrfToken();

            $middleware = new \Bin\Middleware\CsrfMiddleware();
            $request = new Request(
                query: [],
                post: ['_csrf_token' => 'wrong-token'],
                server: ['REQUEST_METHOD' => 'POST']
            );

            $this->assertThrows(\Bin\Exception\HttpException::class, function () use ($middleware, $request) {
                $middleware->handle($request, fn($req) => 'ok');
            });
        } finally {
            $app->instance(SessionManager::class, $previous);
        }
    }

    // === RateLimiter 核心逻辑 ===

    public function testRateLimiterAllowsFirstAttempts(): void
    {
        \Bin\Auth\RateLimiter::reset();

        $key = 'test_key_' . uniqid();
        $result = \Bin\Auth\RateLimiter::attempt($key, 5, 60);
        $this->assertTrue($result);
    }

    public function testRateLimiterBlocksAfterMaxAttempts(): void
    {
        \Bin\Auth\RateLimiter::reset();

        $key = 'test_key_' . uniqid();
        for ($i = 0; $i < 3; $i++) {
            \Bin\Auth\RateLimiter::attempt($key, 3, 60);
        }
        $result = \Bin\Auth\RateLimiter::attempt($key, 3, 60);
        $this->assertFalse($result);
    }

    public function testRateLimiterRemaining(): void
    {
        \Bin\Auth\RateLimiter::reset();

        $key = 'test_key_' . uniqid();
        \Bin\Auth\RateLimiter::attempt($key, 5, 60);
        $remaining = \Bin\Auth\RateLimiter::remaining($key, 5, 60);
        $this->assertEquals(4, $remaining);
    }

    public function testRateLimiterClear(): void
    {
        \Bin\Auth\RateLimiter::reset();

        $key = 'test_key_' . uniqid();
        \Bin\Auth\RateLimiter::attempt($key, 1, 60);
        \Bin\Auth\RateLimiter::clear($key);
        $result = \Bin\Auth\RateLimiter::attempt($key, 1, 60);
        $this->assertTrue($result);
    }

    public function testRateLimiterKeyGeneration(): void
    {
        $key = \Bin\Auth\RateLimiter::key('user_123', 'login');
        $this->assertStringContainsString('user_123', $key);
        $this->assertStringContainsString('login', $key);
    }
}
