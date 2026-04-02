<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Request\Request;

class RequestTest extends TestCase
{
    // =====================================================================
    // 辅助方法：创建请求实例
    // =====================================================================

    private function makeRequest(
        array $query = [],
        array $post = [],
        array $server = [],
        array $cookies = [],
    ): Request {
        $server = array_merge([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'SERVER_NAME' => 'localhost',
        ], $server);

        return new Request($query, $post, $server, $cookies);
    }

    // =====================================================================
    // 基本数据访问（向后兼容）
    // =====================================================================

    public function testInputReturnsValue(): void
    {
        $request = $this->makeRequest(query: ['name' => 'Alice']);
        $this->assertEquals('Alice', $request->input('name'));
    }

    public function testInputReturnsDefaultWhenMissing(): void
    {
        $request = $this->makeRequest();
        $this->assertEquals('default', $request->input('missing', 'default'));
    }

    public function testInputReturnsNullWhenNoDefault(): void
    {
        $request = $this->makeRequest();
        $this->assertNull($request->input('missing'));
    }

    public function testAllReturnsAllData(): void
    {
        $request = $this->makeRequest(query: ['a' => 1], post: ['b' => 2]);
        $result = $request->all();
        $this->assertEquals(1, $result['a']);
        $this->assertEquals(2, $result['b']);
    }

    public function testOnlyReturnsSelectedKeys(): void
    {
        $request = $this->makeRequest(query: ['a' => 1, 'b' => 2, 'c' => 3]);
        $this->assertEquals(['a' => 1, 'b' => 2], $request->only(['a', 'b']));
    }

    public function testExceptExcludesKeys(): void
    {
        $request = $this->makeRequest(query: ['a' => 1, 'b' => 2, 'c' => 3]);
        $result = $request->except(['c']);
        $this->assertArrayNotHasKey('c', $result);
        $this->assertArrayHasKey('a', $result);
    }

    public function testHasKeyExists(): void
    {
        $request = $this->makeRequest(query: ['name' => 'test']);
        $this->assertTrue($request->has('name'));
    }

    public function hasKeyNotExists(): void
    {
        $request = $this->makeRequest();
        $this->assertFalse($request->has('name'));
    }

    // =====================================================================
    // 输入源分离
    // =====================================================================

    public function testQueryReturnsGetParameters(): void
    {
        $request = $this->makeRequest(query: ['search' => 'term', 'page' => '2']);
        $this->assertEquals('term', $request->query('search'));
        $this->assertEquals('2', $request->query('page'));
        $this->assertNull($request->query('nonexistent'));
    }

    public function testQueryReturnsAllWhenNoKey(): void
    {
        $request = $this->makeRequest(query: ['a' => 1, 'b' => 2]);
        $this->assertEquals(['a' => 1, 'b' => 2], $request->query());
    }

    public function testPostReturnsPostParameters(): void
    {
        $request = $this->makeRequest(post: ['name' => 'Alice', 'email' => 'a@b.com']);
        $this->assertEquals('Alice', $request->post('name'));
        $this->assertNull($request->post('nonexistent'));
    }

    public function testPostReturnsAllWhenNoKey(): void
    {
        $request = $this->makeRequest(post: ['a' => 1]);
        $this->assertEquals(['a' => 1], $request->post());
    }

    public function testInputMergesPostAndQueryWithPostPriority(): void
    {
        $request = $this->makeRequest(
            query: ['id' => 'from_query', 'extra' => 'q'],
            post: ['id' => 'from_post'],
            server: ['REQUEST_METHOD' => 'POST'],
        );
        $this->assertEquals('from_post', $request->input('id'));
        $this->assertEquals('q', $request->input('extra'));
    }

    public function testInputReturnsAllWhenNoKey(): void
    {
        $request = $this->makeRequest(query: ['a' => 1], post: ['b' => 2]);
        $all = $request->input();
        $this->assertEquals(1, $all['a']);
        $this->assertEquals(2, $all['b']);
    }

