<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Middleware\Middleware;

class MiddlewareTest extends TestCase
{
    // === 基础 Middleware 行为 ===

    public function testRunReturnsTrueWhenHandleReturnsTrue(): void
    {
        $middleware = new class extends Middleware {
            protected function handle(array $param): mixed
            {
                return true;
            }
        };

        $result = $middleware->run([]);
        $this->assertTrue($result);
    }

    public function testRunReturnsFalseWhenHandleReturnsFalse(): void
    {
        $middleware = new class extends Middleware {
            protected function handle(array $param): mixed
            {
                return false;
            }
        };

        $result = $middleware->run([]);
        $this->assertFalse($result);
    }

    public function testRunReturnsStringWhenHandleReturnsString(): void
    {
        $middleware = new class extends Middleware {
            protected function handle(array $param): mixed
            {
                return 'error message';
            }
        };

        $result = $middleware->run([]);
        $this->assertEquals('error message', $result);
    }

    public function testRunPassesParamsToHandle(): void
    {
        $middleware = new class extends Middleware {
            protected function handle(array $param): mixed
            {
                return $param['key'] ?? null;
            }
        };

        $result = $middleware->run(['key' => 'value']);
        $this->assertEquals('value', $result);
    }

    public function testHandleReturnsNullAsBlock(): void
    {
        $middleware = new class extends Middleware {
            protected function handle(array $param): mixed
            {
                return null;
            }
        };

        $result = $middleware->run([]);
        $this->assertNull($result);
    }

    public function testHandleReturnsArrayAsBlock(): void
    {
        $middleware = new class extends Middleware {
            protected function handle(array $param): mixed
            {
                return ['error' => 'forbidden'];
            }
        };

        $result = $middleware->run([]);
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('error', $result);
    }

    // === CsrfMiddleware 韻态验证 ===

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
