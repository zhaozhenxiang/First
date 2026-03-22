<?php

declare(strict_types=1);

namespace Tests;

use Bin\Cache\ArrayStore;
use Bin\Cache\CacheManager;
use Bin\Cache\FileStore;
use Bin\Cache\NullStore;
use Bin\Testing\TestCase;

/**
 * 缓存系统测试
 */
class CacheTest extends TestCase
{
    private string $tempCachePath;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建临时缓存目录
        $this->tempCachePath = sys_get_temp_dir() . '/cache_test_' . uniqid();
        mkdir($this->tempCachePath, 0777, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // 清理临时目录
        $this->removeDirectory($this->tempCachePath);
    }

    public function testArrayStoreSetAndGet(): void
    {
        $cache = new ArrayStore();

        $cache->set('key', 'value');
        $value = $cache->get('key');

        $this->assertEquals('value', $value);
    }

    public function testArrayStoreHas(): void
    {
        $cache = new ArrayStore();

        $this->assertFalse($cache->has('key'));

        $cache->set('key', 'value');

        $this->assertTrue($cache->has('key'));
    }

    public function testArrayStoreDelete(): void
    {
        $cache = new ArrayStore();

        $cache->set('key', 'value');
        $this->assertTrue($cache->has('key'));

        $cache->delete('key');

        $this->assertFalse($cache->has('key'));
    }

    public function testArrayStoreClear(): void
    {
        $cache = new ArrayStore();

        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');

        $cache->clear();

        $this->assertFalse($cache->has('key1'));
        $this->assertFalse($cache->has('key2'));
    }

    public function testArrayStoreRemember(): void
    {
        $cache = new ArrayStore();
        $count = 0;

        $value = $cache->remember('key', null, function () use (&$count) {
            $count++;
            return 'computed';
        });

        $this->assertEquals('computed', $value);
        $this->assertEquals(1, $count);

        // 第二次调用应该返回缓存值
        $value = $cache->remember('key', null, function () use (&$count) {
            $count++;
            return 'computed2';
        });

        $this->assertEquals('computed', $value);
        $this->assertEquals(1, $count);
    }

    public function testArrayStorePull(): void
    {
        $cache = new ArrayStore();

        $cache->set('key', 'value');
        $value = $cache->pull('key');

        $this->assertEquals('value', $value);
        $this->assertFalse($cache->has('key'));
    }

    public function testArrayStoreGetOrSet(): void
    {
        $cache = new ArrayStore();

        $value = $cache->getOrSet('key', 'default');

        $this->assertEquals('default', $value);

        $cache->getOrSet('key', 'updated');

        $this->assertEquals('default', $value);
    }

    public function testFileStoreSetAndGet(): void
    {
        $cache = new FileStore($this->tempCachePath);

        $cache->set('key', 'value');
        $value = $cache->get('key');

        $this->assertEquals('value', $value);
    }

    public function testFileStoreTTL(): void
    {
        $cache = new FileStore($this->tempCachePath);

        $cache->set('key', 'value', 1); // 1 秒过期
        $value = $cache->get('key');

        $this->assertEquals('value', $value);

        sleep(2);

        $value = $cache->get('key', 'default');

        $this->assertEquals('default', $value);
    }

    public function testNullStore(): void
    {
        $cache = new NullStore();

        $cache->set('key', 'value');
        $value = $cache->get('key', 'default');

        $this->assertEquals('default', $value);
        $this->assertFalse($cache->has('key'));
    }

    public function testCacheManager(): void
    {
        CacheManager::setConfig([
            'array' => ['driver' => 'array'],
        ]);

        CacheManager::setDefaultStore('array');

        CacheManager::set('test_key', 'test_value');
        $value = CacheManager::get('test_key');

        $this->assertEquals('test_value', $value);
    }

    public function testCacheManagerHelper(): void
    {
        // 使用辅助函数
        cache('helper_key', 'helper_value');
        $value = cache('helper_key');

        $this->assertEquals('helper_value', $value);
    }

    public function testArrayStoreIncrement(): void
    {
        $cache = new ArrayStore();

        $cache->set('counter', 10);
        $value = $cache->increment('counter', 5);

        $this->assertEquals(15, $value);
    }

    public function testArrayStoreDecrement(): void
    {
        $cache = new ArrayStore();

        $cache->set('counter', 10);
        $value = $cache->decrement('counter', 3);

        $this->assertEquals(7, $value);
    }

    public function testArrayStoreForever(): void
    {
        $cache = new ArrayStore();

        $cache->forever('forever_key', 'forever_value');
        $value = $cache->get('forever_key');

        $this->assertEquals('forever_value', $value);
    }

    public function testArrayStoreGetMultiple(): void
    {
        $cache = new ArrayStore();

        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');

        $values = $cache->getMultiple(['key1', 'key2', 'key3']);

        $this->assertEquals('value1', $values['key1']);
        $this->assertEquals('value2', $values['key2']);
        $this->assertNull($values['key3']);
    }

    public function testArrayStoreSetMultiple(): void
    {
        $cache = new ArrayStore();

        $result = $cache->setMultiple([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);

        $this->assertTrue($result);

        $this->assertEquals('value1', $cache->get('key1'));
        $this->assertEquals('value2', $cache->get('key2'));
    }

    public function testArrayStoreDeleteMultiple(): void
    {
        $cache = new ArrayStore();

        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');

        $result = $cache->deleteMultiple(['key1', 'key2']);

        $this->assertTrue($result);
        $this->assertFalse($cache->has('key1'));
        $this->assertFalse($cache->has('key2'));
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