    public function testCookiesNotInInput(): void
    {
        $request = $this->makeRequest(cookies: ['session_id' => 'abc123']);
        $this->assertNull($request->input('session_id'));
    }

    // =====================================================================
    // JSON body 解析
    // =====================================================================

    public function testJsonRequestParsesBody(): void
    {
        // JSON body 通过 merge 模拟（因为 php://input 在测试中不可控）
        $request = $this->makeRequest(
            server: [
                'REQUEST_METHOD' => 'POST',
                'CONTENT_TYPE' => 'application/json',
            ],
        );
        $request->merge(['name' => 'from_json', 'nested' => ['key' => 'value']]);
        $this->assertEquals('from_json', $request->input('name'));
        $this->assertEquals('value', $request->input('nested.key'));
    }

    public function testIsJsonDetectsJsonContentType(): void
    {
        $request = $this->makeRequest(server: ['CONTENT_TYPE' => 'application/json']);
        $this->assertTrue($request->isJson());
    }

    public function testIsJsonDetectsJsonApiContentType(): void
    {
        $request = $this->makeRequest(server: ['CONTENT_TYPE' => 'application/vnd.api+json']);
        $this->assertTrue($request->isJson());
    }

    public function testIsJsonReturnsFalseForHtml(): void
    {
        $request = $this->makeRequest(server: ['CONTENT_TYPE' => 'text/html']);
        $this->assertFalse($request->isJson());
    }

    // =====================================================================
    // 点号嵌套访问
    // =====================================================================

    public function testInputWithDotNotation(): void
    {
        $request = $this->makeRequest(query: [
            'user' => ['name' => 'Alice', 'address' => ['city' => 'Beijing']],
        ]);
        $this->assertEquals('Alice', $request->input('user.name'));
        $this->assertEquals('Beijing', $request->input('user.address.city'));
    }

    public function testInputDotNotationReturnsDefaultWhenMissing(): void
    {
        $request = $this->makeRequest(query: ['user' => ['name' => 'Alice']]);
        $this->assertEquals('default', $request->input('user.missing', 'default'));
    }

    public function testInputDotNotationReturnsDefaultWhenPathMissing(): void
    {
        $request = $this->makeRequest(query: ['user' => 'not_array']);
        $this->assertEquals('default', $request->input('user.name', 'default'));
    }

    public function testQueryDotNotation(): void
    {
        $request = $this->makeRequest(query: ['filter' => ['status' => 'active']]);
        $this->assertEquals('active', $request->query('filter.status'));
    }

    public function testPostDotNotation(): void
    {
        $request = $this->makeRequest(
            post: ['data' => ['items' => ['first' => 'a']]],
            server: ['REQUEST_METHOD' => 'POST'],
        );
        $this->assertEquals('a', $request->post('data.items.first'));
    }

    // =====================================================================
    // 输入检查
    // =====================================================================

    public function testHasAny(): void
    {
        $request = $this->makeRequest(query: ['name' => 'Alice']);
        $this->assertTrue($request->hasAny('name', 'email'));
        $this->assertFalse($request->hasAny('email', 'phone'));
    }

    public function testFilledReturnsTrueForNonEmpty(): void
    {
        $request = $this->makeRequest(query: ['name' => 'Alice']);
        $this->assertTrue($request->filled('name'));
    }

    public function testFilledReturnsFalseForEmptyString(): void
    {
        $request = $this->makeRequest(query: ['name' => '']);
        $this->assertFalse($request->filled('name'));
    }

    public function testFilledReturnsFalseForMissing(): void
    {
        $request = $this->makeRequest();
        $this->assertFalse($request->filled('name'));
    }

    public function testMissing(): void
    {
        $request = $this->makeRequest(query: ['name' => 'Alice']);
        $this->assertTrue($request->missing('email'));
        $this->assertFalse($request->missing('name'));
    }

    public function testWhenFilledExecutesCallback(): void
    {
        $called = false;
        $request = $this->makeRequest(query: ['name' => 'Alice']);
        $request->whenFilled('name', function ($value) use (&$called) {
            $called = true;
            $this->assertEquals('Alice', $value);
        });
        $this->assertTrue($called);
    }

