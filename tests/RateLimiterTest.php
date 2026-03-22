<?php

declare(strict_types=1);

namespace Tests;

use Bin\Auth\RateLimiter;
use Bin\Auth\Throttle;
use Bin\Testing\TestCase;

/**
 * Rate Limiter 测试
 */
class RateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::reset();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        RateLimiter::reset();
    }

    public function testRateLimiterAttempt(): void
    {
        $key = 'test_attempt';

        // 前 5 次应该成功
        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue(RateLimiter::attempt($key, 5, 60));
        }

        // 第 6 次应该失败
        $this->assertFalse(RateLimiter::attempt($key, 5, 60));
    }

    public function testRateLimiterRemaining(): void
    {
        $key = 'test_remaining';

        $this->assertEquals(5, RateLimiter::remaining($key, 5, 60));

        RateLimiter::attempt($key, 5, 60);

        $this->assertEquals(4, RateLimiter::remaining($key, 5, 60));
    }

    public function testRateLimiterAvailableIn(): void
    {
        $key = 'test_available_in';

        RateLimiter::attempt($key, 1, 60);

        $availableIn = RateLimiter::availableIn($key, 60);

        $this->assertGreaterThan(0, $availableIn);
        $this->assertLessThanOrEqual(60, $availableIn);
    }

    public function testRateLimiterClear(): void
    {
        $key = 'test_clear';

        // 用尽限制
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::attempt($key, 5, 60);
        }

        $this->assertFalse(RateLimiter::attempt($key, 5, 60));

        // 清除后应该可以再次尝试
        RateLimiter::clear($key);

        $this->assertTrue(RateLimiter::attempt($key, 5, 60));
    }

    public function testRateLimiterAttempts(): void
    {
        $key = 'test_attempts';

        RateLimiter::attempt($key, 10, 60);
        RateLimiter::attempt($key, 10, 60);
        RateLimiter::attempt($key, 10, 60);

        $this->assertEquals(3, RateLimiter::attempts($key, 60));
    }

    public function testRateLimiterKey(): void
    {
        $key = RateLimiter::key('user123', 'api');

        $this->assertEquals('rate_limit:user123:api', $key);
    }

    public function testRateLimiterIsLocked(): void
    {
        $key = 'test_locked';

        // 用尽限制
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::attempt($key, 5, 60);
        }

        // 检查是否被锁定
        $this->assertTrue(RateLimiter::attempts($key, 60) >= 5);
    }

    public function testRateLimiterReset(): void
    {
        RateLimiter::attempt('key1', 10, 60);
        RateLimiter::attempt('key2', 10, 60);

        RateLimiter::reset();

        // 重置后应该重新开始计数
        $this->assertEquals(10, RateLimiter::remaining('key1', 10, 60));
        $this->assertEquals(10, RateLimiter::remaining('key2', 10, 60));
    }

    // Throttle 测试
    public function testThrottleCustom(): void
    {
        $this->assertTrue(Throttle::custom('test', 'custom', 3, 60));
        $this->assertTrue(Throttle::custom('test', 'custom', 3, 60));
        $this->assertTrue(Throttle::custom('test', 'custom', 3, 60));

        $this->assertFalse(Throttle::custom('test', 'custom', 3, 60));
    }

    public function testThrottleApi(): void
    {
        // API 限制：每分钟 60 次
        for ($i = 0; $i < 60; $i++) {
            $this->assertTrue(Throttle::api('test-user'));
        }

        $this->assertFalse(Throttle::api('test-user'));
    }

    public function testThrottleLogin(): void
    {
        // 登录限制：每分钟 5 次
        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue(Throttle::login('test@example.com'));
        }

        $this->assertFalse(Throttle::login('test@example.com'));
    }

    public function testThrottleRegister(): void
    {
        // 注册限制：每小时 3 次
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue(Throttle::register('test@example.com'));
        }

        $this->assertFalse(Throttle::register('test@example.com'));
    }

    public function testThrottlePasswordReset(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue(Throttle::passwordReset('test@example.com'));
        }

        $this->assertFalse(Throttle::passwordReset('test@example.com'));
    }

    public function testThrottleSms(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue(Throttle::sms('+1234567890'));
        }

        $this->assertFalse(Throttle::sms('+1234567890'));
    }

    public function testThrottleEmail(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->assertTrue(Throttle::email('test@example.com'));
        }

        $this->assertFalse(Throttle::email('test@example.com'));
    }

    public function testThrottleUpload(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue(Throttle::upload('user123'));
        }

        $this->assertFalse(Throttle::upload('user123'));
    }

    public function testThrottleRemaining(): void
    {
        Throttle::custom('test', 'remaining', 10, 60);
        Throttle::custom('test', 'remaining', 10, 60);
        Throttle::custom('test', 'remaining', 10, 60);

        $remaining = Throttle::remaining('test', 'remaining', 10, 60);

        $this->assertEquals(7, $remaining);
    }

    public function testThrottleAvailableIn(): void
    {
        Throttle::custom('test', 'available', 1, 60);

        $availableIn = Throttle::availableIn('test', 'available', 60);

        $this->assertGreaterThan(0, $availableIn);
        $this->assertLessThanOrEqual(60, $availableIn);
    }

    public function testThrottleClear(): void
    {
        // 用尽限制（5 次）
        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue(Throttle::login('clear-test@example.com'));
        }

        // 第 6 次应该失败
        $this->assertFalse(Throttle::login('clear-test@example.com'));

        // 清除后应该可以再次尝试
        Throttle::clear('clear-test@example.com', 'login');

        $this->assertTrue(Throttle::login('clear-test@example.com'));
    }

    public function testThrottleIp(): void
    {
        $this->assertTrue(Throttle::byIp('test', 5, 60));
    }

    public function testThrottleByUser(): void
    {
        // 未登录用户使用 IP
        $this->assertTrue(Throttle::byUser('guest', 5, 60));

        // 登录用户使用用户 ID
        // 这个测试需要模拟登录状态
    }
}
