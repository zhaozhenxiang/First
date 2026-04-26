<?php

declare(strict_types=1);

namespace Tests;

use Bin\Session\FileSessionHandler;
use Bin\Session\SessionManager;
use Bin\Testing\TestCase;

/**
 * Session 系统测试
 */
class SessionTest extends TestCase
{
    private string $tempSessionPath;
    private SessionManager $session;

    protected function setUp(): void
    {
        parent::setUp();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // 创建临时 Session 目录
        $this->tempSessionPath = sys_get_temp_dir() . '/session_test_' . uniqid();
        mkdir($this->tempSessionPath, 0777, true);

        // 创建 Session 管理器
        $this->session = new SessionManager();
        $this->session->setHandler(new FileSessionHandler($this->tempSessionPath));
        $this->session->start();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // 销毁 Session
        $this->session->destroy();

        // 清理临时目录
        $this->removeDirectory($this->tempSessionPath);
    }

    public function testSetAndGet(): void
    {
        $this->session->set('key', 'value');
        $value = $this->session->get('key');

        $this->assertEquals('value', $value);
    }

    public function testGetWithDefault(): void
    {
        $value = $this->session->get('nonexistent', 'default');

        $this->assertEquals('default', $value);
    }

    public function testHas(): void
    {
        $this->assertFalse($this->session->has('key'));

        $this->session->set('key', 'value');

        $this->assertTrue($this->session->has('key'));
    }

    public function testRemove(): void
    {
        $this->session->set('key', 'value');
        $this->assertTrue($this->session->has('key'));

        $this->session->remove('key');

        $this->assertFalse($this->session->has('key'));
    }

    public function testPull(): void
    {
        $this->session->set('key', 'value');
        $value = $this->session->pull('key');

        $this->assertEquals('value', $value);
        $this->assertFalse($this->session->has('key'));
    }

    public function testClear(): void
    {
        $this->session->set('key1', 'value1');
        $this->session->set('key2', 'value2');

        $this->session->clear();

        $this->assertFalse($this->session->has('key1'));
        $this->assertFalse($this->session->has('key2'));
    }

    public function testDotNotation(): void
    {
        $this->session->set('user.name', 'John');
        $this->session->set('user.email', 'john@example.com');

        $this->assertEquals('John', $this->session->get('user.name'));
        $this->assertEquals('john@example.com', $this->session->get('user.email'));
    }

    public function testFlash(): void
    {
        $this->session->flash('message', 'Success!');

        $this->assertTrue($this->session->hasFlash('message'));
        $this->assertEquals('Success!', $this->session->getFlash('message'));
    }

    public function testPullFlash(): void
    {
        $this->session->flash('message', 'Success!');

        $message = $this->session->pullFlash('message');

        $this->assertEquals('Success!', $message);
        $this->assertFalse($this->session->hasFlash('message'));
    }

    public function testFlashPersistsForNextRequest(): void
    {
        $this->session->flash('message', 'Success!');

        // 模拟下次请求
        $this->ageFlashData();

        $this->assertTrue($this->session->hasFlash('message'));
        $this->assertEquals('Success!', $this->session->getFlash('message'));
    }

    public function testFlashExpiresAfterSecondRequest(): void
    {
        $this->session->flash('message', 'Success!');

        // 第一次请求后
        $this->ageFlashData();
        $this->assertTrue($this->session->hasFlash('message'));

        // 第二次请求后
        $this->ageFlashData();
        $this->assertFalse($this->session->hasFlash('message'));
    }

    public function testReflash(): void
    {
        $this->session->flash('message', 'Success!');

        // 模拟下次请求
        $this->ageFlashData();

        // 重新 Flash
        $this->session->reflash();

        // 再次老化后应该还存在
        $this->ageFlashData();
        $this->assertTrue($this->session->hasFlash('message'));
    }

    public function testReflashSpecificKey(): void
    {
        $this->session->flash('message1', 'First');
        $this->session->flash('message2', 'Second');

        // 模拟下次请求
        $this->ageFlashData();

        // 只重新 Flash message1
        $this->session->reflash('message1');

        // 再次老化后
        $this->ageFlashData();
        $this->assertTrue($this->session->hasFlash('message1'));
        $this->assertFalse($this->session->hasFlash('message2'));
    }