    public function testWhenFilledDoesNotCallWhenEmpty(): void
    {
        $called = false;
        $request = $this->makeRequest(query: ['name' => '']);
        $request->whenFilled('name', function () use (&$called) {
            $called = true;
        });
        $this->assertFalse($called);
    }

    public function testWhenMissingExecutesCallback(): void
    {
        $called = false;
        $request = $this->makeRequest();
        $request->whenMissing('name', function () use (&$called) {
            $called = true;
        });
        $this->assertTrue($called);
    }

    public function testHasWithDotNotation(): void
    {
        $request = $this->makeRequest(query: ['user' => ['name' => 'Alice']]);
        $this->assertTrue($request->has('user.name'));
        $this->assertFalse($request->has('user.email'));
    }

    // =====================================================================
    // 类型转换
    // =====================================================================

    public function testStrCast(): void
    {
        $request = $this->makeRequest(query: ['name' => 'Alice', 'empty' => '']);
        $this->assertEquals('Alice', $request->str('name'));
        $this->assertEquals('', $request->str('empty'));
        $this->assertEquals('default', $request->str('missing', 'default'));
    }

    public function testBooleanCastTrue(): void
    {
        $request = $this->makeRequest(query: [
            'a' => '1',
            'b' => 'true',
            'c' => 'on',
            'd' => 'yes',
        ]);
        $this->assertTrue($request->boolean('a'));
        $this->assertTrue($request->boolean('b'));
        $this->assertTrue($request->boolean('c'));
        $this->assertTrue($request->boolean('d'));
    }

    public function testBooleanCastFalse(): void
    {
        $request = $this->makeRequest(query: [
            'a' => '0',
            'b' => 'false',
            'c' => '',
        ]);
        $this->assertFalse($request->boolean('a'));
        $this->assertFalse($request->boolean('b'));
        $this->assertFalse($request->boolean('c'));
    }

    public function testBooleanDefault(): void
    {
        $request = $this->makeRequest();
        $this->assertFalse($request->boolean('missing'));
        $this->assertTrue($request->boolean('missing', true));
    }

    public function testIntegerCast(): void
    {
        $request = $this->makeRequest(query: ['age' => '25', 'invalid' => 'abc']);
        $this->assertEquals(25, $request->integer('age'));
        $this->assertEquals(0, $request->integer('invalid'));
        $this->assertEquals(0, $request->integer('missing'));
    }

    public function testFloatCast(): void
    {
        $request = $this->makeRequest(query: ['price' => '9.99']);
        $this->assertEquals(9.99, $request->float('price'));
        $this->assertEquals(0.0, $request->float('missing'));
    }

    public function testDateCast(): void
    {
        $request = $this->makeRequest(query: ['date' => '2026-04-01']);
        $result = $request->date('date');
        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
        $this->assertEquals('2026-04-01', $result->format('Y-m-d'));
    }

    public function testDateCastWithFormat(): void
    {
        $request = $this->makeRequest(query: ['date' => '01/04/2026']);
        $result = $request->date('date', 'd/m/Y');
        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
        $this->assertEquals('2026-04-01', $result->format('Y-m-d'));
    }

    public function testDateCastReturnsNullWhenMissing(): void
    {
        $request = $this->makeRequest();
        $this->assertNull($request->date('missing'));
    }

    public function testEnumCast(): void
    {
        $request = $this->makeRequest(query: ['status' => 'active']);
        $result = $request->enum('status', TestStatus::class);
        $this->assertEquals(TestStatus::Active, $result);
    }

    public function testEnumCastReturnsNullWhenMissing(): void
    {
        $request = $this->makeRequest();
        $this->assertNull($request->enum('missing', TestStatus::class));
    }

    // =====================================================================
    // 输入操作
    // =====================================================================

