<?php

declare(strict_types=1);

namespace Tests;

use Bin\Database\Seeders\Seeder;
use Bin\Database\Seeders\SeederFactory;
use Bin\Database\Seeders\SeederRepository;
use Bin\Database\Seeders\SeederCreator;
use Bin\Testing\TestCase;

/**
 * Seeder 测试
 */
class SeederTest extends TestCase
{
    private string $testSeederPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testSeederPath = basePath('storage/test/seeders');
        SeederRepository::clear();

        // 确保测试目录存在
        if (!is_dir($this->testSeederPath)) {
            mkdir($this->testSeederPath, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // 清理测试文件
        if (is_dir($this->testSeederPath)) {
            $files = glob($this->testSeederPath . '/*Seeder.php');
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }

    public function testSeederCreation(): void
    {
        $creator = new SeederCreator($this->testSeederPath);
        $path = $creator->create('Test');

        $this->assertFileExists($path);
        $this->assertStringContainsString('TestSeeder', file_get_contents($path));
        $this->assertStringContainsString('class TestSeeder extends Seeder', file_get_contents($path));
    }

    public function testSeederCreationThrowsExceptionOnExisting(): void
    {
        $creator = new SeederCreator($this->testSeederPath);
        $creator->create('Duplicate');

        $this->assertThrows(\RuntimeException::class, function() use ($creator) {
            $creator->create('Duplicate');
        });
    }

    public function testSeederRegistration(): void
    {
        SeederRepository::register('test', 'TestSeeder');

        $seeders = SeederRepository::all();

        $this->assertArrayHasKey('test', $seeders);
        $this->assertEquals('TestSeeder', $seeders['test']);
    }

    public function testSeederRepositoryClear(): void
    {
        // 清除已注册的 seeders
        SeederRepository::clear();

        SeederRepository::register('test1', 'TestSeeder1');
        SeederRepository::register('test2', 'TestSeeder2');

        // 验证注册成功
        $seeders = SeederRepository::all();
        $this->assertArrayHasKey('test1', $seeders);
        $this->assertArrayHasKey('test2', $seeders);

        // 清除
        SeederRepository::clear();

        // 验证清除后注册的 seeders 没有了（但可能还有发现的 seeders）
        $seeders = SeederRepository::all();
        $this->assertArrayNotHasKey('test1', $seeders);
        $this->assertArrayNotHasKey('test2', $seeders);
    }

    public function testSeederFactoryMake(): void
    {
        $factory = new SeederFactory(\stdClass::class);

        $instance = $factory->make(['name' => 'Test']);

        $this->assertInstanceOf(\stdClass::class, $instance);
    }

    public function testSeederFactoryState(): void
    {
        $factory = new SeederFactory(\stdClass::class);
        $factory->state('active', function() { return ['status' => 'active']; });

        $instance = $factory->make();

        $this->assertEquals('active', $instance->status ?? null);
    }

    public function testSeederFactoryWithStates(): void
    {
        $factory = new SeederFactory(\stdClass::class);
        $factory->state('active', function() { return ['status' => 'active']; });
        $factory->state('verified', function() { return ['verified' => true]; });

        $instance = $factory->withStates(['verified'])->make();

        $this->assertTrue(($instance->verified ?? false));
        $this->assertNull($instance->status ?? null);
    }

    public function testSeederFactoryAfterMaking(): void
    {
        $factory = new SeederFactory(\stdClass::class);
        $factory->afterMaking(function($instance) {
            $instance->processed = true;
        });

        $instance = $factory->make();

        $this->assertTrue($instance->processed ?? false);
    }

    public function testSeederFactoryPath(): void
    {
        $factory = new SeederFactory(\stdClass::class);

        $this->assertEquals(\stdClass::class, $factory->getModel());
    }

    public function testSeederCallMethod(): void
    {
        $executed = [];

        // 测试 run 方法执行
        $seeder1 = new class extends Seeder {
            public function run(): void {
                // 测试基础 run 方法
            }
        };

        $seeder1->run();

        $this->assertTrue(true); // 如果没有异常则通过
    }

    public function testSeederRepositoryAddPath(): void
    {
        SeederRepository::addPath('/custom/path');

        // 路径应该被添加（内部状态）
        $this->assertTrue(true); // 如果没有抛出异常就通过
    }

    public function testSeederRepositorySetAppNamespace(): void
    {
        SeederRepository::setAppNamespace('Custom\\Namespace\\');

        $this->assertEquals('Custom\\Namespace\\', SeederRepository::getAppNamespace());
    }

    public function testSeederCreatorSetPath(): void
    {
        $creator = new SeederCreator();
        $creator->setPath('/custom/path');

        $this->assertEquals('/custom/path', $creator->getPath());
    }

    public function testSeederCreatorChainable(): void
    {
        $creator = new SeederCreator();
        $result = $creator->setPath('/test');

        $this->assertSame($creator, $result);
    }

    public function testSeederFactoryChainable(): void
    {
        $factory = new SeederFactory(\stdClass::class);

        $result1 = $factory->state('test', function() { return []; });
        $result2 = $factory->afterMaking(function() {});
        $result3 = $factory->afterCreating(function() {});
        $result4 = $factory->withStates([]);

        $this->assertSame($factory, $result1);
        $this->assertSame($factory, $result2);
        $this->assertSame($factory, $result3);
        $this->assertNotSame($factory, $result4); // withStates 返回克隆
    }

    public function testSeederFactoryCreateMany(): void
    {
        $factory = new SeederFactory(\ArrayObject::class);

        $instances = $factory->createMany(3);

        $this->assertCount(3, $instances);
        foreach ($instances as $instance) {
            $this->assertInstanceOf(\ArrayObject::class, $instance);
        }
    }
}
