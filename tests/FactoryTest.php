<?php

declare(strict_types=1);

namespace Tests;

use Bin\Database\Model;
use Bin\Database\Factory;
use Bin\Testing\TestCase;

class FactoryTest extends TestCase
{
    protected \PDO $pdo;

    public function setUp(): void
    {
        parent::setUp();

        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE fac_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT \'user\'
        )');

        FacUser::setConnection($this->pdo);
        Factory::flush();
    }

    public function tearDown(): void
    {
        FacUser::setConnection(null);
        Factory::flush();
        parent::tearDown();
    }

    // === define / make ===

    public function testDefineAndMake(): void
    {
        Factory::define(FacUser::class, function () {
            return ['name' => 'Test User', 'email' => 'test@example.com'];
        });

        $user = Factory::make(FacUser::class);
        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertFalse($user->exists);
    }

    // === create ===

    public function testCreateSavesToDatabase(): void
    {
        Factory::define(FacUser::class, function () {
            return ['name' => 'DB User', 'email' => 'db@example.com'];
        });

        $user = Factory::create(FacUser::class);
        $this->assertTrue($user->exists);
        $this->assertNotNull($user->id);

        // 验证数据库中有记录
        $found = FacUser::find($user->id);
        $this->assertNotNull($found);
        $this->assertEquals('DB User', $found->name);
    }

    // === 属性覆盖 ===

    public function testAttributeOverride(): void
    {
        Factory::define(FacUser::class, function () {
            return ['name' => 'Default', 'email' => 'default@example.com'];
        });

        $user = Factory::make(FacUser::class, ['name' => 'Override']);
        $this->assertEquals('Override', $user->name);
        $this->assertEquals('default@example.com', $user->email);
    }

    // === state ===

    public function testState(): void
    {
        Factory::define(FacUser::class, function () {
            return ['name' => 'Normal', 'email' => 'normal@example.com', 'role' => 'user'];
        });

        Factory::state(FacUser::class, 'admin', function () {
            return ['role' => 'admin', 'name' => 'Admin User'];
        });

        $user = Factory::make(FacUser::class, [], ['admin']);
        $this->assertEquals('Admin User', $user->name);
        $this->assertEquals('admin', $user->role);
    }

    // === times ===

    public function testTimesCreatesMultiple(): void
    {
        Factory::define(FacUser::class, function () {
            return ['name' => 'Batch', 'email' => 'batch@example.com'];
        });

        $users = Factory::times(3, FacUser::class);
        $this->assertEquals(3, $users->count());

        foreach ($users as $user) {
            $this->assertTrue($user->exists);
        }
    }

    // === makeTimes ===

    public function testMakeTimesCreatesMultipleWithoutSaving(): void
    {
        Factory::define(FacUser::class, function () {
            return ['name' => 'Unsaved', 'email' => 'unsaved@example.com'];
        });

        $users = Factory::makeTimes(2, FacUser::class);
        $this->assertEquals(2, $users->count());

        foreach ($users as $user) {
            $this->assertFalse($user->exists);
        }
    }

    // === 未定义工厂抛异常 ===

    public function testUndefinedFactoryThrowsException(): void
    {
        $threw = false;
        try {
            Factory::make('NonExistentModel');
        } catch (\InvalidArgumentException $e) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected InvalidArgumentException was not thrown');
    }

    // === flush ===

    public function testFlush(): void
    {
        Factory::define(FacUser::class, function () {
            return ['name' => 'X', 'email' => 'x@example.com'];
        });

        Factory::flush();
        $this->assertEmpty(Factory::getDefinitions());
        $this->assertEmpty(Factory::getStates());
    }
}

// === 测试模型 ===

class FacUser extends Model
{
    protected string $table = 'fac_users';
    protected array $guarded = [];
    protected array $fillable = ['name', 'email', 'role'];
    protected bool $timestamps = false;
}

return new FactoryTest();
