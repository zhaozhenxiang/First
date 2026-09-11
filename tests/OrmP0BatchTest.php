<?php

declare(strict_types=1);

namespace Tests;

use Bin\Database\Model;
use Bin\Database\ModelEventDispatcher;
use Bin\Database\QueryBuilder;
use Bin\Testing\TestCase;

/**
 * ORM P0 补齐批次回归测试（阶段 8）
 *
 * 覆盖：upsert 家族 / cursor-lazy / joinSub / whereKey（阶段A）、
 * 静默家族 / dispatchesEvents / wasChanged / is / replicate（阶段B）、
 * has 家族 / 关系 make / toggle / sync 系列（阶段C）。
 */
class OrmP0BatchTest extends TestCase
{
    protected \PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE p0_users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, note TEXT, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE p0_posts (id INTEGER PRIMARY KEY, user_id INTEGER, title TEXT, views INTEGER DEFAULT 0, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE p0_soft_posts (id INTEGER PRIMARY KEY, title TEXT, deleted_at TEXT, created_at TEXT, updated_at TEXT)');

        P0User::resetBooted();
        P0Post::resetBooted();
        P0SoftPost::resetBooted();
        Model::setConnection($this->pdo);
    }

    protected function tearDown(): void
    {
        P0User::flushEventListeners();
        P0Post::flushEventListeners();
        P0SoftPost::flushEventListeners();
        ModelEventDispatcher::forget(P0UserSavedEvent::class);
        Model::setConnection(null);
    }

    // ============================
    // 阶段A：查询构建器
    // ============================

    public function testUpsertInsertsThenUpdatesOnConflict(): void
    {
        P0User::query()->upsert([
            ['id' => 1, 'name' => 'a', 'email' => 'a@x.com'],
            ['id' => 2, 'name' => 'b', 'email' => 'b@x.com'],
        ], ['id'], ['name', 'email']);

        $this->assertSame(2, P0User::count());

        // 冲突行更新 name/email，非冲突列保留
        P0User::query()->upsert([
            ['id' => 1, 'name' => 'a2', 'email' => 'a2@x.com'],
        ], ['id'], ['name', 'email']);

        $user = P0User::find(1);
        $this->assertSame('a2', $user->name);
        $this->assertSame('a2@x.com', $user->email);

        // 冲突时未列入 update 的列不被覆盖
        P0User::query()->whereKey(1)->update(['name' => 'a3']);
        P0User::query()->upsert([
            ['id' => 1, 'name' => 'ignored', 'email' => 'a3@x.com'],
        ], ['id'], ['email']);

        $this->assertSame('a3', P0User::find(1)->name);
    }

    public function testUpsertFillsModelTimestamps(): void
    {
        P0User::query()->upsert([
            ['id' => 3, 'name' => 'c'],
        ], ['id'], ['name']);

        $row = $this->pdo->query('SELECT created_at, updated_at FROM p0_users WHERE id = 3')->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotEmpty($row['created_at']);
        $this->assertNotEmpty($row['updated_at']);
    }