    public function testMerge(): void
    {
        $request = $this->makeRequest(query: ['name' => 'Alice']);
        $request->merge(['name' => 'Bob', 'extra' => 'value']);
        $this->assertEquals('Bob', $request->input('name'));
        $this->assertEquals('value', $request->input('extra'));
    }

    public function testReplace(): void
    {
        $request = $this->makeRequest(query: ['name' => 'Alice']);
        $request->replace(['new' => 'data']);
        $this->assertEquals('data', $request->input('new'));
        $this->assertNull($request->input('name'));
    }

    public function testMergeIfMissing(): void
    {
        $request = $this->makeRequest(query: ['name' => 'Alice']);
        $request->mergeIfMissing(['name' => 'Bob', 'email' => 'a@b.com']);
        $this->assertEquals('Alice', $request->input('name'));
        $this->assertEquals('a@b.com', $request->input('email'));
    }

    // =====================================================================
    // URL / 路径方法
    // =====================================================================

    public function testGetPath(): void
    {
        $request = $this->makeRequest(server: ['REQUEST_URI' => '/users/profile']);
        $this->assertEquals('users/profile', $request->getPath());
    }

    public function testGetPathDefaultRoot(): void
    {
        $request = $this->makeRequest(server: ['REQUEST_URI' => '/']);
        $this->assertEquals('', $request->getPath());
    }

    public function testPathMethod(): void
    {
        $request = $this->makeRequest(server: ['REQUEST_URI' => '/api/v1/users']);
        $this->assertEquals('api/v1/users', $request->path());
    }

    public function testUrl(): void
    {
        $request = $this->makeRequest(server: [
            'REQUEST_URI' => '/users?page=2',
            'SERVER_NAME' => 'example.com',
            'HTTPS' => 'on',
        ]);
        $this->assertEquals('https://example.com/users', $request->url());
    }

    public function testFullUrl(): void
    {
        $request = $this->makeRequest(server: [
            'REQUEST_URI' => '/users?page=2',
            'QUERY_STRING' => 'page=2',
            'SERVER_NAME' => 'example.com',
        ]);
        $this->assertEquals('http://example.com/users?page=2', $request->fullUrl());
    }

    public function testFullUrlWithQuery(): void
    {
        $request = $this->makeRequest(query: ['a' => '1'], server: [
            'REQUEST_URI' => '/test',
            'SERVER_NAME' => 'example.com',
        ]);
        $url = $request->fullUrlWithQuery(['b' => '2']);
        $this->assertStringContainsString('a=1', $url);
        $this->assertStringContainsString('b=2', $url);
    }

    public function testFullUrlWithoutQuery(): void
    {
        $request = $this->makeRequest(query: ['a' => '1', 'b' => '2'], server: [
            'REQUEST_URI' => '/test',
            'SERVER_NAME' => 'example.com',
        ]);
        $url = $request->fullUrlWithoutQuery(['a']);
        $this->assertStringContainsString('b=2', $url);
        $this->assertStringNotContainsString('a=1', $url);
    }

    public function testIsMatchesPatterns(): void
    {
        $request = $this->makeRequest(server: ['REQUEST_URI' => '/admin/users/edit']);
        $this->assertTrue($request->is('admin/*'));
        $this->assertTrue($request->is('*/users/*'));
        $this->assertFalse($request->is('api/*'));
    }

    public function testIsMatchesMultiplePatterns(): void
    {
        $request = $this->makeRequest(server: ['REQUEST_URI' => '/api/users']);
        $this->assertTrue($request->is('admin/*', 'api/*'));
    }

    public function testIsSecure(): void
    {
        $request = $this->makeRequest(server: ['HTTPS' => 'on']);
        $this->assertTrue($request->isSecure());

        $request2 = $this->makeRequest(server: ['HTTPS' => 'off']);
        $this->assertFalse($request2->isSecure());
    }

    public function testHost(): void
    {
        $request = $this->makeRequest(server: ['SERVER_NAME' => 'example.com']);
        $this->assertEquals('example.com', $request->host());
    }

