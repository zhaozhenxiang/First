<?php

declare(strict_types=1);

namespace Tests;

use Bin\App\App;
use Bin\Testing\TestCase;
use Bin\Response\Response;
use Bin\Response\ResponseFactory;

class ResponseTest extends TestCase
{
    // === 字符串响应 ===

    public function testStringResponse(): void
    {
        $response = new Response('Hello World');
        $this->assertEquals('Hello World', $response->getContent());
        $this->assertEquals('Hello World', (string) $response);
    }

    // === 数组 JSON 响应 ===

    public function testArrayResponseIsJson(): void
    {
        $response = new Response(['key' => 'value']);
        $content = $response->getContent();
        $this->assertStringContainsString('"key"', $content);
        $this->assertStringContainsString('"value"', $content);
    }

    public function testArrayResponseToJson(): void
    {
        $data = ['name' => 'Alice', 'age' => 30];
        $response = new Response($data);
        $decoded = json_decode($response->getContent(), true);
        $this->assertEquals($data, $decoded);
    }

    // === setContent ===

    public function testSetContent(): void
    {
        $response = new Response();
        $result = $response->setContent('new content');
        $this->assertSame($response, $result);
        $this->assertEquals('new content', $response->getContent());
    }

    // === __toString ===

    public function testToString(): void
    {
        $response = new Response('test output');
        $this->assertEquals('test output', (string) $response);
    }

    // === 空响应 ===

    public function testNullResponse(): void
    {
        $response = new Response(null);
        $content = $response->getContent();
        $this->assertTrue($content === '' || $content === null || is_string($content));
    }

    // === 嵌套数组 ===

    public function testNestedArrayResponse(): void
    {
        $data = ['user' => ['name' => 'Bob', 'roles' => ['admin', 'editor']]];
        $response = new Response($data);
        $decoded = json_decode($response->getContent(), true);
        $this->assertEquals($data, $decoded);
    }

    public function testResponseStoresStatusCode(): void
    {
        $response = new Response('created', 201);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('created', $response->getContent());
    }

    public function testResponseStoresHeaders(): void
    {
        $response = new Response('body', 202, [
            'Content-Type' => 'application/json',
            'X-Test' => 'ok',
        ]);

        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertEquals('ok', $response->getHeader('X-Test'));
        $this->assertArrayHasKey('Content-Type', $response->getHeaders());
    }

    public function testResponseAppendsMultipleSetCookieHeaders(): void
    {
        $response = new Response('ok');

        $response->appendHeader('Set-Cookie', 'first=value; Path=/; HttpOnly');
        $response->appendHeader('Set-Cookie', 'second=value; Path=/; HttpOnly');

        $this->assertSame('first=value; Path=/; HttpOnly', $response->getHeader('Set-Cookie'));
        $this->assertSame(
            ['first=value; Path=/; HttpOnly', 'second=value; Path=/; HttpOnly'],
            $response->getHeaderLines('Set-Cookie')
        );
    }

    public function testRedirectHelperReturnsResponse(): void
    {
        $response = redirect('/target');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/target', $response->getHeader('Location'));
    }

    public function testResponseHelperAddsJsonHeaderForArrays(): void
    {
        $response = response(['ok' => true], 201);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
    }

    public function testResponseFactoryCastsIntegerPayloadsToStrings(): void
    {
        $response = (new ResponseFactory())->make(123);

        $this->assertEquals('123', $response->getContent());
    }

    public function testResponseHelperPropagatesFactoryResolutionFailures(): void
    {
        $app = App::getInstance();
        $app->forget(ResponseFactory::class);
        $app->singleton(ResponseFactory::class, function (): ResponseFactory {
            throw new \RuntimeException('factory broken');
        });

        try {
            response('boom');
            $this->fail('Expected response() to propagate factory resolution failure');
        } catch (\RuntimeException $e) {
            $this->assertEquals('factory broken', $e->getMessage());
        } finally {
            $app->forget(ResponseFactory::class);
            $app->singleton(ResponseFactory::class, ResponseFactory::class);
        }
    }
}