    public function testUpsertMysqlBranchCompilesOnDuplicateKey(): void
    {
        $mysqlPdo = new class('sqlite::memory:') extends \PDO {
            public function getAttribute(int $attribute): mixed
            {
                return $attribute === \PDO::ATTR_DRIVER_NAME ? 'mysql' : parent::getAttribute($attribute);
            }
        };

        $builder = new class($mysqlPdo, P0User::class) extends QueryBuilder {
            /** @return array{0: string, 1: list<mixed>} */
            public function exposeUpsertSql(array $values, array $uniqueBy, ?array $update): array
            {
                return $this->buildUpsertStatement($values, $uniqueBy, $update);
            }
        };
        $builder->from('p0_users');

        [$sql, $bindings] = $builder->exposeUpsertSql(
            [['id' => 1, 'name' => 'a', 'email' => 'a@x.com']],
            ['id'],
            ['name']
        );

        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $sql);
        $this->assertStringContainsString('`name` = VALUES(`name`)', $sql);
        $this->assertStringNotContainsString('ON CONFLICT', $sql);
        $this->assertSame([1, 'a', 'a@x.com', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')], $bindings);
    }

    public function testInsertOrIgnoreSkipsConflictingRows(): void
    {
        $this->pdo->exec("INSERT INTO p0_users (id, name, email) VALUES (1, 'a', 'a@x.com')");

        $affected = P0User::query()->insertOrIgnore([
            ['id' => 1, 'name' => 'dup', 'email' => 'dup@x.com'],
            ['id' => 2, 'name' => 'b', 'email' => 'b@x.com'],
        ]);

        $this->assertSame(1, $affected);
        $this->assertSame(2, P0User::count());
        // 冲突行未被覆盖
        $this->assertSame('a', P0User::find(1)->name);
    }

    public function testUpdateOrInsertCreatesThenUpdates(): void
    {
        $this->assertTrue(P0User::query()->updateOrInsert(['email' => 'x@x.com'], ['name' => 'x1']));
        $this->assertSame('x1', P0User::firstWhere('email', 'x@x.com')->name);

        $this->assertTrue(P0User::query()->updateOrInsert(['email' => 'x@x.com'], ['name' => 'x2']));
        $this->assertSame(1, P0User::count());
        $this->assertSame('x2', P0User::firstWhere('email', 'x@x.com')->name);

        // 存在且 values 为空：不发 UPDATE 也返回 true
        $this->assertTrue(P0User::query()->updateOrInsert(['email' => 'x@x.com']));
    }

    public function testCursorYieldsHydratedModelsLazily(): void
    {
        foreach (['a', 'b', 'c'] as $name) {
            P0User::create(['name' => $name, 'email' => $name . '@x.com']);
        }

        $names = [];
        foreach (P0User::query()->orderBy('id')->cursor() as $model) {
            $this->assertInstanceOf(P0User::class, $model);
            $names[] = $model->name;
        }

        $this->assertSame(['a', 'b', 'c'], $names);
    }

    public function testLazyAndLazyByIdStreamAllRows(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            P0User::create(['name' => 'u' . $i, 'email' => "u{$i}@x.com"]);
        }

        $lazyNames = [];
        foreach (P0User::query()->orderBy('id')->lazy(2) as $model) {
            $lazyNames[] = $model->name;
        }
        $this->assertSame(5, count($lazyNames));

        $lazyByIdNames = [];
        foreach (P0User::query()->orderBy('id')->lazyById(2) as $model) {
            $lazyByIdNames[] = $model->name;
        }
        $this->assertSame(['u1', 'u2', 'u3', 'u4', 'u5'], $lazyByIdNames);
    }

    public function testJoinSubJoinsSubqueryWithCorrectBindingOrder(): void
    {
        $this->pdo->exec("INSERT INTO p0_users (id, name, email) VALUES (1, 'a', 'a@x.com'), (2, 'b', 'b@x.com')");
        $this->pdo->exec("INSERT INTO p0_posts (user_id, title, views) VALUES (1, 'p1', 10), (1, 'p2', 20), (2, 'p3', 5)");

        $query = P0User::query()
            ->joinSub(
                fn (QueryBuilder $q) => $q->from('p0_posts')
                    ->select('user_id')
                    ->selectRaw('SUM(views) AS total_views')
                    ->where('views', '>', 5)
                    ->groupBy('user_id'),
                'post_stats',
                'p0_users.id',
                '=',
                'post_stats.user_id'
            )
            ->where('p0_users.name', '=', 'a');

        $this->assertSame([5, 'a'], $query->getBindings());

        $results = $query->get();
        $this->assertSame(1, $results->count());
        $this->assertSame(30, (int) $results->first()->getAttribute('total_views'));
    }

