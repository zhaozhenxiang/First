<?php

declare(strict_types=1);

namespace Tests;

use Bin\Database\Model;
use Bin\Database\Pivot;
use Bin\Database\Factory;
use Bin\Database\Observer;
use Bin\Database\ModelEventDispatcher;
use Bin\Testing\TestCase;

class PivotModelTest extends TestCase
{
    protected \PDO $pdo;

    public function setUp(): void
    {
        parent::setUp();

        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE pv_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL
        )');
        $this->pdo->exec('CREATE TABLE pv_roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL
        )');
        $this->pdo->exec('CREATE TABLE pv_role_user (
            user_id INTEGER NOT NULL,
            role_id INTEGER NOT NULL,
            note TEXT
        )');

        $this->pdo->exec("INSERT INTO pv_users (name) VALUES ('Alice')");
        $this->pdo->exec("INSERT INTO pv_users (name) VALUES ('Bob')");
        $this->pdo->exec("INSERT INTO pv_roles (name) VALUES ('Admin')");
        $this->pdo->exec("INSERT INTO pv_roles (name) VALUES ('Editor')");
        $this->pdo->exec("INSERT INTO pv_role_user (user_id, role_id, note) VALUES (1, 1, 'primary admin')");
        $this->pdo->exec("INSERT INTO pv_role_user (user_id, role_id, note) VALUES (1, 2, 'can edit')");
        $this->pdo->exec("INSERT INTO pv_role_user (user_id, role_id, note) VALUES (2, 2, 'content editor')");

        PvUser::setConnection($this->pdo);
        PvRole::setConnection($this->pdo);
    }

    public function tearDown(): void
    {
        PvUser::setConnection(null);
        PvRole::setConnection(null);
        parent::tearDown();
    }

    // === Pivot 基本属性 ===

    public function testPivotDefaults(): void
    {
        $pivot = new Pivot();
        // Pivot defaults: no auto-increment, no timestamps, unguarded
        $this->assertInstanceOf(Pivot::class, $pivot);
    }

    public function testPivotWithCustomTable(): void
    {
        $pivot = new Pivot([], 'custom_table');
        $this->assertEquals('custom_table', $pivot->getTable());
    }

    public function testPivotParent(): void
    {
        $user = PvUser::find(1);
        $pivot = new Pivot();
        $pivot->setPivotParent($user);
        $this->assertSame($user, $pivot->getPivotParent());
    }

    public function testPivotKeys(): void
    {
        $pivot = new Pivot();
        $pivot->setForeignKey('user_id');
        $pivot->setRelatedKey('role_id');
        $this->assertEquals('user_id', $pivot->getForeignKey());
        $this->assertEquals('role_id', $pivot->getRelatedKey());
    }

    // === withPivot ===

    public function testBelongsToManyWithPivot(): void
    {
        $user = PvUser::find(1);
        $roles = $user->roles()->withPivot('note')->get();

        $this->assertEquals(2, $roles->count());

        $admin = $roles->first(fn($r) => $r->name === 'Admin');
        $this->assertNotNull($admin);
        $pivot = $admin->getRelation('pivot');
        $this->assertNotNull($pivot);
        $this->assertEquals('primary admin', $pivot->note ?? null);
    }

    // === attach / detach ===

    public function testAttach(): void
    {
        $user = PvUser::find(2);
        $result = $user->roles()->attach(1, ['note' => 'new admin']);

        $roles = $user->roles()->get();
        $this->assertEquals(2, $roles->count());
    }

    public function testDetach(): void
    {
        $user = PvUser::find(1);
        $count = $user->roles()->detach(2);
        $this->assertEquals(1, $count);

        $roles = $user->roles()->get();
        $this->assertEquals(1, $roles->count());
    }

    // === sync ===

    public function testSync(): void
    {
        $user = PvUser::find(1);
        $result = $user->roles()->sync([2]); // 只保留 role_id=2

        $this->assertContains(1, $result['detached']);
        $this->assertEmpty($result['attached']);

        $roles = $user->roles()->get();
        $this->assertEquals(1, $roles->count());
        $this->assertEquals('Editor', $roles->first()->name);
    }
}

// === 测试模型 ===

class PvUser extends Model
{
    protected string $table = 'pv_users';
    protected array $guarded = [];
    protected bool $timestamps = false;

    public function roles(): \Bin\Database\Relations\BelongsToMany
    {
        return $this->belongsToMany(PvRole::class, 'pv_role_user', 'user_id', 'role_id');
    }
}

class PvRole extends Model
{
    protected string $table = 'pv_roles';
    protected array $guarded = [];
    protected bool $timestamps = false;
}

return new PivotModelTest();