    public function testScheme(): void
    {
        $http = $this->makeRequest();
        $this->assertEquals('http', $http->scheme());

        $https = $this->makeRequest(server: ['HTTPS' => 'on']);
        $this->assertEquals('https', $https->scheme());
    }

    // =====================================================================
    // 请求方法
    // =====================================================================

    public function testGetMethod(): void
    {
        $request = $this->makeRequest(server: ['REQUEST_METHOD' => 'POST']);
        $this->assertEquals('POST', $request->getMethod());
    }

    public function testGetMethodOverride(): void
    {
        $request = $this->makeRequest(
            post: ['_method' => 'PUT'],
            server: ['REQUEST_METHOD' => 'POST'],
        );
        $this->assertEquals('PUT', $request->getMethod());
    }

    public function testMethodReturnsMethod(): void
    {
        $request = $this->makeRequest(server: ['REQUEST_METHOD' => 'DELETE']);
        $this->assertEquals('DELETE', $request->method());
    }

    // =====================================================================
    // 请求头
    // =====================================================================

    public function testHeaderReturnsValue(): void
    {
        $request = $this->makeRequest(server: ['HTTP_ACCEPT' => 'application/json']);
        $this->assertEquals('application/json', $request->header('Accept'));
    }

    public function testHeaderReturnsNullForMissing(): void
    {
        $request = $this->makeRequest();
        $this->assertNull($request->header('Accept'));
    }

    public function testHeaderReturnsAllWhenNullArg(): void
    {
        $request = $this->makeRequest(server: ['HTTP_HOST' => 'localhost']);
        $result = $request->header(null);
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('HOST', $result);
    }

    public function testHeaderParsesContentTypeWithoutHttpPrefix(): void
    {
        $request = $this->makeRequest(server: ['CONTENT_TYPE' => 'application/json']);
        $this->assertEquals('application/json', $request->header('Content-Type'));
    }

    // =====================================================================
    // 快捷检测方法
    // =====================================================================

