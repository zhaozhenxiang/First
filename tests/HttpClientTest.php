<?php

declare(strict_types=1);

namespace Tests;

use Bin\Http\HttpClient;
use Bin\Http\HttpPool;
use Bin\Http\HttpResponse;
use Bin\Http\PendingRequest;
use Bin\Testing\TestCase;

/**
 * HTTP 客户端测试
 *
 * 使用 HttpResponse 单元测试 + PendingRequest 构建器测试。
 * 实际网络请求仅在明确标记的测试中执行。
 */
class HttpClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        HttpClient::resetInstance();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        HttpClient::resetInstance();
    }

    // ─── HttpResponse 单元测试 ───

    public function testResponseStatus(): void
    {
        $response = new HttpResponse(200, 'OK');

        $this->assertEquals(200, $response->status());
        $this->assertTrue($response->successful());
        $this->assertFalse($response->failed());
    }

    public function testResponseStatusCodes(): void
    {
        $ok = new HttpResponse(200);
        $created = new HttpResponse(201);
        $redirect = new HttpResponse(301);
        $notFound = new HttpResponse(404);
        $serverError = new HttpResponse(500);

        $this->assertTrue($ok->successful());
        $this->assertTrue($created->successful());
        $this->assertTrue($redirect->redirect());
        $this->assertTrue($notFound->clientError());
        $this->assertTrue($serverError->serverError());
        $this->assertTrue($notFound->failed());
        $this->assertTrue($serverError->failed());
    }

    public function testResponseBody(): void
    {
        $response = new HttpResponse(200, '{"name":"test"}');

        $this->assertEquals('{"name":"test"}', $response->body());
        $this->assertEquals(['name' => 'test'], $response->json());
        $this->assertEquals('test', $response->json('name'));
        $this->assertNull($response->json('missing'));
        $this->assertEquals('default', $response->json('missing', 'default'));
    }

    public function testResponseJsonNested(): void
    {
        $response = new HttpResponse(200, '{"user":{"profile":{"age":25}}}');

        $this->assertEquals(25, $response->json('user.profile.age'));
        $this->assertEquals(['age' => 25], $response->json('user.profile'));
    }

    public function testResponseJsonInvalid(): void
    {
        $response = new HttpResponse(200, 'not json');

        $this->assertNull($response->json('key'));
        $this->assertEquals('default', $response->json('key', 'default'));
    }

    public function testResponseHeaders(): void
    {
        $response = new HttpResponse(200, '', [
            'Content-Type' => 'application/json',
            'X-Custom' => 'value',
        ]);

        $this->assertEquals('application/json', $response->header('Content-Type'));
        $this->assertEquals('value', $response->header('X-Custom'));
        $this->assertNull($response->header('Missing'));
    }

    public function testResponseHeaderCaseInsensitive(): void
    {
        $response = new HttpResponse(200, '', ['Content-Type' => 'text/html']);

        $this->assertEquals('text/html', $response->header('content-type'));
        $this->assertEquals('text/html', $response->header('CONTENT-TYPE'));
    }

    public function testResponseCookies(): void
    {
        $response = new HttpResponse(200, '', [], ['session' => 'abc123', 'lang' => 'zh']);

        $cookies = $response->cookies();
        $this->assertEquals('abc123', $cookies['session']);
        $this->assertEquals('zh', $cookies['lang']);
    }

    public function testResponseEffectiveUrl(): void
    {
        $response = new HttpResponse(200, '', [], [], 'https://example.com/final');

        $this->assertEquals('https://example.com/final', $response->effectiveUrl());
    }

    public function testResponseThrowOnSuccess(): void
    {
        $response = new HttpResponse(200, 'OK');

        // 不应抛异常
        $result = $response->throw();
        $this->assertSame($response, $result);
    }

    public function testResponseThrowOnFailure(): void
    {
        $response = new HttpResponse(500, 'Server Error');

        $thrown = false;
        try {
            $response->throw();
        } catch (\RuntimeException $e) {
            $thrown = true;
            $this->assertStringContainsString('500', $e->getMessage());
        }
        $this->assertTrue($thrown, 'Expected RuntimeException was not thrown');
    }

    public function testResponseOnErrorCallback(): void
    {
        $response = new HttpResponse(404, 'Not Found');

        $called = false;
        $response->OnError(function (HttpResponse $r) use (&$called) {
            $called = true;
            $this->assertEquals(404, $r->status());
        });

        $this->assertTrue($called);
    }

    public function testResponseOnErrorNotCalledOnSuccess(): void
    {
        $response = new HttpResponse(200, 'OK');

        $called = false;
        $response->onError(function () use (&$called) {
            $called = true;
        });

        $this->assertFalse($called);
    }

    // ─── PendingRequest 构建器测试 ───

    public function testPendingRequestBaseUrl(): void
    {
        $request = new PendingRequest();
        $request->baseUrl('https://api.example.com');

        // 通过反射检查 URL 构建
        $reflection = new \ReflectionMethod($request, 'buildUrl');

        $url = $reflection->invoke($request, '/users', []);
        $this->assertEquals('https://api.example.com/users', $url);
    }

    public function testPendingRequestBuildUrlWithQuery(): void
    {
        $request = new PendingRequest();

        $reflection = new \ReflectionMethod($request, 'buildUrl');

        $url = $reflection->invoke($request, 'https://example.com/search', ['q' => 'test', 'page' => 1]);
        $this->assertStringContainsString('q=test', $url);
        $this->assertStringContainsString('page=1', $url);
    }

    public function testPendingRequestWithToken(): void
    {
        $request = new PendingRequest();
        $request->withToken('my-token');

        $reflection = new \ReflectionProperty($request, 'headers');
        $headers = $reflection->getValue($request);

        $this->assertEquals('Bearer my-token', $headers['Authorization']);
    }

    public function testPendingRequestWithBasicAuth(): void
    {
        $request = new PendingRequest();
        $request->withBasicAuth('user', 'pass');

        $reflection = new \ReflectionProperty($request, 'headers');
        $headers = $reflection->getValue($request);

        $this->assertEquals('Basic ' . base64_encode('user:pass'), $headers['Authorization']);
    }

    public function testPendingRequestWithHeaders(): void
    {
        $request = new PendingRequest();
        $request->withHeaders(['X-Custom' => 'value', 'Accept' => 'text/html']);

        $reflection = new \ReflectionProperty($request, 'headers');
        $headers = $reflection->getValue($request);

        $this->assertEquals('value', $headers['X-Custom']);
        $this->assertEquals('text/html', $headers['Accept']);
    }

    public function testPendingRequestTimeout(): void
    {
        $request = new PendingRequest();
        $request->timeout(60);

        $reflection = new \ReflectionProperty($request, 'timeout');

        $this->assertEquals(60, $reflection->getValue($request));
    }

    public function testPendingRequestWithoutVerifying(): void
    {
        $request = new PendingRequest();
        $request->withoutVerifying();

        $reflection = new \ReflectionProperty($request, 'verifySsl');

        $this->assertFalse($reflection->getValue($request));
    }

    public function testPendingRequestWithoutRedirecting(): void
    {
        $request = new PendingRequest();
        $request->withoutRedirecting();

        $reflection = new \ReflectionProperty($request, 'followRedirects');

        $this->assertFalse($reflection->getValue($request));
    }

    public function testPendingRequestRetry(): void
    {
        $request = new PendingRequest();
        $request->retry(3, 100);

        $reflection = new \ReflectionProperty($request, 'retryTimes');
        $times = $reflection->getValue($request);

        $sleepRef = new \ReflectionProperty($request, 'retrySleepMs');
        $sleep = $sleepRef->getValue($request);

        $this->assertEquals(3, $times);
        $this->assertEquals(100, $sleep);
    }

    public function testPendingRequestBodyFormatJson(): void
    {
        $request = new PendingRequest();
        $request->asJson();

        $reflection = new \ReflectionProperty($request, 'bodyFormat');

        $this->assertEquals('json', $reflection->getValue($request));
    }

    public function testPendingRequestBodyFormatForm(): void
    {
        $request = new PendingRequest();
        $request->asForm();

        $reflection = new \ReflectionProperty($request, 'bodyFormat');

        $this->assertEquals('form', $reflection->getValue($request));
    }

    public function testPendingRequestBuildJsonBody(): void
    {
        $request = new PendingRequest();
        $request->asJson();

        $buildBody = new \ReflectionMethod($request, 'buildBody');

        $headers = [];
        $data = ['name' => 'test'];
        $body = $buildBody->invokeArgs($request, [&$data, &$headers]);

        $this->assertEquals('{"name":"test"}', $body);
        $this->assertEquals('application/json', $headers['Content-Type']);
    }

    public function testPendingRequestBuildFormBody(): void
    {
        $request = new PendingRequest();
        $request->asForm();

        $buildBody = new \ReflectionMethod($request, 'buildBody');

        $headers = [];
        $data = ['name' => 'test'];
        $body = $buildBody->invokeArgs($request, [&$data, &$headers]);

        $this->assertEquals('name=test', $body);
        $this->assertEquals('application/x-www-form-urlencoded', $headers['Content-Type']);
    }

    public function testPendingRequestBuildBodyEmpty(): void
    {
        $request = new PendingRequest();

        $buildBody = new \ReflectionMethod($request, 'buildBody');

        $headers = [];
        $data = null;
        $body = $buildBody->invokeArgs($request, [&$data, &$headers]);

        $this->assertEquals('', $body);
    }

    // ─── HttpClient 管理器测试 ───

    public function testHttpClientSingleton(): void
    {
        $a = HttpClient::getInstance();
        $b = HttpClient::getInstance();

        $this->assertSame($a, $b);
    }

    public function testHttpClientResetInstance(): void
    {
        $a = HttpClient::getInstance();
        HttpClient::resetInstance();
        $b = HttpClient::getInstance();

        $this->assertNotSame($a, $b);
    }

    public function testHttpClientMakeReturnsPendingRequest(): void
    {
        $client = new HttpClient();
        $request = $client->make();

        $this->assertInstanceOf(PendingRequest::class, $request);
    }

    public function testHttpClientMakeWithConfig(): void
    {
        $client = new HttpClient();
        $client->setConfig([
            'base_url' => 'https://api.example.com',
            'timeout' => 60,
            'headers' => ['X-App' => 'test'],
        ]);

        $request = $client->make();

        // 检查 timeout 通过反射
        $reflection = new \ReflectionProperty($request, 'timeout');
        $this->assertEquals(60, $reflection->getValue($request));

        $baseUrlRef = new \ReflectionProperty($request, 'baseUrl');
        $this->assertEquals('https://api.example.com', $baseUrlRef->getValue($request));
    }

    public function testHttpClientProxyCall(): void
    {
        $client = new HttpClient();

        // __call 代理到 PendingRequest
        // 测试无网络：只验证方法存在性，不验证结果
        $result = $client->withToken('test');
        $this->assertInstanceOf(PendingRequest::class, $result);
    }

    // ─── HttpPool 测试（不需要网络的） ───

    public function testHttpPoolCreation(): void
    {
        $pool = new HttpPool();

        $this->assertInstanceOf(HttpPool::class, $pool);
    }

    public function testHttpPoolEmptyExecute(): void
    {
        $pool = new HttpPool();

        // 反射调用 execute
        $reflection = new \ReflectionMethod($pool, 'execute');

        $results = $reflection->invoke($pool);
        $this->assertEquals([], $results);
    }

    // ─── 网络测试（需要 curl + 网络） ───

    public function testRealGetRequest(): void
    {
        if (!extension_loaded('curl')) {
            $this->markTestSkipped('curl extension not loaded');
        }

        $request = new PendingRequest(['timeout' => 10, 'verify_ssl' => false]);
        $response = $request->get('https://httpbin.org/get', ['test' => 'value']);

        // httpbin 可能不可用，跳过而非失败
        if ($response->status() === 0) {
            $this->markTestSkipped('httpbin.org unreachable');
        }

        $this->assertTrue($response->successful());
        $this->assertStringContainsString('test', $response->body());
    }

    public function testRealPostJson(): void
    {
        if (!extension_loaded('curl')) {
            $this->markTestSkipped('curl extension not loaded');
        }

        $request = new PendingRequest(['timeout' => 10, 'verify_ssl' => false]);
        $request->asJson();
        $response = $request->post('https://httpbin.org/post', ['name' => 'test']);

        if ($response->status() === 0) {
            $this->markTestSkipped('httpbin.org unreachable');
        }

        $this->assertTrue($response->successful());
        $data = $response->json();
        $this->assertEquals('test', $data['json']['name'] ?? null);
    }

    public function testRealPostForm(): void
    {
        if (!extension_loaded('curl')) {
            $this->markTestSkipped('curl extension not loaded');
        }

        $request = new PendingRequest(['timeout' => 10, 'verify_ssl' => false]);
        $request->asForm();
        $response = $request->post('https://httpbin.org/post', ['email' => 'user@example.com']);

        if ($response->status() === 0) {
            $this->markTestSkipped('httpbin.org unreachable');
        }

        $this->assertTrue($response->successful());
        $data = $response->json();
        $this->assertEquals('user@example.com', $data['form']['email'] ?? null);
    }

    public function testRealNotFound(): void
    {
        if (!extension_loaded('curl')) {
            $this->markTestSkipped('curl extension not loaded');
        }

        $request = new PendingRequest(['timeout' => 10, 'verify_ssl' => false]);
        $response = $request->get('https://httpbin.org/status/404');

        if ($response->status() === 0) {
            $this->markTestSkipped('httpbin.org unreachable');
        }

        $this->assertEquals(404, $response->status());
        $this->assertTrue($response->clientError());
        $this->assertTrue($response->failed());
    }

    public function testRealWithToken(): void
    {
        if (!extension_loaded('curl')) {
            $this->markTestSkipped('curl extension not loaded');
        }

        $request = new PendingRequest(['timeout' => 10, 'verify_ssl' => false]);
        $request->withToken('secret-token');
        $response = $request->get('https://httpbin.org/get');

        if ($response->status() === 0) {
            $this->markTestSkipped('httpbin.org unreachable');
        }

        $this->assertTrue($response->successful());
        $data = $response->json();
        $this->assertEquals('Bearer secret-token', $data['headers']['Authorization'] ?? '');
    }

    public function testRealTimeout(): void
    {
        if (!extension_loaded('curl')) {
            $this->markTestSkipped('curl extension not loaded');
        }

        $request = new PendingRequest(['timeout' => 1, 'verify_ssl' => false]);
        $response = $request->get('https://httpbin.org/delay/5');

        // 1 秒超时应导致 status 0
        $this->assertEquals(0, $response->status());
    }

    public function testRealPool(): void
    {
        if (!extension_loaded('curl')) {
            $this->markTestSkipped('curl extension not loaded');
        }

        $responses = HttpPool::pool(function ($pool) {
            $pool->as('get')->get('https://httpbin.org/get');
            $pool->as('ip')->get('https://httpbin.org/ip');
        });

        // 如果任一失败（网络问题），跳过
        $getResp = $responses['get'] ?? null;
        if ($getResp === null || $getResp->status() === 0) {
            $this->markTestSkipped('httpbin.org unreachable');
        }

        $this->assertTrue($getResp->successful());
        $this->assertTrue($responses['ip']->successful());
    }
}
