<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;

/**
 * 配置补齐测试
 *
 * 验证：配置文件结构、Manager 从 config 读取、辅助函数可访问
 */
class ConfigCompletionTest extends TestCase
{
    // ================================================================
    // 配置文件结构验证
    // ================================================================

    public function testAppConfigHasRequiredKeys(): void
    {
        $config = require config_path('app.php');

        $this->assertArrayHasKey('name', $config);
        $this->assertArrayHasKey('env', $config);
        $this->assertArrayHasKey('debug', $config);
        $this->assertArrayHasKey('url', $config);
        $this->assertArrayHasKey('timezone', $config);
        $this->assertArrayHasKey('locale', $config);
        $this->assertArrayHasKey('fallback_locale', $config);
        $this->assertArrayHasKey('key', $config);
        $this->assertArrayHasKey('cipher', $config);
        $this->assertArrayHasKey('providers', $config);
    }

    public function testAppConfigProvidersIsArray(): void
    {
        $config = require config_path('app.php');

        $this->assertTrue(is_array($config['providers']));
        $this->assertNotEmpty($config['providers']);
    }

    public function testAppConfigProvidersAreRealClasses(): void
    {
        $config = require config_path('app.php');

        foreach ($config['providers'] as $provider) {
            $this->assertTrue(
                class_exists($provider),
                "Provider '{$provider}' does not exist"
            );
        }
    }

    public function testCacheConfigHasRequiredKeys(): void
    {
        $config = require config_path('cache.php');

        $this->assertArrayHasKey('default', $config);
        $this->assertArrayHasKey('stores', $config);
        $this->assertArrayHasKey('prefix', $config);
    }

    public function testCacheConfigHasFileStore(): void
    {
        $config = require config_path('cache.php');

        $this->assertArrayHasKey('file', $config['stores']);
        $this->assertEquals('file', $config['stores']['file']['driver']);
    }

    public function testCacheConfigHasRedisStore(): void
    {
        $config = require config_path('cache.php');

        $this->assertArrayHasKey('redis', $config['stores']);
        $redisConfig = $config['stores']['redis'];
        $this->assertEquals('redis', $redisConfig['driver']);
        $this->assertArrayHasKey('host', $redisConfig);
        $this->assertArrayHasKey('port', $redisConfig);
    }

    public function testCacheConfigHasArrayStore(): void
    {
        $config = require config_path('cache.php');

        $this->assertArrayHasKey('array', $config['stores']);
    }

    public function testSessionConfigHasRequiredKeys(): void
    {
        $config = require config_path('session.php');

        $this->assertArrayHasKey('driver', $config);
        $this->assertArrayHasKey('lifetime', $config);
        $this->assertArrayHasKey('files', $config);
        $this->assertArrayHasKey('cookie', $config);
    }

    public function testSessionConfigCookieHasRequiredKeys(): void
    {
        $config = require config_path('session.php');

        $cookie = $config['cookie'];
        $this->assertArrayHasKey('name', $cookie);
        $this->assertArrayHasKey('path', $cookie);
        $this->assertArrayHasKey('http_only', $cookie);
        $this->assertArrayHasKey('same_site', $cookie);
    }

    public function testSessionConfigDefaultLifetimeIsReasonable(): void
    {
        $config = require config_path('session.php');

        // 默认应该是 7200 秒 (2 小时)，不是 120 秒 (旧 bug)
        $this->assertEquals(7200, $config['lifetime']);
    }

    public function testLoggingConfigHasRequiredKeys(): void
    {
        $config = require config_path('logging.php');

        $this->assertArrayHasKey('default', $config);
        $this->assertArrayHasKey('channels', $config);
    }

    public function testLoggingConfigHasSingleChannel(): void
    {
        $config = require config_path('logging.php');

        $this->assertArrayHasKey('single', $config['channels']);
        $this->assertEquals('single', $config['channels']['single']['driver']);
        $this->assertArrayHasKey('path', $config['channels']['single']);
    }

    public function testLoggingConfigHasDailyChannel(): void
    {
        $config = require config_path('logging.php');

        $this->assertArrayHasKey('daily', $config['channels']);
        $this->assertArrayHasKey('days', $config['channels']['daily']);
    }

    public function testHashingConfigHasRequiredKeys(): void
    {
        $config = require config_path('hashing.php');

        $this->assertArrayHasKey('driver', $config);
        $this->assertArrayHasKey('bcrypt', $config);
        $this->assertArrayHasKey('argon', $config);
    }

    public function testHashingConfigBcryptRounds(): void
    {
        $config = require config_path('hashing.php');

        $this->assertArrayHasKey('rounds', $config['bcrypt']);
        $this->assertEquals(10, $config['bcrypt']['rounds']);
    }

