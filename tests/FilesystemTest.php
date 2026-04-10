<?php

declare(strict_types=1);

namespace Tests;

use Bin\Filesystem\Drivers\LocalDriver;
use Bin\Filesystem\Filesystem;
use Bin\Filesystem\StorageManager;
use Bin\Facade\Storage;
use Bin\Testing\TestCase;
use RuntimeException;

/**
 * 文件系统测试
 */
class FilesystemTest extends TestCase
{
    private string $tempPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempPath = sys_get_temp_dir() . '/fs_test_' . uniqid();
        mkdir($this->tempPath, 0777, true);

        StorageManager::resetInstance();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->removeDirectory($this->tempPath);
        StorageManager::resetInstance();
    }

    // ─── LocalDriver 测试 ───

    public function testPutAndGet(): void
    {
        $driver = new LocalDriver($this->tempPath);

        $this->assertTrue($driver->put('test.txt', 'hello world'));
        $this->assertEquals('hello world', $driver->get('test.txt'));
    }

    public function testGetReturnsNullForMissingFile(): void
    {
        $driver = new LocalDriver($this->tempPath);

        $this->assertNull($driver->get('missing.txt'));
    }

    public function testExists(): void
    {
        $driver = new LocalDriver($this->tempPath);

        $this->assertFalse($driver->exists('file.txt'));

        $driver->put('file.txt', 'content');

        $this->assertTrue($driver->exists('file.txt'));
    }

    public function testDelete(): void
    {
        $driver = new LocalDriver($this->tempPath);

        $driver->put('del.txt', 'content');
        $this->assertTrue($driver->exists('del.txt'));

        $this->assertTrue($driver->delete('del.txt'));
        $this->assertFalse($driver->exists('del.txt'));
    }

    public function testDeleteNonExistentReturnsTrue(): void
    {
        $driver = new LocalDriver($this->tempPath);

        $this->assertTrue($driver->delete('nope.txt'));
    }

    public function testCopy(): void
    {
        $driver = new LocalDriver($this->tempPath);

        $driver->put('src.txt', 'copy me');

        $this->assertTrue($driver->copy('src.txt', 'dst.txt'));
        $this->assertEquals('copy me', $driver->get('dst.txt'));
    }

    public function testCopyMissingFileReturnsFalse(): void
    {
        $driver = new LocalDriver($this->tempPath);

        $this->assertFalse($driver->copy('missing.txt', 'dst.txt'));
    }

    public function testMove(): void
    {
        $driver = new LocalDriver($this->tempPath);

        $driver->put('old.txt', 'move me');

        $this->assertTrue($driver->move('old.txt', 'new.txt'));
        $this->assertNull($driver->get('old.txt'));
        $this->assertEquals('move me', $driver->get('new.txt'));
    }

    public function testSize(): void
    {
        $driver = new LocalDriver($this->tempPath);

        $driver->put('size.txt', '12345');

        $this->assertEquals(5, $driver->size('size.txt'));
    }

    public function testSizeMissingFileReturnsZero(): void
    {
        $driver = new LocalDriver($this->tempPath);

        $this->assertEquals(0, $driver->size('missing.txt'));
    }

    public function testLastModified(): void
    {
        $driver = new LocalDriver($this->tempPath);

        $driver->put('time.txt', 'content');
        $before = time() - 1;
        $mtime = $driver->lastModified('time.txt');
        $after = time() + 1;

        $this->assertTrue($mtime >= $before && $mtime <= $after);
    }

    public function testAppend(): void
    {
        $driver = new LocalDriver($this->tempPath);

        $driver->append('log.txt', "line1\n");
        $driver->append('log.txt', "line2\n");

        $this->assertEquals("line1\nline2\n", $driver->get('log.txt'));
    }

    public function testMakeDirectory(): void
    {
        $driver = new LocalDriver($this->tempPath);

        $this->assertTrue($driver->makeDirectory('newdir'));
        $this->assertTrue(is_dir($this->tempPath . '/newdir'));
    }

    public function testMakeDirectoryAlreadyExists(): void
    {
        $driver = new LocalDriver($this->tempPath);

        mkdir($this->tempPath . '/existing');

        $this->assertTrue($driver->makeDirectory('existing'));
    }

    public function testDeleteDirectory(): void
    {
        $driver = new LocalDriver($this->tempPath);

        $driver->put('subdir/a.txt', 'a');
        $driver->put('subdir/b.txt', 'b');

        $this->assertTrue($driver->deleteDirectory('subdir'));
        $this->assertFalse($driver->exists('subdir/a.txt'));
    }

    public function testFiles(): void
    {
        $driver = new LocalDriver($this->tempPath);

        $driver->put('a.txt', 'a');
        $driver->put('b.txt', 'b');
        $driver->makeDirectory('sub');

        $files = $driver->files('');

        $this->assertCount(2, $files);
        $this->assertContains('a.txt', $files);
        $this->assertContains('b.txt', $files);
    }

    public function testAllFiles(): void
    {
        $driver = new LocalDriver($this->tempPath);

        $driver->put('root.txt', 'r');
        $driver->put('sub/deep.txt', 'd');

        $all = $driver->allFiles('');

        $this->assertCount(2, $all);
        $this->assertContains('root.txt', $all);
        $this->assertContains('sub/deep.txt', $all);
    }

    public function testDirectories(): void
    {
        $driver = new LocalDriver($this->tempPath);

        $driver->makeDirectory('dir1');
        $driver->makeDirectory('dir2');
        $driver->put('file.txt', 'f');

        $dirs = $driver->directories('');

        $this->assertCount(2, $dirs);
        $this->assertContains('dir1', $dirs);
        $this->assertContains('dir2', $dirs);
    }

    public function testAllDirectories(): void
    {
        $driver = new LocalDriver($this->tempPath);

        $driver->makeDirectory('a/b/c');

        $all = $driver->allDirectories('');

        $this->assertCount(3, $all);
        $this->assertContains('a', $all);
        $this->assertContains('a/b', $all);
        $this->assertContains('a/b/c', $all);
    }

    public function testUrlWithPrefix(): void
    {
        $driver = new LocalDriver($this->tempPath, 'http://example.com/storage');

        $this->assertEquals('http://example.com/storage/file.txt', $driver->url('file.txt'));
    }

    public function testUrlWithoutPrefix(): void
    {
        $driver = new LocalDriver($this->tempPath);

        $this->assertEquals('/file.txt', $driver->url('file.txt'));
    }

    public function testPath(): void
    {
        $driver = new LocalDriver($this->tempPath);

        $expected = $this->tempPath . '/test.txt';
        $this->assertEquals($expected, $driver->path('test.txt'));
    }

    public function testPutCreatesSubdirectories(): void
    {
        $driver = new LocalDriver($this->tempPath);

        $this->assertTrue($driver->put('deep/nested/dir/file.txt', 'deep content'));
        $this->assertEquals('deep content', $driver->get('deep/nested/dir/file.txt'));
    }

    // ─── Filesystem 包装层测试 ───

    public function testFilesystemMissing(): void
    {
        $driver = new LocalDriver($this->tempPath);
        $fs = new Filesystem($driver);

        $this->assertTrue($fs->missing('nope.txt'));
        $this->assertFalse($fs->missing('nope.txt') === $fs->exists('nope.txt'));
    }

    public function testFilesystemGetOrElse(): void
    {
        $driver = new LocalDriver($this->tempPath);
        $fs = new Filesystem($driver);

        $this->assertEquals('default', $fs->getOrElse('missing.txt', 'default'));

        $fs->put('exists.txt', 'found');
        $this->assertEquals('found', $fs->getOrElse('exists.txt', 'default'));
    }

    public function testFilesystemPrepend(): void
    {
        $driver = new LocalDriver($this->tempPath);
        $fs = new Filesystem($driver);

        $fs->put('file.txt', 'world');
        $fs->prepend('file.txt', 'hello ');

        $this->assertEquals('hello world', $fs->get('file.txt'));
    }

    public function testFilesystemPrependNewFile(): void
    {
        $driver = new LocalDriver($this->tempPath);
        $fs = new Filesystem($driver);

        $fs->prepend('new.txt', 'first');

        $this->assertEquals('first', $fs->get('new.txt'));
    }

    public function testFilesystemDeleteArray(): void
    {
        $driver = new LocalDriver($this->tempPath);
        $fs = new Filesystem($driver);

        $fs->put('a.txt', 'a');
        $fs->put('b.txt', 'b');

        $this->assertTrue($fs->delete(['a.txt', 'b.txt']));
        $this->assertTrue($fs->missing('a.txt'));
        $this->assertTrue($fs->missing('b.txt'));
    }

    public function testFilesystemGetAdapter(): void
    {
        $driver = new LocalDriver($this->tempPath);
        $fs = new Filesystem($driver);

        $this->assertSame($driver, $fs->getAdapter());
    }

    // ─── StorageManager 测试 ───

    public function testStorageManagerDefaultDisk(): void
    {
        $manager = new StorageManager();
        $manager->setConfig([
            'local' => ['driver' => 'local', 'root' => $this->tempPath],
        ]);

        $disk = $manager->disk();
        $disk->put('mgr.txt', 'from manager');

        $this->assertEquals('from manager', $disk->get('mgr.txt'));
    }

    public function testStorageManagerNamedDisk(): void
    {
        $manager = new StorageManager();
        $manager->setConfig([
            'local' => ['driver' => 'local', 'root' => $this->tempPath . '/local'],
            'backup' => ['driver' => 'local', 'root' => $this->tempPath . '/backup'],
        ]);

        $local = $manager->disk('local');
        $backup = $manager->disk('backup');

        $local->put('file.txt', 'local');
        $backup->put('file.txt', 'backup');

        $this->assertEquals('local', $local->get('file.txt'));
        $this->assertEquals('backup', $backup->get('file.txt'));
    }

    public function testStorageManagerDiskIsCached(): void
    {
        $manager = new StorageManager();
        $manager->setConfig([
            'local' => ['driver' => 'local', 'root' => $this->tempPath],
        ]);

        $first = $manager->disk('local');
        $second = $manager->disk('local');

        $this->assertSame($first, $second);
    }

    public function testStorageManagerUnconfiguredDiskThrows(): void
    {
        $manager = new StorageManager();
        $manager->setConfig([]);

        $thrown = false;
        try {
            $manager->disk('unknown');
        } catch (RuntimeException $e) {
            $thrown = true;
            $this->assertStringContainsString('Disk [unknown] is not configured', $e->getMessage());
        }
        $this->assertTrue($thrown, 'Expected RuntimeException was not thrown');
    }

    public function testStorageManagerUnsupportedDriverThrows(): void
    {
        $manager = new StorageManager();
        $manager->setConfig([
            's3' => ['driver' => 's3', 'bucket' => 'test'],
        ]);

        $thrown = false;
        try {
            $manager->disk('s3');
        } catch (RuntimeException $e) {
            $thrown = true;
            $this->assertStringContainsString('Unsupported filesystem driver: s3', $e->getMessage());
        }
        $this->assertTrue($thrown, 'Expected RuntimeException was not thrown');
    }

    public function testStorageManagerProxyMethods(): void
    {
        $manager = new StorageManager();
        $manager->setConfig([
            'local' => ['driver' => 'local', 'root' => $this->tempPath],
        ]);

        // 通过代理方法操作默认磁盘
        $manager->put('proxy.txt', 'proxy content');
        $this->assertTrue($manager->exists('proxy.txt'));
        $this->assertEquals('proxy content', $manager->get('proxy.txt'));
        $this->assertEquals(strlen('proxy content'), $manager->size('proxy.txt'));
        $this->assertTrue($manager->delete('proxy.txt'));
        $this->assertFalse($manager->exists('proxy.txt'));
    }

    public function testStorageManagerFlush(): void
    {
        $manager = new StorageManager();
        $manager->setConfig([
            'local' => ['driver' => 'local', 'root' => $this->tempPath],
        ]);

        $first = $manager->disk('local');
        $manager->flush();
        $second = $manager->disk('local');

        // flush 后应创建新实例
        $this->assertNotSame($first, $second);
    }

    public function testStorageManagerSingleton(): void
    {
        $manager = StorageManager::getInstance();
        $same = StorageManager::getInstance();

        $this->assertSame($manager, $same);
    }

    public function testStorageManagerSetDefaultDisk(): void
    {
        $manager = new StorageManager();
        $manager->setConfig([
            'primary' => ['driver' => 'local', 'root' => $this->tempPath . '/pri'],
            'secondary' => ['driver' => 'local', 'root' => $this->tempPath . '/sec'],
        ]);
        $manager->setDefaultDisk('secondary');

        $manager->put('disk.txt', 'secondary');

        $this->assertEquals('secondary', $manager->disk('secondary')->get('disk.txt'));
        $this->assertNull($manager->disk('primary')->get('disk.txt'));
    }

    // ─── Storage Facade 测试 ───

    public function testStorageFacadePutAndGet(): void
    {
        $manager = StorageManager::getInstance();
        $manager->setConfig([
            'local' => ['driver' => 'local', 'root' => $this->tempPath],
        ]);

        Storage::put('facade.txt', 'facade content');
        $this->assertEquals('facade content', Storage::get('facade.txt'));
    }

    public function testStorageFacadeExistsAndDelete(): void
    {
        $manager = StorageManager::getInstance();
        $manager->setConfig([
            'local' => ['driver' => 'local', 'root' => $this->tempPath],
        ]);

        Storage::put('to_delete.txt', 'bye');

        $this->assertTrue(Storage::exists('to_delete.txt'));
        $this->assertTrue(Storage::delete('to_delete.txt'));
        $this->assertFalse(Storage::exists('to_delete.txt'));
    }

    public function testStorageFacadeCopyAndMove(): void
    {
        $manager = StorageManager::getInstance();
        $manager->setConfig([
            'local' => ['driver' => 'local', 'root' => $this->tempPath],
        ]);

        Storage::put('orig.txt', 'data');

        Storage::copy('orig.txt', 'copy.txt');
        $this->assertEquals('data', Storage::get('copy.txt'));

        Storage::move('orig.txt', 'moved.txt');
        $this->assertNull(Storage::get('orig.txt'));
        $this->assertEquals('data', Storage::get('moved.txt'));
    }

    // ─── 辅助方法 ───

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