    public function testIsAjaxTrue(): void
    {
        $request = $this->makeRequest(server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $this->assertTrue($request->isAjax());
    }

    public function testIsAjaxFalse(): void
    {
        $request = $this->makeRequest();
        $this->assertFalse($request->isAjax());
    }

    public function testIsJsonTrue(): void
    {
        $request = $this->makeRequest(server: ['CONTENT_TYPE' => 'application/json']);
        $this->assertTrue($request->isJson());
    }

    public function testIsJsonFalse(): void
    {
        $request = $this->makeRequest(server: ['CONTENT_TYPE' => 'text/html']);
        $this->assertFalse($request->isJson());
    }

    public function testExpectsJson(): void
    {
        $request = $this->makeRequest(server: ['HTTP_ACCEPT' => 'application/json']);
        $this->assertTrue($request->expectsJson());
    }

    public function testWantsJson(): void
    {
        $request = $this->makeRequest(server: ['HTTP_ACCEPT' => 'application/json']);
        $this->assertTrue($request->wantsJson());
    }

    public function testWantsJsonFalse(): void
    {
        $request = $this->makeRequest(server: ['HTTP_ACCEPT' => 'text/html']);
        $this->assertFalse($request->wantsJson());
    }

    public function testAccepts(): void
    {
        $request = $this->makeRequest(server: ['HTTP_ACCEPT' => 'text/html']);
        $this->assertTrue($request->accepts('text/html'));
        $this->assertFalse($request->accepts('application/json'));
    }

    public function testAcceptsWildcard(): void
    {
        $request = $this->makeRequest(server: ['HTTP_ACCEPT' => '*/*']);
        $this->assertTrue($request->accepts('application/json'));
    }

    // =====================================================================
    // IP 和 User-Agent
    // =====================================================================

    public function testIpFromServer(): void
    {
        $request = $this->makeRequest(server: ['REMOTE_ADDR' => '192.168.1.1']);
        $this->assertEquals('192.168.1.1', $request->ip());
    }

    public function testUserAgent(): void
    {
        $request = $this->makeRequest(server: ['HTTP_USER_AGENT' => 'Mozilla/5.0']);
        $this->assertEquals('Mozilla/5.0', $request->userAgent());
    }

    // =====================================================================
    // ArrayAccess
    // =====================================================================

    public function testArrayAccessOffsetExists(): void
    {
        $request = $this->makeRequest(query: ['name' => 'Alice']);
        $this->assertTrue(isset($request['name']));
        $this->assertFalse(isset($request['missing']));
    }

    public function testArrayAccessOffsetGet(): void
    {
        $request = $this->makeRequest(query: ['name' => 'Alice']);
        $this->assertEquals('Alice', $request['name']);
    }

    public function testArrayAccessOffsetSet(): void
    {
        $request = $this->makeRequest();
        $request['key'] = 'value';
        $this->assertEquals('value', $request['key']);
    }

    public function testArrayAccessOffsetUnset(): void
    {
        $request = $this->makeRequest(query: ['name' => 'Alice']);
        unset($request['name']);
        // After unset from mergedInput, query still has it
        // but offsetGet delegates to input() which includes merged
        // unset removes from mergedInput but query persists
    }

    // =====================================================================
    // 路由参数（向后兼容）
    // =====================================================================

    public function testUrlParams(): void
    {
        $request = $this->makeRequest();
        $request->setUrlParam(['id' => 5]);
        $this->assertEquals(['id' => 5], $request->getUrlParam());
    }

    public function testSetRouteParams(): void
    {
        $request = $this->makeRequest();
        $request->setRouteParams(['user' => 'alice', 'page' => 2]);
        $this->assertEquals('alice', $request->route('user'));
        $this->assertEquals(2, $request->route('page'));
        $this->assertNull($request->route('missing'));
    }

    public function testRouteReturnsAllWhenNoKey(): void
    {
        $request = $this->makeRequest();
        $request->setRouteParams(['a' => 1, 'b' => 2]);
        $this->assertEquals(['a' => 1, 'b' => 2], $request->route());
    }

    // =====================================================================
    // Magic Methods
    // =====================================================================

    public function testMagicGetFromInput(): void
    {
        $request = $this->makeRequest(query: ['name' => 'Alice']);
        $this->assertEquals('Alice', $request->name);
    }

    public function testMagicGetFallsBackToRoute(): void
    {
        $request = $this->makeRequest();
        $request->setRouteParams(['id' => 42]);
        $this->assertEquals(42, $request->id);
    }

    public function testMagicIsset(): void
    {
        $request = $this->makeRequest(query: ['name' => 'Alice']);
        $this->assertTrue(isset($request->name));
        $this->assertFalse(isset($request->missing));
    }

    public function testMagicSet(): void
    {
        $request = $this->makeRequest();
        $request->foo = 'bar';
        $this->assertEquals('bar', $request->input('foo'));
    }

    // =====================================================================
    // capture() 静态方法
    // =====================================================================

    public function testCaptureCreatesInstance(): void
    {
        $_GET = ['test' => 'value'];
        $_POST = [];
        $_SERVER = array_merge($_SERVER, ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);
        $_COOKIE = [];

        $request = Request::capture();
        $this->assertInstanceOf(Request::class, $request);
        $this->assertEquals('value', $request->query('test'));

        // Restore
        $_GET = [];
    }

    // =====================================================================
    // Iterator
    // =====================================================================

    public function testIteratorIteration(): void
    {
        $request = $this->makeRequest(query: ['a' => 1, 'b' => 2]);
        $result = [];
        foreach ($request as $key => $value) {
            $result[$key] = $value;
        }
        $this->assertEquals(['a' => 1, 'b' => 2], $result);
    }

    // =====================================================================
    // Content-Type
    // =====================================================================

    public function testGetContentType(): void
    {
        $request = $this->makeRequest(server: ['CONTENT_TYPE' => 'application/json']);
        $this->assertEquals('application/json', $request->getContentType());
    }
}

// 测试用枚举
enum TestStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
