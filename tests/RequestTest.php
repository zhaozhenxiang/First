<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Request\Request;

class RequestTest extends TestCase
{
    private array $originalServer;
    private array $originalRequest;
    private array $originalPost;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalServer = $_SERVER;
        $this->originalRequest = $_REQUEST;
        $this->originalPost = $_POST;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        $_REQUEST = $this->originalRequest;
        $_POST = $this->originalPost;
        parent::tearDown();
    }

    // === 基本数据访问 ===

    public function testInputReturnsValue(): void
    {
        $_REQUEST = ['name' => 'Alice'];
        $request = new Request();
        $this->assertEquals('Alice', $request->input('name'));
    }

    public function testInputReturnsDefaultWhenMissing(): void
    {
        $_REQUEST = [];
        $request = new Request();
        $this->assertEquals('default', $request->input('missing', 'default'));
    }

    public function testInputReturnsNullWhenNoDefault(): void
    {
        $_REQUEST = [];
        $request = new Request();
        $this->assertNull($request->input('missing'));
    }

    public function testAllReturnsAllData(): void
    {
        $_REQUEST = ['a' => 1, 'b' => 2];
        $request = new Request();
        $this->assertEquals(['a' => 1, 'b' => 2], $request->all());
    }

    public function testOnlyReturnsSelectedKeys(): void
    {
        $_REQUEST = ['a' => 1, 'b' => 2, 'c' => 3];
        $request = new Request();
        $this->assertEquals(['a' => 1, 'b' => 2], $request->only(['a', 'b']));
    }

    public function testExceptExcludesKeys(): void
    {
        $_REQUEST = ['a' => 1, 'b' => 2, 'c' => 3];
        $request = new Request();
        $result = $request->except(['c']);
        $this->assertArrayNotHasKey('c', $result);
        $this->assertArrayHasKey('a', $result);
    }

    public function testHasKeyExists(): void
    {
        $_REQUEST = ['name' => 'test'];
        $request = new Request();
        $this->assertTrue($request->has('name'));
    }

    public function hasKeyNotExists(): void
    {
        $_REQUEST = [];
        $request = new Request();
        $this->assertFalse($request->has('name'));
    }

    // === URL 和方法 ===

    public function testGetPath(): void
    {
        $_SERVER['PATH_INFO'] = '/users/profile';
        $_REQUEST = [];
        $request = new Request();
        $this->assertEquals('users/profile', $request->getPath());
    }

    public function testGetPathDefaultRoot(): void
    {
        unset($_SERVER['PATH_INFO']);
        $_REQUEST = [];
        $request = new Request();
        $this->assertEquals('', $request->getPath());
    }

    public function testGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_REQUEST = [];
        $request = new Request();
        $this->assertEquals('POST', $request->getMethod());
    }

    public function testGetMethodOverride(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['_method' => 'PUT'];
        $_REQUEST = [];
        $request = new Request();
        $this->assertEquals('PUT', $request->getMethod());
    }

    // === Headers ===

    public function testHeaderReturnsValue(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_REQUEST = [];
        $request = new Request();
        $this->assertEquals('application/json', $request->header('Accept'));
    }

    public function testHeaderReturnsNullForMissing(): void
    {
        unset($_SERVER['HTTP_ACCEPT']);
        $_REQUEST = [];
        $request = new Request();
        $this->assertNull($request->header('Accept'));
    }

    public function testHeaderReturnsAllWhenNullArg(): void
    {
        $_SERVER = ['HTTP_HOST' => 'localhost'];
        $_REQUEST = [];
        $request = new Request();
        $result = $request->header(null);
        $this->assertTrue(is_array($result));
    }

    // === 快捷检测方法 ===

    public function testIsAjaxTrue(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_REQUEST = [];
        $request = new Request();
        $this->assertTrue($request->isAjax());
    }

    public function testIsAjaxFalse(): void
    {
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        $_REQUEST = [];
        $request = new Request();
        $this->assertFalse($request->isAjax());
    }

    public function testIsJsonTrue(): void
    {
        $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
        $_REQUEST = [];
        $request = new Request();
        $this->assertTrue($request->isJson());
    }

    public function testIsJsonFalse(): void
    {
        $_SERVER['HTTP_CONTENT_TYPE'] = 'text/html';
        $_REQUEST = [];
        $request = new Request();
        $this->assertFalse($request->isJson());
    }

    // === IP ===

    public function testIpFromServer(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_REQUEST = [];
        $request = new Request();
        $this->assertEquals('192.168.1.1', $request->ip());
    }

    // === ArrayAccess ===

    public function testArrayAccessOffsetExists(): void
    {
        $_REQUEST = ['name' => 'Alice'];
        $request = new Request();
        $this->assertTrue(isset($request['name']));
        $this->assertFalse(isset($request['missing']));
    }

    public function testArrayAccessOffsetGet(): void
    {
        $_REQUEST = ['name' => 'Alice'];
        $request = new Request();
        $this->assertEquals('Alice', $request['name']);
    }

    public function testArrayAccessOffsetSet(): void
    {
        $_REQUEST = [];
        $request = new Request();
        $request['key'] = 'value';
        $this->assertEquals('value', $request['key']);
    }

    public function testArrayAccessOffsetUnset(): void
    {
        $_REQUEST = ['name' => 'Alice'];
        $request = new Request();
        unset($request['name']);
        $this->assertNull($request->input('name'));
    }

    // === URL 参数 ===

    public function testUrlParams(): void
    {
        $_REQUEST = [];
        $request = new Request();
        $request->setUrlParam(['id' => 5]);
        $this->assertEquals(['id' => 5], $request->getUrlParam());
    }
}
