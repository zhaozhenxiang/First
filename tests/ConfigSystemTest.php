<?php

declare(strict_types=1);

namespace Tests;

use Bin\Config\EnvLoader;
use Bin\Config\ConfigRepository;
use Bin\Console\Kernel;
use Bin\Testing\TestCase;

/**
 * Config 系统综合测试
 *
 * 覆盖：EnvLoader / ConfigRepository / config() helper / 编译缓存 / fallback / config 文件结构
 */
class ConfigSystemTest extends TestCase
{
    private string $compiledPath;
    private string $tmpEnvFile;
    private string $tmpConfigPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->compiledPath = basePath('/storage/config');
        $this->tmpEnvFile = tempnam(sys_get_temp_dir(), 'env_test_');
        $this->tmpConfigPath = sys_get_temp_dir() . '/config_test_' . uniqid();
        mkdir($this->tmpConfigPath, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->cleanCompiled();
        if (file_exists($this->tmpEnvFile)) {
            unlink($this->tmpEnvFile);
        }
        $this->removeDir($this->tmpConfigPath);
        parent::tearDown();
    }

    private function cleanCompiled(): void
    {
        if (is_dir($this->compiledPath)) {
            foreach (glob($this->compiledPath . '/*.php') as $f) {
                unlink($f);
            }
        }
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . '/' . $item;
            is_dir($full) ? $this->removeDir($full) : unlink($full);
        }
        rmdir($path);
    }

    private function writeEnv(string $content): void
    {
        file_put_contents($this->tmpEnvFile, $content);
    }

    private function writeConfig(string $name, string $content): void
    {
        file_put_contents($this->tmpConfigPath . '/' . $name, $content);
    }

    private function envCleanup(string $key): void
    {
        putenv($key);
        unset($_ENV[$key]);
    }

    // ================================================================
    //  EnvLoader
    // ================================================================

    public function testEnvLoaderBasicKeyValue(): void
    {
        $this->writeEnv("TEST_BASIC_KEY=hello");
        EnvLoader::load($this->tmpEnvFile);

        $this->assertEquals('hello', $_ENV['TEST_BASIC_KEY']);
        $this->assertEquals('hello', getenv('TEST_BASIC_KEY'));
        $this->envCleanup('TEST_BASIC_KEY');
    }

    public function testEnvLoaderSkipsComments(): void
    {
        $this->writeEnv("# comment line\nTEST_COMMENT=yes");
        EnvLoader::load($this->tmpEnvFile);

        $this->assertEquals('yes', $_ENV['TEST_COMMENT']);
        $this->assertArrayNotHasKey('# comment line', $_ENV);
        $this->envCleanup('TEST_COMMENT');
    }

    public function testEnvLoaderSkipsEmptyLines(): void
    {
        $this->writeEnv("\n\nTEST_EMPTY=val\n\n");
        EnvLoader::load($this->tmpEnvFile);

        $this->assertEquals('val', $_ENV['TEST_EMPTY']);
        $this->envCleanup('TEST_EMPTY');
    }

    public function testEnvLoaderDoubleQuotes(): void
    {
        $this->writeEnv('TEST_DQ="hello world"');
        EnvLoader::load($this->tmpEnvFile);

        $this->assertEquals('hello world', $_ENV['TEST_DQ']);
        $this->envCleanup('TEST_DQ');
    }

    public function testEnvLoaderSingleQuotes(): void
    {
        $this->writeEnv("TEST_SQ='foo bar'");
        EnvLoader::load($this->tmpEnvFile);

        $this->assertEquals('foo bar', $_ENV['TEST_SQ']);
        $this->envCleanup('TEST_SQ');
    }

    public function testEnvLoaderExportPrefix(): void
    {
        $this->writeEnv("export TEST_EXP=yes");
        EnvLoader::load($this->tmpEnvFile);

        $this->assertEquals('yes', $_ENV['TEST_EXP']);
        $this->envCleanup('TEST_EXP');
    }

    public function testEnvLoaderNoOverwrite(): void
    {
        $_ENV['TEST_NOVER'] = 'original';
        putenv('TEST_NOVER=original');

        $this->writeEnv("TEST_NOVER=changed");
        EnvLoader::load($this->tmpEnvFile);

        $this->assertEquals('original', $_ENV['TEST_NOVER']);
        $this->envCleanup('TEST_NOVER');
    }

    public function testEnvLoaderMissingFileSilent(): void
    {
        $before = count($_ENV);
        EnvLoader::load('/nonexistent/.env_' . uniqid());
        $this->assertEquals($before, count($_ENV));
    }

    public function testEnvLoaderSkipsNoEquals(): void
    {
        $this->writeEnv("INVALID\nTEST_VALID=ok");
        EnvLoader::load($this->tmpEnvFile);

        $this->assertArrayNotHasKey('INVALID', $_ENV);
        $this->assertEquals('ok', $_ENV['TEST_VALID']);
        $this->envCleanup('TEST_VALID');
    }

    public function testEnvLoaderEmptyKeySkipped(): void
    {
        $this->writeEnv("=no_key_value");
        EnvLoader::load($this->tmpEnvFile);

        // 不应有空键写入
        $this->assertNull($_ENV[''] ?? null);
    }

    public function testEnvLoaderInlineHashIsValue(): void
    {
        // 值中的 # 不应被视为注释
        $this->writeEnv("TEST_HASH=pass#word");
        EnvLoader::load($this->tmpEnvFile);

        $this->assertEquals('pass#word', $_ENV['TEST_HASH']);
        $this->envCleanup('TEST_HASH');
    }

    public function testEnvLoaderEqualsInValue(): void
    {
        $this->writeEnv("TEST_EQ=key=val=ue");
        EnvLoader::load($this->tmpEnvFile);

        $this->assertEquals('key=val=ue', $_ENV['TEST_EQ']);
        $this->envCleanup('TEST_EQ');
    }

    // ================================================================
    //  Config 文件结构
    // ================================================================

    public function testConfigAppFileStructure(): void
    {
        $config = require basePath('/config/app.php');

        $this->assertTrue(is_array($config));
        $this->assertArrayHasKey('name', $config);
        $this->assertArrayHasKey('env', $config);
        $this->assertArrayHasKey('debug', $config);
        $this->assertArrayHasKey('url', $config);
        $this->assertArrayHasKey('timezone', $config);
    }

    public function testConfigAppDefaults(): void
    {
        $config = require basePath('/config/app.php');

        // 默认值（无 .env 时）
        $this->assertEquals('UTC', $config['timezone']);
    }

    public function testConfigDatabaseFileStructure(): void
    {
        $config = require basePath('/config/database.php');

        $this->assertTrue(is_array($config));
        $this->assertArrayHasKey('default', $config);
        $this->assertArrayHasKey('resultType', $config);
        $this->assertArrayHasKey('connections', $config);
    }

    public function testConfigDatabaseConnectionStructure(): void
    {
        $config = require basePath('/config/database.php');

        $this->assertArrayHasKey('mysql', $config['connections']);
        $mysql = $config['connections']['mysql'];

        $this->assertArrayHasKey('driver', $mysql);
        $this->assertArrayHasKey('host', $mysql);
        $this->assertArrayHasKey('port', $mysql);
        $this->assertArrayHasKey('user', $mysql);
        $this->assertArrayHasKey('pass', $mysql);
        $this->assertArrayHasKey('dbname', $mysql);
    }

    public function testConfigAuthFileStructure(): void
    {
        $config = require basePath('/config/auth.php');

        $this->assertTrue(is_array($config));
        $this->assertArrayHasKey('provider', $config);
    }

    // ================================================================
    //  ConfigRepository - 基础操作
    // ================================================================

    public function testRepoGetSimple(): void
    {
        $this->writeConfig('test.php', "<?php return ['name' => 'App', 'debug' => true];");

        $repo = new ConfigRepository($this->tmpConfigPath);

        $this->assertEquals('App', $repo->get('test.name'));
        $this->assertTrue($repo->get('test.debug'));
    }

    public function testRepoGetDefault(): void
    {
        $this->writeConfig('defaults.php', "<?php return ['name' => 'App'];");

        $repo = new ConfigRepository($this->tmpConfigPath);

        $this->assertEquals('fallback', $repo->get('defaults.missing', 'fallback'));
        $this->assertNull($repo->get('defaults.nonexistent'));
    }

    public function testRepoGetNested(): void
    {
        $this->writeConfig('test.php', "<?php return ['db' => ['mysql' => ['host' => '127.0.0.1']]];");

        $repo = new ConfigRepository($this->tmpConfigPath);

        $this->assertEquals('127.0.0.1', $repo->get('test.db.mysql.host'));
    }

    public function testRepoGetWholeFile(): void
    {
        $this->writeConfig('test.php', "<?php return ['a' => 1, 'b' => 2];");

        $repo = new ConfigRepository($this->tmpConfigPath);
        $all = $repo->get('test');

        $this->assertTrue(is_array($all));
        $this->assertEquals(1, $all['a']);
        $this->assertEquals(2, $all['b']);
    }

    public function testRepoSetAndGet(): void
    {
        $this->writeConfig('test.php', "<?php return ['name' => 'original'];");

        $repo = new ConfigRepository($this->tmpConfigPath);
        $repo->set('test.name', 'changed');

        $this->assertEquals('changed', $repo->get('test.name'));
    }

    public function testRepoHas(): void
    {
        $this->writeConfig('test.php', "<?php return ['name' => 'App'];");

        $repo = new ConfigRepository($this->tmpConfigPath);

        $this->assertTrue($repo->has('test.name'));
        $this->assertFalse($repo->has('test.nonexistent'));
    }

    public function testRepoFileNotFound(): void
    {
        $repo = new ConfigRepository($this->tmpConfigPath);

        $this->assertThrows('RuntimeException', function () use ($repo) {
            $repo->get('nonexistent.key');
        });
    }

    public function testRepoPreload(): void
    {
        $this->writeConfig('a.php', "<?php return ['x' => 1];");
        $this->writeConfig('b.php', "<?php return ['y' => 2];");

        $repo = new ConfigRepository($this->tmpConfigPath);
        $repo->preload(['a', 'b']);

        $this->assertEquals(1, $repo->get('a.x'));
        $this->assertEquals(2, $repo->get('b.y'));
    }

    public function testRepoSaveAndReload(): void
    {
        $this->writeConfig('test.php', "<?php return ['name' => 'original'];");

        $repo = new ConfigRepository($this->tmpConfigPath);
        $repo->set('test.name', 'saved');
        $repo->save('test');

        // 新实例验证持久化
        $repo2 = new ConfigRepository($this->tmpConfigPath);
        $this->assertEquals('saved', $repo2->get('test.name'));
    }

    public function testRepoSaveImmediately(): void
    {
        $this->writeConfig('test.php', "<?php return ['v' => 1];");

        $repo = new ConfigRepository($this->tmpConfigPath);
        $repo->saveImmediately('test.v', 99);

        $repo2 = new ConfigRepository($this->tmpConfigPath);
        $this->assertEquals(99, $repo2->get('test.v'));
    }

    public function testRepoDiscardChanges(): void
    {
        $this->writeConfig('test.php', "<?php return ['name' => 'original'];");

        $repo = new ConfigRepository($this->tmpConfigPath);
        $repo->set('test.name', 'discarded');
        $repo->discardChanges();

        $this->assertEquals('original', $repo->get('test.name'));
    }

    public function testRepoClearCache(): void
    {
        $this->writeConfig('test.php', "<?php return ['name' => 'original'];");

        $repo = new ConfigRepository($this->tmpConfigPath);
        $repo->get('test.name'); // 触发缓存

        $repo->set('test.name', 'new');
        $repo->clearCache();

        $this->assertEquals('new', $repo->get('test.name'));
    }

    public function testRepoSetPath(): void
    {
        $this->writeConfig('test.php', "<?php return ['k' => 'v'];");

        $repo = new ConfigRepository($this->tmpConfigPath);
        $this->assertEquals($this->tmpConfigPath, $repo->getPath());

        $newPath = sys_get_temp_dir() . '/cfg_' . uniqid();
        mkdir($newPath);
        file_put_contents($newPath . '/x.php', "<?php return ['y' => 'z'];");

        $repo->setPath($newPath);
        $this->assertEquals('z', $repo->get('x.y'));

        $this->removeDir($newPath);
    }

    public function testRepoCompiledPathAccessors(): void
    {
        $repo = new ConfigRepository($this->tmpConfigPath);

        $repo->setCompiledPath('/tmp/test_compiled');
        $this->assertEquals('/tmp/test_compiled', $repo->getCompiledPath());
    }

    // ================================================================
    //  ConfigRepository - Fallback（编译优先）
    // ================================================================

    public function testFallbackReadsSourceWhenNoCompiled(): void
    {
        $this->cleanCompiled();

        $repo = new ConfigRepository();
        $name = $repo->get('app.name');
        $this->assertNotNull($name);
    }

    public function testFallbackPrefersCompiled(): void
    {
        $this->cleanCompiled();
        if (!is_dir($this->compiledPath)) {
            mkdir($this->compiledPath, 0755, true);
        }

        file_put_contents(
            $this->compiledPath . '/app.php',
            "<?php\nreturn ['name' => 'COMPILED', 'env' => 'test', 'debug' => false, 'url' => 'http://test', 'timezone' => 'UTC'];\n"
        );

        $repo = new ConfigRepository();
        $this->assertEquals('COMPILED', $repo->get('app.name'));
    }

    public function testFallbackDatabaseNestedRead(): void
    {
        $repo = new ConfigRepository();

        $host = $repo->get('database.connections.mysql.host');
        $this->assertNotNull($host);
        $this->assertStringContainsString('.', $host); // IP 地址含 .
    }

    public function testFallbackDatabaseDefaultDriver(): void
    {
        $repo = new ConfigRepository();

        $driver = $repo->get('database.default');
        $this->assertNotNull($driver);
        $this->assertContains($driver, ['mysql', 'sqlite', 'pgsql']);
    }

    public function testFallbackAuthProvider(): void
    {
        $repo = new ConfigRepository();

        $provider = $repo->get('auth.provider');
        $this->assertNotNull($provider);
        $this->assertStringContainsString('User', $provider);
    }

    // ================================================================
    //  config:cache 命令
    // ================================================================

    public function testConfigCacheCreatesCompiledFiles(): void
    {
        Kernel::register('config:cache', \Bin\Console\Commands\ConfigCacheCommand::class);
        $exitCode = Kernel::callSilent('config:cache');

        $this->assertEquals(0, $exitCode);
        $this->assertFileExists($this->compiledPath . '/app.php');
        $this->assertFileExists($this->compiledPath . '/database.php');
        $this->assertFileExists($this->compiledPath . '/auth.php');
    }

    public function testConfigCacheReturnsArrays(): void
    {
        Kernel::register('config:cache', \Bin\Console\Commands\ConfigCacheCommand::class);
        Kernel::callSilent('config:cache');

        $app = require $this->compiledPath . '/app.php';
        $db = require $this->compiledPath . '/database.php';
        $auth = require $this->compiledPath . '/auth.php';

        $this->assertTrue(is_array($app));
        $this->assertTrue(is_array($db));
        $this->assertTrue(is_array($auth));
    }

    public function testConfigCacheNoEnvCalls(): void
    {
        Kernel::register('config:cache', \Bin\Console\Commands\ConfigCacheCommand::class);
        Kernel::callSilent('config:cache');

        foreach (glob($this->compiledPath . '/*.php') as $file) {
            $content = file_get_contents($file);
            $this->assertStringNotContainsString('env(', $content, "Compiled file {$file} should not contain env() calls");
        }
    }

    public function testConfigCacheCompiledValuesMatchSource(): void
    {
        Kernel::register('config:cache', \Bin\Console\Commands\ConfigCacheCommand::class);
        Kernel::callSilent('config:cache');

        $sourceApp = require basePath('/config/app.php');
        $compiledApp = require $this->compiledPath . '/app.php';

        // 编译后的值应与源文件解析后一致
        $this->assertEquals($sourceApp['timezone'], $compiledApp['timezone']);
        $this->assertEquals($sourceApp['name'], $compiledApp['name']);
    }

    public function testConfigCacheCompiledHasDeclare(): void
    {
        Kernel::register('config:cache', \Bin\Console\Commands\ConfigCacheCommand::class);
        Kernel::callSilent('config:cache');

        $content = file_get_contents($this->compiledPath . '/app.php');
        $this->assertStringContainsString('declare(strict_types=1)', $content);
    }

    public function testConfigCacheCompiledHasReturnStatement(): void
    {
        Kernel::register('config:cache', \Bin\Console\Commands\ConfigCacheCommand::class);
        Kernel::callSilent('config:cache');

        $content = file_get_contents($this->compiledPath . '/database.php');
        $this->assertStringContainsString('return [', $content);
    }

    // ================================================================
    //  config:clear 命令
    // ================================================================

    public function testConfigClearRemovesFiles(): void
    {
        Kernel::register('config:cache', \Bin\Console\Commands\ConfigCacheCommand::class);
        Kernel::callSilent('config:cache');

        $this->assertFileExists($this->compiledPath . '/app.php');

        Kernel::register('config:clear', \Bin\Console\Commands\ConfigClearCommand::class);
        $exitCode = Kernel::callSilent('config:clear');

        $this->assertEquals(0, $exitCode);
        $this->assertFileDoesNotExist($this->compiledPath . '/app.php');
        $this->assertFileDoesNotExist($this->compiledPath . '/database.php');
        $this->assertFileDoesNotExist($this->compiledPath . '/auth.php');
    }

    public function testConfigClearEmptyDir(): void
    {
        $this->cleanCompiled();

        Kernel::register('config:clear', \Bin\Console\Commands\ConfigClearCommand::class);
        $exitCode = Kernel::callSilent('config:clear');

        // 目录不存在时也应正常返回
        $this->assertEquals(0, $exitCode);
    }

    // ================================================================
    //  config() helper
    // ================================================================

    public function testConfigHelperGet(): void
    {
        $val = config('app.timezone');
        $this->assertEquals('UTC', $val);
    }

    public function testConfigHelperGetReturnsNullForMissingKey(): void
    {
        $val = config('app.nonexistent_key_12345');
        $this->assertNull($val);
    }

    public function testConfigHelperSet(): void
    {
        config(['test_cfg.key1' => 'val1']);
        $this->assertEquals('val1', config('test_cfg.key1'));
    }

    public function testConfigHelperReturnsRepository(): void
    {
        $repo = config();
        $this->assertInstanceOf(ConfigRepository::class, $repo);
    }

    public function testConfigHelperSetAndGetBatch(): void
    {
        config(['test_batch.a' => '1', 'test_batch.b' => '2']);
        $this->assertEquals('1', config('test_batch.a'));
        $this->assertEquals('2', config('test_batch.b'));
    }

    // ================================================================
    //  端到端：env → config → compiled → read
    // ================================================================

    public function testEndToEndEnvToConfigToCompiled(): void
    {
        // 1. 设置环境变量
        $_ENV['APP_NAME'] = 'E2E Test App';
        putenv('APP_NAME=E2E Test App');

        // 2. 从源文件读取（env 值生效）
        $repo = new ConfigRepository();
        $this->assertEquals('E2E Test App', $repo->get('app.name'));

        // 3. 编译
        Kernel::register('config:cache', \Bin\Console\Commands\ConfigCacheCommand::class);
        Kernel::callSilent('config:cache');

        // 4. 清除环境变量，验证编译文件独立于 env
        $this->envCleanup('APP_NAME');

        // 5. 新实例从编译文件读取
        $repo2 = new ConfigRepository();
        $this->assertEquals('E2E Test App', $repo2->get('app.name'));
    }

    public function testEndToEndDatabaseConfigFlow(): void
    {
        // 使用独立临时目录，避免全局 config helper 缓存干扰
        $tmpDir = sys_get_temp_dir() . '/cfg_e2e_' . uniqid();
        mkdir($tmpDir, 0777, true);

        $this->envCleanup('E2E_DB_HOST');
        $_ENV['E2E_DB_HOST'] = '10.0.0.1';
        putenv('E2E_DB_HOST=10.0.0.1');

        $dbContent = "<?php\nreturn [\n    'default' => 'mysql',\n    'connections' => [\n        'mysql' => [\n            'host' => env('E2E_DB_HOST', '127.0.0.1'),\n        ],\n    ],\n];\n";
        file_put_contents($tmpDir . '/database.php', $dbContent);

        $repo = new ConfigRepository($tmpDir);
        $repo->setCompiledPath($tmpDir); // 避免 fallback 到全局 storage/config
        $host = $repo->get('database.connections.mysql.host');
        $this->assertEquals('10.0.0.1', $host);

        $this->envCleanup('E2E_DB_HOST');
        $this->removeDir($tmpDir);
    }

    // ================================================================
    //  边界情况
    // ================================================================

    public function testRepoLoadsFileOnce(): void
    {
        $this->writeConfig('test.php', "<?php return ['name' => 'App'];");

        $repo = new ConfigRepository($this->tmpConfigPath);
        $repo->get('test.name');

        // 修改文件内容，再次读取应返回缓存值
        file_put_contents($this->tmpConfigPath . '/test.php', "<?php return ['name' => 'Changed'];");

        $this->assertEquals('App', $repo->get('test.name'));
    }

    public function testRepoSetOverridesCache(): void
    {
        $this->writeConfig('test.php', "<?php return ['name' => 'original'];");

        $repo = new ConfigRepository($this->tmpConfigPath);
        $repo->get('test.name'); // 触发加载+缓存

        $repo->set('test.name', 'override');
        $this->assertEquals('override', $repo->get('test.name'));
    }

    public function testConfigKeyWithDeepNesting(): void
    {
        $this->writeConfig('deep.php', "<?php return ['a' => ['b' => ['c' => ['d' => 'deep_val']]]];");

        $repo = new ConfigRepository($this->tmpConfigPath);
        $this->assertEquals('deep_val', $repo->get('deep.a.b.c.d'));
        $this->assertNull($repo->get('deep.a.b.c.e'));
    }

    public function testConfigValueTypes(): void
    {
        $this->writeConfig('types.php', "<?php return ['str' => 'hello', 'int' => 42, 'float' => 3.14, 'bool' => true, 'null' => null, 'arr' => [1, 2, 3]];");

        $repo = new ConfigRepository($this->tmpConfigPath);

        $this->assertEquals('hello', $repo->get('types.str'));
        $this->assertEquals(42, $repo->get('types.int'));
        $this->assertEquals(3.14, $repo->get('types.float'));
        $this->assertTrue($repo->get('types.bool'));
        $this->assertNull($repo->get('types.null'));
        $this->assertEquals([1, 2, 3], $repo->get('types.arr'));
    }
}
