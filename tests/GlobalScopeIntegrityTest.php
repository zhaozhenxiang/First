<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Database\Model;
use Bin\Database\QueryBuilder;
use Bin\Database\SoftDeletes;

/**
 * 全局作用域完整性回归测试
 *
 * 此前 update()/delete()/increment() 完全绕过全局作用域，
 * 软删除模型可以被查询级 delete 物理删除。
 */
class GlobalScopeIntegrityTest extends TestCase
{
    protected \PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('
            CREATE TABLE scope_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                votes INTEGER DEFAULT 0,
                tenant_id INTEGER DEFAULT 1,
                deleted_at TEXT,
                created_at TEXT,
                updated_at TEXT
            )
        ');
        $this->pdo->exec("INSERT INTO scope_users (name, votes) VALUES ('a', 1), ('b', 2)");

        ScopeUser::resetBooted();
        Model::setConnection($this->pdo);
    }

    protected function tearDown(): void
    {
        ScopeUser::flushEventListeners();
        Model::setConnection(null);
    }

    public function testUpdateAppliesGlobalScopes(): void
    {
        ScopeUser::addGlobalScope('tenant', fn (QueryBuilder $q) => $q->where('tenant_id', '=', 1));

        $this->pdo->exec("UPDATE scope_users SET tenant_id = 2 WHERE name = 'b'");

        // 只应影响 tenant_id=1 的行
        ScopeUser::query()->update(['name' => 'renamed']);

        $rows = $this->pdo->query('SELECT name FROM scope_users ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame(['renamed', 'b'], $rows);
    }

    public function testQueryDeleteOnSoftDeleteModelSoftDeletes(): void
    {
        ScopeUser::query();

        $user = ScopeUser::find(1);
        $user->delete(); // 软删

        // 查询级 delete 应转软删 UPDATE，且不触碰已软删的行
        ScopeUser::where('id', '>', 0)->delete();

        $rows = $this->pdo->query('SELECT id, deleted_at FROM scope_users ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertCount(2, $rows); // 物理行都在
        $this->assertNotNull($rows[0]['deleted_at']);
        $this->assertNotNull($rows[1]['deleted_at']);

        // 默认查询看不到软删行
        $this->assertSame(0, ScopeUser::count());
    }

    public function testWithTrashedDeleteHardDeletes(): void
    {
        ScopeUser::query();

        ScopeUser::find(1)->delete();
        ScopeUser::withTrashed()->where('id', 1)->delete();

        $left = $this->pdo->query('SELECT COUNT(*) FROM scope_users')->fetchColumn();
        $this->assertSame(1, (int) $left);
    }

    public function testReusedBuilderDoesNotStackScopeConditions(): void
    {
        $q = (new QueryBuilder($this->pdo))->from('scope_users')
            ->withGlobalScope('flag', fn (QueryBuilder $qb) => $qb->where('votes', '>', 0));

        $q->get();
        $q->get(); // 复用不再叠加

        $wheres = (fn () => $this->wheres)->call($q);
        $this->assertCount(1, $wheres);
    }

    public function testChunkByIdDoesNotPolluteOriginalBuilder(): void
    {
        $for = (new QueryBuilder($this->pdo))->from('scope_users')->orderBy('name', 'desc');

        $seen = 0;
        $for->chunkById(1, function () use (&$seen) {
            $seen++;
        });

        $this->assertSame(2, $seen);
        // 原查询的 where 不应被污染（只剩调用方自己的 0 条）
        $wheres = (fn () => $this->wheres)->call($for);
        $this->assertCount(0, $wheres);
    }

    public function testInstanceIncrementOnlyTouchesItsRow(): void
    {
        ScopeUser::query();

        $user = ScopeUser::find(1);
        $user->increment('votes');

        $votes = $this->pdo->query('SELECT votes FROM scope_users ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame([2, 2], array_map('intval', $votes));

        // 内存属性已同步且为干净状态
        $this->assertSame(2, (int) $user->votes);
        $this->assertFalse($user->isDirty('votes'));
    }
}

class ScopeUser extends Model
{
    use SoftDeletes;

    protected string $table = 'scope_users';
    protected array $fillable = ['name', 'votes', 'tenant_id'];
}
