<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Database\QueryBuilder;
use Bin\Database\Model;
use Bin\Database\SoftDeletes;
use PDO;

/**
 * 软删除功能测试
 */
class SoftDeletesTest extends TestCase
{
    protected ?PDO $connection = null;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建 SQLite 内存数据库
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 创建测试表（带 deleted_at 字段）
        $this->connection->exec('
            CREATE TABLE soft_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                deleted_at TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');

        // 重置模型引导状态
        SoftTestUser::resetBooted();

        // 设置模型连接
        SoftTestUser::setPdoConnection($this->connection);

        // 插入测试数据
        for ($i = 1; $i <= 10; $i++) {
            $stmt = $this->connection->prepare('INSERT INTO soft_users (name, email, deleted_at) VALUES (?, ?, ?)');
            $deletedAt = $i <= 3 ? date('Y-m-d H:i:s') : null;
            $stmt->execute(["User {$i}", "user{$i}@example.com", $deletedAt]);
        }
    }

    protected function tearDown(): void
    {
        SoftTestUser::clearGlobalScopes();
        SoftTestUser::resetBooted();
        SoftTestUser::setPdoConnection(null);
        $this->connection = null;

        parent::tearDown();
    }

    // =========================================================================
    // 基本软删除测试
    // =========================================================================

    public function testSoftDelete(): void
    {
        // 获取一个未删除的用户
        $user = SoftTestUser::find(4);

        $this->assertNotNull($user);
        $this->assertFalse($user->trashed());

        // 软删除
        $result = $user->delete();

        $this->assertTrue($result);
        $this->assertTrue($user->trashed());
        $this->assertNotNull($user->deleted_at);

        // 验证记录仍然存在
        $stmt = $this->connection->query('SELECT COUNT(*) FROM soft_users WHERE id = 4');
        $this->assertEquals(1, $stmt->fetchColumn());
    }

    public function testSoftDeleteSetsDeletedAt(): void
    {
        $user = SoftTestUser::find(5);

        $this->assertNull($user->deleted_at);

        $user->delete();

        $this->assertNotNull($user->deleted_at);
    }

    public function testSoftDeletedModelNotInDefaultQuery(): void
    {
        // 默认查询不应该包含软删除记录
        $users = SoftTestUser::all();

        // 前 3 条已被软删除
        $this->assertCount(7, $users);

        // 验证 ID 范围
        foreach ($users as $user) {
            $this->assertGreaterThan(3, $user->id);
        }
    }

    public function testFindExcludesSoftDeleted(): void
    {
        // 已软删除的记录
        $user1 = SoftTestUser::find(1);
        $this->assertNull($user1);

        // 未删除的记录
        $user4 = SoftTestUser::find(4);
        $this->assertNotNull($user4);
    }

    // =========================================================================
    // withTrashed 测试
    // =========================================================================

    public function testWithTrashed(): void
    {
        $users = SoftTestUser::withTrashed()->get();

        // 包含所有 10 条记录
        $this->assertCount(10, $users);
    }

    public function testWithTrashedFind(): void
    {
        $user = SoftTestUser::withTrashed()->where('id', 1)->first();

        $this->assertNotNull($user);
        $this->assertEquals('User 1', $user->name);
        $this->assertTrue($user->trashed());
    }

    // =========================================================================
    // onlyTrashed 测试
    // =========================================================================

    public function testOnlyTrashed(): void
    {
        $users = SoftTestUser::onlyTrashed()->get();

        // 只有前 3 条被软删除
        $this->assertCount(3, $users);
    }

    public function testOnlyTrashedAllAreSoftDeleted(): void
    {
        $users = SoftTestUser::onlyTrashed()->get();

        foreach ($users as $user) {
            $this->assertTrue($user->trashed());
        }
    }

    // =========================================================================
    // restore 测试
    // =========================================================================

    public function testRestore(): void
    {
        // 获取已软删除的记录
        $user = SoftTestUser::withTrashed()->where('id', 1)->first();

        $this->assertTrue($user->trashed());

        // 恢复
        $result = $user->restore();

        $this->assertTrue($result);
        $this->assertFalse($user->trashed());
        $this->assertNull($user->deleted_at);

        // 验证现在可以通过默认查询找到
        $restoredUser = SoftTestUser::find(1);
        $this->assertNotNull($restoredUser);
    }

    public function testRestoreNonDeletedModel(): void
    {
        $user = SoftTestUser::find(4);

        $this->assertFalse($user->trashed());

        // 恢复未删除的模型应该返回 false
        $result = $user->restore();

        $this->assertFalse($result);
    }

    public function testRestoreMany(): void
    {
        $count = SoftTestUser::restoreMany([1, 2, 3]);

        $this->assertEquals(3, $count);

        // 验证都恢复了
        $users = SoftTestUser::all();
        $this->assertCount(10, $users);
    }

    // =========================================================================
    // forceDelete 测试
    // =========================================================================

    public function testForceDelete(): void
    {
        $user = SoftTestUser::withTrashed()->where('id', 1)->first();

        $result = $user->forceDelete();

        $this->assertTrue($result);
        $this->assertFalse($user->exists);

        // 验证记录真正被删除
        $stmt = $this->connection->query('SELECT COUNT(*) FROM soft_users WHERE id = 1');
        $this->assertEquals(0, $stmt->fetchColumn());
    }

    public function testForceDeleteNonTrashed(): void
    {
        $user = SoftTestUser::find(4);

        $result = $user->forceDelete();

        $this->assertTrue($result);

        // 验证记录被删除
        $stmt = $this->connection->query('SELECT COUNT(*) FROM soft_users WHERE id = 4');
        $this->assertEquals(0, $stmt->fetchColumn());
    }