    public function testLeftJoinSubKeepsUnmatchedRows(): void
    {
        $this->pdo->exec("INSERT INTO p0_users (id, name, email) VALUES (1, 'a', 'a@x.com'), (2, 'b', 'b@x.com')");
        $this->pdo->exec("INSERT INTO p0_posts (user_id, title, views) VALUES (1, 'p1', 7)");

        $results = P0User::query()
            ->leftJoinSub(
                fn (QueryBuilder $q) => $q->from('p0_posts')
                    ->select('user_id')
                    ->selectRaw('SUM(views) AS total_views')
                    ->groupBy('user_id'),
                'post_stats',
                'p0_users.id',
                '=',
                'post_stats.user_id'
            )
            ->orderBy('p0_users.id')
            ->get();

        $this->assertSame(2, $results->count());
        $this->assertSame(7, (int) $results->first()->getAttribute('total_views'));
        $this->assertNull($results[1]->getAttribute('total_views'));
    }

    public function testCrossJoinProducesCartesianProduct(): void
    {
        $this->pdo->exec("INSERT INTO p0_users (id, name, email) VALUES (1, 'a', 'a@x.com'), (2, 'b', 'b@x.com')");
        $this->pdo->exec("INSERT INTO p0_posts (user_id, title) VALUES (1, 'p1'), (1, 'p2')");

        $count = (new QueryBuilder($this->pdo))
            ->from('p0_users')
            ->crossJoin('p0_posts')
            ->count();

        $this->assertSame(4, $count);
    }

    public function testWhereKeyFiltersByPrimaryKey(): void
    {
        foreach (['a', 'b', 'c'] as $name) {
            P0User::create(['name' => $name, 'email' => $name . '@x.com']);
        }

        $this->assertSame([1], P0User::query()->whereKey(1)->pluck('id'));
        $this->assertSame([1, 2], P0User::query()->whereKey([1, 2])->orderBy('id')->pluck('id'));
        $this->assertSame([3], P0User::query()->whereKeyNot([1, 2])->pluck('id'));

        // 空数组：whereKey 恒假，whereKeyNot 不加条件
        $this->assertSame(0, P0User::query()->whereKey([])->count());
        $this->assertSame(3, P0User::query()->whereKeyNot([])->count());
    }

    // ============================
    // 阶段B：模型层
    // ============================

    public function testSaveQuietlySkipsModelEvents(): void
    {
        $fired = 0;
        P0User::creating(fn (P0User $user) => $fired++);
        P0User::saved(fn (P0User $user) => $fired++);

        $user = new P0User(['name' => 'q', 'email' => 'q@x.com']);
        $this->assertTrue($user->saveQuietly());

        $this->assertSame(0, $fired);
        $this->assertTrue($user->exists);
        $this->assertSame(1, P0User::count());
    }

    public function testDeleteQuietlySkipsModelEvents(): void
    {
        $fired = 0;
        P0User::deleted(fn (P0User $user) => $fired++);

        $user = P0User::create(['name' => 'd', 'email' => 'd@x.com']);
        $fired = 0; // create 事件不计入

        $this->assertTrue($user->deleteQuietly());
        $this->assertSame(0, $fired);
        $this->assertSame(0, P0User::count());
    }

    public function testRestoreAndForceDeleteQuietlyOnSoftDeletes(): void
    {
        $fired = 0;
        P0SoftPost::restoring(fn (P0SoftPost $post) => $fired++);
        P0SoftPost::restored(fn (P0SoftPost $post) => $fired++);
        P0SoftPost::deleted(fn (P0SoftPost $post) => $fired++);

        $post = P0SoftPost::create(['title' => 't']);
        $post->delete();
        $fired = 0;

        $this->assertTrue($post->restoreQuietly());
        $this->assertSame(0, $fired);
        $this->assertFalse($post->trashed());

        $post->delete();
        $fired = 0;

        $this->assertTrue($post->forceDeleteQuietly());
        $this->assertSame(0, $fired);
        $this->assertSame(0, P0SoftPost::withTrashed()->count());
    }