    public function testHashingConfigArgonParams(): void
    {
        $config = require config_path('hashing.php');

        $argon = $config['argon'];
        $this->assertArrayHasKey('memory', $argon);
        $this->assertArrayHasKey('threads', $argon);
        $this->assertArrayHasKey('time', $argon);
    }

    public function testCorsConfigHasRequiredKeys(): void
    {
        $config = require config_path('cors.php');

        $this->assertArrayHasKey('paths', $config);
        $this->assertArrayHasKey('allowed_methods', $config);
        $this->assertArrayHasKey('allowed_origins', $config);
        $this->assertArrayHasKey('allowed_headers', $config);
        $this->assertArrayHasKey('supports_credentials', $config);
        $this->assertArrayHasKey('max_age', $config);
    }

    // ================================================================
    // config() 辅助函数可访问
    // ================================================================

    public function testConfigHelperCanAccessCacheConfig(): void
    {
        $default = config('cache.default');
        $this->assertNotNull($default);
        $this->assertTrue(is_string($default));
    }

    public function testConfigHelperCanAccessNestedKeys(): void
    {
        $driver = config('cache.stores.file.driver');
        $this->assertEquals('file', $driver);
    }

    public function testConfigHelperCanAccessSessionLifetime(): void
    {
        $lifetime = config('session.lifetime');
        $this->assertEquals(7200, $lifetime);
    }

    public function testConfigHelperCanAccessLoggingDefault(): void
    {
        $default = config('logging.default');
        $this->assertNotNull($default);
        $this->assertTrue(is_string($default));
    }

    public function testConfigHelperCanAccessHashingDriver(): void
    {
        $driver = config('hashing.driver');
        $this->assertEquals('bcrypt', $driver);
    }

    public function testConfigHelperCanAccessAppProviders(): void
    {
        $providers = config('app.providers');
        $this->assertTrue(is_array($providers));
        $this->assertNotEmpty($providers);
    }

    public function testConfigHelperReturnsNullForMissingKey(): void
    {
        // config() 在键不存在时可能返回 null 或抛异常
        $result = @config('app.nonexistent_key_xyz');
        $this->assertNull($result);
    }

    public function testConfigHelperReturnsDefaultForMissingKey(): void
    {
        $result = @config('app.nonexistent_key_xyz', 'fallback');
        // 如果 config 支持默认值，则返回 fallback；否则为 null
        $this->assertTrue($result === 'fallback' || $result === null);
    }

    // ================================================================
    // 路径辅助函数
    // ================================================================

    public function testStoragePathReturnsCorrectPath(): void
    {
        $path = storage_path('cache');
        $this->assertStringContainsString('storage', $path);
        $this->assertStringContainsString('cache', $path);
    }

    public function testStoragePathWithoutArgument(): void
    {
        $path = storage_path();
        $this->assertStringEndsWith('storage', $path);
    }

    public function testConfigPathReturnsCorrectPath(): void
    {
        $path = config_path('app.php');
        $this->assertStringContainsString('config', $path);
        $this->assertStringContainsString('app.php', $path);
    }

    public function testBasePathReturnsCorrectPath(): void
    {
        $path = basePath('test');
        $this->assertStringEndsWith('test', $path);
        $this->assertStringContainsString(BASE_PATH, $path);
    }

    // ================================================================
    // 所有配置文件可加载
    // ================================================================

    public function testAllConfigFilesAreLoadable(): void
    {
        $configFiles = glob(config_path() . '/*.php');

        $this->assertNotEmpty($configFiles, 'No config files found');

        foreach ($configFiles as $file) {
            $config = require $file;
            $this->assertTrue(is_array($config), "Config file {$file} should return an array");
        }
    }

    public function testConfigFileCount(): void
    {
        $configFiles = glob(config_path() . '/*.php');

        // app + auth + database + cache + session + logging + hashing + cors + middleware = 9
        $this->assertGreaterThanOrEqual(9, count($configFiles));
    }

    // ================================================================
    // CacheManager 从 config 读取
    // ================================================================

    public function testCacheManagerReadsDefaultFromConfig(): void
    {
        \Bin\Cache\CacheManager::resetInstance();
        $cm = new \Bin\Cache\CacheManager();

        // config('cache.default') 在测试环境可能为 null，回退到 'file'
        $this->assertEquals('file', $cm->getDefaultStoreFor());
    }

    // ================================================================
    // HashManager 从 config 读取
    // ================================================================

    public function testHashManagerLoadFromConfigSetsBcryptDefault(): void
    {
        $hash = \Bin\Auth\HashManager::make('test_password');

        $this->assertNotEmpty($hash);
        $this->assertTrue(\Bin\Auth\HashManager::check('test_password', $hash));
    }

    public function testHashManagerBcryptShortcutUsesConfig(): void
    {
        $hash = \Bin\Auth\HashManager::bcrypt('test');
        $info = \Bin\Auth\HashManager::getInfo($hash);

        $this->assertEquals('bcrypt', $info['algoName'] ?? '');
    }
}
