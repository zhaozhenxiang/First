<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;

class HelperTest extends TestCase
{
    // === getUrl ===

    public function testGetUrlDefault(): void
    {
        $_SERVER['REQUEST_URI'] = '/test/path';
        $this->assertEquals('/test/path', getUrl());
    }

    public function testGetUrlFallback(): void
    {
        unset($_SERVER['REQUEST_URI']);
        $this->assertEquals('/', getUrl());
        $_SERVER['REQUEST_URI'] = '/';
    }

    // === getMethod ===

    public function testGetMethodDefault(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->assertEquals('POST', getMethod());
    }

    public function testGetMethodFallback(): void
    {
        unset($_SERVER['REQUEST_METHOD']);
        $this->assertEquals('GET', getMethod());
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    // === basePath ===

    public function testBasePathNoArg(): void
    {
        $path = basePath();
        $this->assertEquals(BASE_PATH, $path);
    }

    public function testBasePathWithArg(): void
    {
        $path = basePath('config/db.php');
        $this->assertStringContainsString('config', $path);
        $this->assertStringContainsString('db.php', $path);
    }

    public function testBasePathStripsLeadingSlash(): void
    {
        $path = basePath('/config/db.php');
        // 不应有双斜杠
        $this->assertFalse(str_contains($path, '//'));
    }

    // === config ===

    public function testConfigSetAndGet(): void
    {
        config(['test_helper:key1' => 'value1']);
        $this->assertEquals('value1', config('test_helper:key1'));
    }

    public function testConfigSetMultiple(): void
    {
        config([
            'test_helper:a' => '1',
            'test_helper:b' => '2',
        ]);
        $this->assertEquals('1', config('test_helper:a'));
        $this->assertEquals('2', config('test_helper:b'));
    }

    public function testConfigReturnsRepositoryWhenNoArgs(): void
    {
        $repo = config();
        $this->assertNotNull($repo);
    }

    // === now ===

    public function testNowReturnsDateTime(): void
    {
        $dt = now();
        $this->assertInstanceOf(\DateTime::class, $dt);
    }

    // === env ===

    public function testEnvReturnsDefaultWhenMissing(): void
    {
        $result = env('NONEXISTENT_VAR_12345', 'fallback');
        $this->assertEquals('fallback', $result);
    }

    public function testEnvReturnsTrueString(): void
    {
        $_ENV['TEST_BOOL_VAR'] = 'true';
        $this->assertTrue(env('TEST_BOOL_VAR'));
        unset($_ENV['TEST_BOOL_VAR']);
    }

    public function testEnvReturnsFalseString(): void
    {
        $_ENV['TEST_BOOL_VAR'] = 'false';
        $this->assertFalse(env('TEST_BOOL_VAR'));
        unset($_ENV['TEST_BOOL_VAR']);
    }

    // === escape ===

    public function testEscapeHtmlSpecialChars(): void
    {
        $this->assertEquals('&lt;script&gt;', escape('<script>'));
    }

    public function testEscapeQuotes(): void
    {
        $result = escape('"hello" & \'world\'');
        $this->assertStringNotContainsString('"', $result);
        $this->assertStringContainsString('&amp;', $result);
    }
}