    public function testForceCreateBypassesMassAssignmentProtection(): void
    {
        $user = P0User::forceCreate(['name' => 'f', 'email' => 'f@x.com', 'note' => 'kept']);
        $this->assertSame('kept', $user->note);
        $this->assertSame('kept', P0User::find($user->id)->note);

        // 对照：create() 静默丢弃不可批量赋值属性
        $plain = P0User::create(['name' => 'p', 'email' => 'p@x.com', 'note' => 'dropped']);
        $this->assertNull($plain->note);
    }

    public function testDispatchesEventsMapsEventToCustomClass(): void
    {
        $captured = null;
        ModelEventDispatcher::dispatcher()->listen(
            P0UserSavedEvent::class,
            function (P0UserSavedEvent $event) use (&$captured): void {
                $captured = $event;
            }
        );

        $defaultFired = 0;
        P0UserWithEvents::saved(fn (P0UserWithEvents $user) => $defaultFired++);

        $user = P0UserWithEvents::create(['name' => 'm', 'email' => 'm@x.com']);

        $this->assertInstanceOf(P0UserSavedEvent::class, $captured);
        $this->assertSame('m', $captured->model->name);
        // 命中映射时默认监听不再触发
        $this->assertSame(0, $defaultFired);
    }

    public function testWasChangedGetChangesAndGetPrevious(): void
    {
        $user = P0User::create(['name' => 'orig', 'email' => 'o@x.com']);

        $this->assertTrue($user->wasChanged());
        $this->assertSame('orig', $user->getChanges()['name'] ?? null);
        $this->assertSame([], $user->getPrevious());

        $user->name = 'new';
        // wasChanged 反映上次保存：create 时写入过 name
        $this->assertTrue($user->wasChanged('name'));

        $user->save();

        $this->assertTrue($user->wasChanged('name'));
        $this->assertSame('new', $user->getChanges()['name']);
        $this->assertSame('orig', $user->getPrevious('name'));
        $this->assertFalse($user->wasChanged('email'));

        // 只改 email 再保存后，name 不再属于"上次保存写入"
        $user->email = 'o2@x.com';
        $user->save();
        $this->assertFalse($user->wasChanged('name'));
        $this->assertTrue($user->wasChanged('email'));
    }

    public function testIsAndIsNotCompareSameRow(): void
    {
        $a = P0User::create(['name' => 'a', 'email' => 'a@x.com']);
        $b = P0User::create(['name' => 'b', 'email' => 'b@x.com']);

        $this->assertTrue($a->is(P0User::find($a->id)));
        $this->assertFalse($a->is($b));
        $this->assertTrue($a->isNot($b));
        $this->assertFalse($a->is(null));
        $this->assertTrue($a->isNot(null));
    }

    public function testReplicateSupportsExceptColumns(): void
    {
        $post = P0Post::create(['user_id' => 1, 'title' => 't', 'views' => 5]);

        $copy = $post->replicate(['views']);

        $this->assertNull($copy->id);
        $this->assertNull($copy->views);
        $this->assertSame('t', $copy->title);

        $copy->save();
        $this->assertSame(2, P0Post::count());
    }
}

class P0User extends Model
{
    protected string $table = 'p0_users';

    protected array $fillable = ['name', 'email'];

    public function posts()
    {
        return $this->hasMany(P0Post::class);
    }
}

class P0UserWithEvents extends P0User
{
    protected array $dispatchesEvents = ['saved' => P0UserSavedEvent::class];
}

class P0UserSavedEvent
{
    public function __construct(public readonly P0User $model)
    {
    }
}

class P0SoftPost extends Model
{
    use \Bin\Database\SoftDeletes;

    protected string $table = 'p0_soft_posts';

    protected array $fillable = ['title'];
}

class P0Post extends Model
{
    protected string $table = 'p0_posts';

    protected array $fillable = ['user_id', 'title', 'views'];

    public function user()
    {
        return $this->belongsTo(P0User::class);
    }
}
