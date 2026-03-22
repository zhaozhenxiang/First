<?php

declare(strict_types=1);

namespace Tests;

use Bin\Config\ConfigRepository;
use Bin\Testing\TestCase;

/**
 * 配置仓库测试
 */
class ConfigRepositoryTest extends TestCase
{
    private string $tempConfigPath;
    private ConfigRepository $config;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建临时配置目录
        $this->tempConfigPath = sys_get_temp_dir() . '/config_test_' . uniqid();
        mkdir($this->tempConfigPath, 0777, true);

        // 创建测试配置文件
        $this->createTestConfigFiles();

        $this->config = new ConfigRepository($this->tempConfigPath);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // 清理临时目录
        $this->removeDirectory($this->tempConfigPath);
    }

    private function createTestConfigFiles(): void
    {
        // app.php - 使用标准格式
        $content = '<?php' . PHP_EOL;
        $content .= 'return [' . PHP_EOL;
        $content .= '    "name" => "Test App",' . PHP_EOL;
        $content .= '    "debug" => true,' . PHP_EOL;
        $content .= '    "timezone" => "UTC",' . PHP_EOL;
        $content .= '];' . PHP_EOL;

        file_put_contents($this->tempConfigPath . '/app.php', $content);
    }

    public function testGetSimpleValue(): void
    {
        $value = $this->config->get('app.name');

        $this->assertEquals('Test App', $value);
    }

    public function testGetBooleanValue(): void
    {
        $value = $this->config->get('app.debug');

        $this->assertTrue($value);
    }

    public function testGetWithDefault(): void
    {
        $value = $this->config->get('app.nonexistent', 'default');

        $this->assertEquals('default', $value);
    }

    public function testGetAll(): void
    {
        $all = $this->config->get('app');

        $this->assertIsType('array', $all);
        $this->assertEquals('Test App', $all['name']);
    }

    public function testHas(): void
    {
        $this->assertTrue($this->config->has('app.name'));
        $this->assertFalse($this->config->has('app.nonexistent'));
    }

    public function testSet(): void
    {
        $this->config->set('app.name', 'New Name');

        $value = $this->config->get('app.name');

        $this->assertEquals('New Name', $value);
    }

    public function testSetNewKey(): void
    {
        $this->config->set('app.new_key', 'new_value');

        $value = $this->config->get('app.new_key');

        $this->assertEquals('new_value', $value);
    }

    public function testLoad(): void
    {
        $config = $this->config->load('app');

        $this->assertIsType('array', $config);
        $this->assertEquals('Test App', $config['name']);
    }

    public function testSave(): void
    {
        $this->config->set('app.name', 'Saved Name');
        $this->config->save('app');

        // 创建新的配置实例来验证持久化
        $newConfig = new ConfigRepository($this->tempConfigPath);
        $value = $newConfig->get('app.name');

        $this->assertEquals('Saved Name', $value);
    }

    public function testSaveNested(): void
    {
        $this->config->set('app.database.host', '127.0.0.1');
        $this->config->save('app');

        $newConfig = new ConfigRepository($this->tempConfigPath);
        $value = $newConfig->get('app.database.host');

        $this->assertEquals('127.0.0.1', $value);
    }

    public function testSaveAll(): void
    {
        $this->config->set('app.name', 'App Name');
        $this->config->set('app.debug', false);

        $this->config->saveAll();

        $newConfig = new ConfigRepository($this->tempConfigPath);

        $this->assertEquals('App Name', $newConfig->get('app.name'));
        $this->assertFalse($newConfig->get('app.debug'));
    }

    public function testSaveImmediately(): void
    {
        $result = $this->config->saveImmediately('app.debug', false);

        $this->assertTrue($result);

        $newConfig = new ConfigRepository($this->tempConfigPath);
        $value = $newConfig->get('app.debug');

        $this->assertFalse($value);
    }

    public function testGetPendingChanges(): void
    {
        $this->config->set('app.name', 'Pending');

        $changes = $this->config->getPendingChanges();

        $this->assertIsType('array', $changes);
        $this->assertNotEmpty($changes);
    }

    public function testDiscardChanges(): void
    {
        $this->config->set('app.name', 'Pending');
        $this->config->discardChanges();

        $value = $this->config->get('app.name');

        $this->assertEquals('Test App', $value);
    }

    public function testClearCache(): void
    {
        $this->config->get('app.name'); // 缓存
        $this->config->set('app.name', 'New');
        $this->config->clearCache();

        $value = $this->config->get('app.name');

        $this->assertEquals('New', $value);
    }

    public function testSetPath(): void
    {
        $newPath = sys_get_temp_dir() . '/config_test_new_' . uniqid();
        mkdir($newPath, 0777, true);

        file_put_contents($newPath . '/test.php', "<?php return ['key' => 'value'];");

        $this->config->setPath($newPath);
        $value = $this->config->get('test.key');

        $this->assertEquals('value', $value);

        $this->removeDirectory($newPath);
    }

    public function testGetPath(): void
    {
        $path = $this->config->getPath();

        $this->assertEquals($this->tempConfigPath, $path);
    }

    public function testMacro(): void
    {
        ConfigRepository::macro('uppercase', function () {
            return strtoupper($this->get('app.name'));
        });

        $value = $this->config->uppercase();

        $this->assertEquals('TEST APP', $value);
    }

    public function testHasMacro(): void
    {
        ConfigRepository::macro('testMacro', function () {
            return true;
        });

        $this->assertTrue(ConfigRepository::hasMacro('testMacro'));
        $this->assertFalse(ConfigRepository::hasMacro('nonexistent'));
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