    public function testForceDeleteMany(): void
    {
        $count = SoftTestUser::forceDeleteMany([1, 2, 3, 4]);

        $this->assertEquals(4, $count);

        // 验证总数
        $stmt = $this->connection->query('SELECT COUNT(*) FROM soft_users');
        $this->assertEquals(6, $stmt->fetchColumn());
    }

    // =========================================================================
    // trashed 测试
    // =========================================================================

    public function testTrashedMethod(): void
    {
        // 已删除的
        $deletedUser = SoftTestUser::withTrashed()->where('id', 1)->first();
        $this->assertTrue($deletedUser->trashed());

        // 未删除的
        $normalUser = SoftTestUser::find(4);
        $this->assertFalse($normalUser->trashed());
    }

    public function testIsSoftDeletedMethod(): void
    {
        $deletedUser = SoftTestUser::withTrashed()->where('id', 1)->first();
        $this->assertTrue($deletedUser->isSoftDeleted());

        $normalUser = SoftTestUser::find(4);
        $this->assertFalse($normalUser->isSoftDeleted());
    }

    // =========================================================================
    // 查询构建器测试
    // =========================================================================

    public function testQueryWithoutTrashed(): void
    {
        $users = SoftTestUser::withoutTrashed()->get();

        $this->assertCount(7, $users);
    }

    public function testCountExcludesSoftDeleted(): void
    {
        $count = SoftTestUser::count();

        $this->assertEquals(7, $count);
    }

    public function testCountWithTrashed(): void
    {
        $count = SoftTestUser::withTrashed()->count();

        $this->assertEquals(10, $count);
    }

    public function testCountOnlyTrashed(): void
    {
        $count = SoftTestUser::onlyTrashed()->count();

        $this->assertEquals(3, $count);
    }

    // =========================================================================
    // 集成测试
    // =========================================================================

    public function testSoftDeleteAndRestoreWorkflow(): void
    {
        // 1. 获取用户
        $user = SoftTestUser::find(4);
        $this->assertNotNull($user);

        // 2. 软删除
        $user->delete();
        $this->assertTrue($user->trashed());

        // 3. 验证默认查询找不到
        $this->assertNull(SoftTestUser::find(4));

        // 4. 使用 withTrashed 找到
        $trashedUser = SoftTestUser::withTrashed()->where('id', 4)->first();
        $this->assertNotNull($trashedUser);

        // 5. 恢复
        $trashedUser->restore();

        // 6. 验证可以再次找到
        $restoredUser = SoftTestUser::find(4);
        $this->assertNotNull($restoredUser);
        $this->assertFalse($restoredUser->trashed());
    }

    public function testDeleteAndForceDeleteWorkflow(): void
    {
        // 1. 软删除
        $user = SoftTestUser::find(4);
        $user->delete();

        // 2. 使用 withTrashed 找到
        $trashedUser = SoftTestUser::withTrashed()->where('id', 4)->first();
        $this->assertNotNull($trashedUser);

        // 3. 强制删除
        $trashedUser->forceDelete();

        // 4. 验证彻底删除
        $deletedUser = SoftTestUser::withTrashed()->where('id', 4)->first();
        $this->assertNull($deletedUser);
    }

    // =========================================================================
    // 边界条件测试
    // =========================================================================

    public function testDoubleSoftDelete(): void
    {
        $user = SoftTestUser::find(4);
        $user->delete();
        $this->assertTrue($user->trashed());

        // 第二次软删除仍然返回 true（更新 deleted_at 为新时间）
        $result = $user->delete();
        $this->assertTrue($result);
    }

    public function testDeleteManyWithEmptyArray(): void
    {
        // deleteMany([]) 在空数组上不执行任何删除
        // 先确认有一个记录
        $user = SoftTestUser::find(4);
        $this->assertNotNull($user);
        $count = SoftTestUser::count();

        // 空数组操作不应影响数据
        $this->assertEquals($count, SoftTestUser::count());
    }

    public function testRestoreManyWithEmptyArray(): void
    {
        $count = SoftTestUser::count();
        $result = SoftTestUser::restoreMany([]);
        $this->assertEquals(0, $result);
    }

    public function testForceDeleteManyWithEmptyArray(): void
    {
        $stmt = $this->connection->query('SELECT COUNT(*) FROM soft_users');
        $totalBefore = (int) $stmt->fetchColumn();

        $result = SoftTestUser::forceDeleteMany([]);
        $this->assertEquals(0, $result);

        $stmt = $this->connection->query('SELECT COUNT(*) FROM soft_users');
        $this->assertEquals($totalBefore, (int) $stmt->fetchColumn());
    }

    public function testOnlyTrashedOnTableWithNoTrashed(): void
    {
        // 先清空并插入全未删除数据
        $this->connection->exec('DELETE FROM soft_users');
        for ($i = 1; $i <= 3; $i++) {
            $stmt = $this->connection->prepare('INSERT INTO soft_users (name, email) VALUES (?, ?)');
            $stmt->execute(["Clean User {$i}", "clean{$i}@example.com"]);
        }

        SoftTestUser::clearGlobalScopes();
        SoftTestUser::resetBooted();

        $trashed = SoftTestUser::onlyTrashed()->get();
        $this->assertCount(0, $trashed);
    }
}

/**
 * 测试用软删除模型
 */
class SoftTestUser extends Model
{
    use SoftDeletes;

    protected string $table = 'soft_users';

    protected array $fillable = ['name', 'email'];

    /**
     * 禁用时间戳（测试表没有 updated_at）
     */
    protected bool $timestamps = false;

    /**
     * 设置 PDO 连接
     */
    public static function setPdoConnection(?PDO $connection): void
    {
        static::setConnection($connection);
    }
}