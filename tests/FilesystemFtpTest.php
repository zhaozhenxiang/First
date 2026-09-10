<?php

declare(strict_types=1);

namespace Tests;

use Bin\Filesystem\Drivers\FtpDriver;
use Bin\Filesystem\Drivers\LocalDriver;
use Bin\Filesystem\FilesystemAdapter;
use Bin\Filesystem\StorageManager;
use Bin\Testing\TestCase;
use ReflectionMethod;

/**
 * Track E 文件系统测试 — 驱动契约一致性与 FTP 适配器路径映射
 *
 * FTP 网络交互不在单测范围（无 FTP 服务器），此处覆盖：
 * 契约实现、URL/路径映射规则、StorageManager 解析。
 */
class FilesystemFtpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        StorageManager::resetInstance();
    }

    protected function tearDown(): void
    {
        StorageManager::resetInstance();
        parent::tearDown();
    }

    // ================================================================
    // 契约一致性
    // ================================================================

    public function testFtpDriverImplementsContract(): void
    {
        $this->assertTrue(new FtpDriver('ftp.example.com') instanceof FilesystemAdapter);
    }

    public function testLocalDriverImplementsContract(): void
    {
        $this->assertTrue(new LocalDriver(sys_get_temp_dir()) instanceof FilesystemAdapter);
    }

    public function testContractSurfaceCoversCoreOperations(): void
    {
        $methods = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new \ReflectionClass(FilesystemAdapter::class))->getMethods()
        );

        foreach (['exists', 'get', 'put', 'append', 'delete', 'copy', 'move', 'size', 'lastModified', 'url', 'path', 'makeDirectory', 'deleteDirectory', 'files', 'allFiles', 'directories', 'allDirectories'] as $expected) {
            $this->assertContains($expected, $methods);
        }
    }

    // ================================================================
    // FTP 路径映射
    // ================================================================

    public function testRemoteUrlIncludesCredentialsHostPortAndRoot(): void
    {
        $driver = new FtpDriver(
            host: 'ftp.example.com',
            username: 'user name',
            password: 'p@ss:word',
            port: 2121,
            root: '/pub'
        );

        $url = $this->invokeProtected($driver, 'remoteUrl', 'docs/report.txt');

        $this->assertSame(
            'ftp://user%20name:p%40ss%3Aword@ftp.example.com:2121/pub/docs/report.txt',
            $url
        );
    }

    public function testSslOptionSwitchesScheme(): void
    {
        $driver = new FtpDriver('secure.example.com', ssl: true);

        $url = $this->invokeProtected($driver, 'remoteUrl', 'file.txt');

        $this->assertStringStartsWith('ftps://', $url);
    }

    public function testPathMapsRelativePathUnderRoot(): void
    {
        $driver = new FtpDriver('ftp.example.com', root: '/upload');

        $this->assertSame('/upload/a/b.txt', $driver->path('a/b.txt'));
        $this->assertSame('/upload/a/b.txt', $driver->path('/a/b.txt'));
    }

    public function testRootDefaultsToSlashAndNormalizesTrailingSlashes(): void
    {
        $driver = new FtpDriver('ftp.example.com', root: 'nested/dir///');

        $this->assertSame('/nested/dir/file.txt', $driver->path('file.txt'));
    }

    public function testUrlUsesPrefixWhenConfigured(): void
    {
        $driver = new FtpDriver('ftp.example.com', urlPrefix: 'https://cdn.example.com/files');

        $this->assertSame(
            'https://cdn.example.com/files/img/logo.png',
            $driver->url('img/logo.png')
        );
    }

    public function testUrlWithoutPrefixOmitsCredentials(): void
    {
        $driver = new FtpDriver('ftp.example.com', username: 'secret', password: 'secret', port: 21);

        $this->assertSame(
            'ftp://ftp.example.com:21/pub/x.txt',
            $driver->url('/pub/x.txt')
        );
    }

    // ================================================================
    // StorageManager 解析
    // ================================================================

    public function testManagerResolvesFtpDisk(): void
    {
        $manager = new StorageManager();
        $manager->setConfig([
            'ftp-docs' => [
                'driver' => 'ftp',
                'host' => 'ftp.example.com',
                'username' => 'demo',
                'password' => 'secret',
                'port' => 21,
                'root' => '/pub',
            ],
        ]);

        $disk = $manager->disk('ftp-docs');

        $this->assertInstanceOf(\Bin\Filesystem\Filesystem::class, $disk);
    }

    public function testManagerRejectsUnknownDriver(): void
    {
        $manager = new StorageManager();
        $manager->setConfig([
            'broken' => ['driver' => 's3'],
        ]);

        $this->assertThrows(\RuntimeException::class, function () use ($manager): void {
            $manager->disk('broken');
        });
    }

    private function invokeProtected(object $object, string $method, mixed ...$args): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);

        return $reflection->invoke($object, ...$args);
    }
}