    public function testClearFlash(): void
    {
        $this->session->flash('message', 'Success!');
        $this->assertTrue($this->session->hasFlash('message'));

        $this->session->clearFlash();

        $this->assertFalse($this->session->hasFlash('message'));
    }

    public function testGetAllFlash(): void
    {
        $this->session->flash('message1', 'First');
        $this->session->flash('message2', 'Second');

        $allFlash = $this->session->getAllFlash();

        $this->assertEquals('First', $allFlash['message1']);
        $this->assertEquals('Second', $allFlash['message2']);
    }

    public function testCsrfToken(): void
    {
        $token = $this->session->putCsrfToken();

        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex chars
        $this->assertTrue($this->session->verifyCsrfToken($token));
    }

    public function testVerifyInvalidCsrfToken(): void
    {
        $this->session->putCsrfToken();

        $this->assertFalse($this->session->verifyCsrfToken('invalid_token'));
    }

    public function testOldInput(): void
    {
        $input = ['name' => 'John', 'email' => 'john@example.com'];

        $this->session->flashInput($input);

        $this->assertEquals('John', $this->session->getOldInput('name'));
        $this->assertEquals('john@example.com', $this->session->getOldInput('email'));
        $this->assertEquals($input, $this->session->getOldInput());
    }

    public function testOldInputWithDefault(): void
    {
        $this->assertEquals('default', $this->session->getOldInput('nonexistent', 'default'));
    }

    public function testSessionId(): void
    {
        $id = $this->session->getId();
        $sidLength = (int) ini_get('session.sid_length');

        $this->assertNotEmpty($id);
        $this->assertGreaterThan(0, $sidLength);
        $this->assertEquals($sidLength, strlen($id)); // PHP session_id 长度取决于当前环境
    }

    public function testSessionName(): void
    {
        $name = $this->session->getName();
        $config = require config_path('session.php');

        $this->assertEquals($config['cookie']['name'], $name);
    }

    public function testDestroyMarksSessionCookieForExpiration(): void
    {
        $this->session->destroy();

        $this->assertTrue($this->session->shouldExpireCookieOnResponse());

        $this->session->clearCookieExpirationFlag();

        $this->assertFalse($this->session->shouldExpireCookieOnResponse());
    }

    public function testIsStarted(): void
    {
        $this->assertTrue($this->session->isStarted());
    }

    public function testLifetime(): void
    {
        $this->session->setLifetime(60); // 1 分钟

        $this->assertEquals(3600, $this->session->getLifetime()); // 转换为秒
    }

    public function testRegenerate(): void
    {
        $oldId = $this->session->getId();
        $newId = $this->session->regenerate();

        $this->assertNotEmpty($newId);
        $this->assertNotEquals($oldId, $newId);
    }

    public function testAll(): void
    {
        $this->session->set('key1', 'value1');
        $this->session->set('key2', 'value2');

        $all = $this->session->all();

        $this->assertArrayHasKey('key1', $all);
        $this->assertEquals('value1', $all['key1']);
    }

    /**
     * 手动触发 Flash 数据老化
     */
    private function ageFlashData(): void
    {
        $this->session->start();

        // 先结束当前请求的 session，确保下一次 start() 会真正进入新请求边界
        $currentSessionId = session_id();
        $this->session->save();

        // 创建新的 Session 实例来模拟下次请求
        $newSession = new SessionManager();
        $newSession->setHandler(new FileSessionHandler($this->tempSessionPath));
        $newSession->setId($currentSessionId);
        $newSession->start();

        $this->session = $newSession;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = scandir($path);
        $files = array_diff($files, ['.', '..']);

        foreach ($files as $file) {
            $filePath = $path . '/' . $file;

            if (is_dir($filePath)) {
                $this->removeDirectory($filePath);
            } else {
                unlink($filePath);
            }
        }

        rmdir($path);
    }
}
