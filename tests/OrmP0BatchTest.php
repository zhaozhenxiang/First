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
        $this->pdo->exec('CREATE TABLE p0_comments (id INTEGER PRIMARY KEY, post_id INTEGER, body TEXT)');
        $this->pdo->exec('CREATE TABLE p0_notes (id INTEGER PRIMARY KEY, notable_type TEXT, notable_id INTEGER, content TEXT, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE p0_roles (id INTEGER PRIMARY KEY, name TEXT)');
        $this->pdo->exec('CREATE TABLE p0_role_user (role_id INTEGER, user_id INTEGER, note TEXT)');
        $this->pdo->exec('CREATE TABLE p0_json_items (id INTEGER PRIMARY KEY, tags TEXT, meta TEXT)');

        P0User::resetBooted();
        P0Post::resetBooted();
        P0SoftPost::resetBooted();
        P0JsonItem::resetBooted();
        Model::setConnection($this->pdo);
    }

    protected function tearDown(): void
    {
        P0User::flushEventListeners();
        P0Post::flushEventListeners();
        P0SoftPost::flushEventListeners();
        P0JsonItem::flushEventListeners();
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
        P0User::creating(function (P0User $user) use (&$fired): void {
            $fired++;
        });
        P0User::saved(function (P0User $user) use (&$fired): void {
            $fired++;
        });

        // 对照：普通保存触发事件（防止计数器按值捕获导致的假阳性）
        $control = new P0User(['name' => 'c', 'email' => 'c@x.com']);
        $control->save();
        $this->assertSame(2, $fired);
        $fired = 0;

        $user = new P0User(['name' => 'q', 'email' => 'q@x.com']);
        $this->assertTrue($user->saveQuietly());

        $this->assertSame(0, $fired);
        $this->assertTrue($user->exists);
        $this->assertSame(2, P0User::count());
    }

    public function testDeleteQuietlySkipsModelEvents(): void
    {
        $fired = 0;
        P0User::deleted(function (P0User $user) use (&$fired): void {
            $fired++;
        });

        // 对照：普通删除触发事件
        $control = P0User::create(['name' => 'c', 'email' => 'c@x.com']);
        $fired = 0;
        $control->delete();
        $this->assertSame(1, $fired);
        $fired = 0;

        $user = P0User::create(['name' => 'd', 'email' => 'd@x.com']);
        $fired = 0;

        $this->assertTrue($user->deleteQuietly());
        $this->assertSame(0, $fired);
        $this->assertSame(0, P0User::count());
    }

    public function testRestoreAndForceDeleteQuietlyOnSoftDeletes(): void
    {
        $fired = 0;
        P0SoftPost::restoring(function (P0SoftPost $post) use (&$fired): void {
            $fired++;
        });
        P0SoftPost::restored(function (P0SoftPost $post) use (&$fired): void {
            $fired++;
        });
        P0SoftPost::deleted(function (P0SoftPost $post) use (&$fired): void {
            $fired++;
        });

        $post = P0SoftPost::create(['title' => 't']);
        $fired = 0;

        // 对照：普通软删/恢复触发事件
        $post->delete();
        $this->assertSame(1, $fired);
        $post->restore();
        $this->assertSame(3, $fired);
        $fired = 0;

        // 未软删时 restore（含静默版）返回 false
        $this->assertFalse($post->restoreQuietly());
        $this->assertSame(0, $fired);

        $post->deleteQuietly();
        $this->assertSame(0, $fired);
        $this->assertTrue($post->restoreQuietly());
        $this->assertSame(0, $fired);
        $this->assertFalse($post->trashed());

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

        // 对照：无映射子类的默认 saved 监听正常触发
        $plainFired = 0;
        P0UserPlain::saved(function (P0UserPlain $user) use (&$plainFired): void {
            $plainFired++;
        });
        P0UserPlain::create(['name' => 'p', 'email' => 'p@x.com']);
        $this->assertSame(1, $plainFired);

        // 有映射子类：只发自定义事件，默认监听不触发
        $defaultFired = 0;
        P0UserWithEvents::saved(function (P0UserWithEvents $user) use (&$defaultFired): void {
            $defaultFired++;
        });

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

    // ============================
    // 阶段C：关系层
    // ============================

    public function testHasFamilyFiltersByRelationExistence(): void
    {
        $u1 = P0User::create(['name' => 'u1', 'email' => 'u1@x.com']);
        $u2 = P0User::create(['name' => 'u2', 'email' => 'u2@x.com']);
        $u3 = P0User::create(['name' => 'u3', 'email' => 'u3@x.com']);

        P0Post::create(['user_id' => $u1->id, 'title' => 'p1']);
        P0Post::create(['user_id' => $u1->id, 'title' => 'p2']);
        P0Post::create(['user_id' => $u2->id, 'title' => 'p3']);

        $this->assertSame([$u1->id, $u2->id], P0User::query()->has('posts')->orderBy('id')->pluck('id'));
        $this->assertSame([$u1->id], P0User::query()->has('posts', '>=', 2)->pluck('id'));
        $this->assertSame([$u2->id, $u3->id], P0User::query()->has('posts', '<', 2)->orderBy('id')->pluck('id'));
        $this->assertSame([$u3->id], P0User::query()->doesntHave('posts')->pluck('id'));
        $this->assertSame([$u1->id], P0User::query()->where('name', 'zz')->orHas('posts', '>=', 2)->pluck('id'));
        $this->assertSame([$u1->id, $u3->id], P0User::query()->where('name', 'u1')->orDoesntHave('posts')->orderBy('id')->pluck('id'));
    }

    public function testNestedHasWithDotNotation(): void
    {
        $u1 = P0User::create(['name' => 'u1', 'email' => 'u1@x.com']);
        $u2 = P0User::create(['name' => 'u2', 'email' => 'u2@x.com']);

        $post1 = P0Post::create(['user_id' => $u1->id, 'title' => 'p1']);
        P0Post::create(['user_id' => $u2->id, 'title' => 'p2']);

        $this->pdo->exec("INSERT INTO p0_comments (post_id, body) VALUES ({$post1->id}, 'c1'), ({$post1->id}, 'c2')");

        $this->assertSame([$u1->id], P0User::query()->has('posts.comments')->pluck('id'));
        $this->assertSame([$u1->id], P0User::query()->has('posts.comments', '>=', 2)->pluck('id'));
        $this->assertSame([$u1->id], P0User::query()->whereHas('posts.comments')->pluck('id'));
    }

    public function testHasManyRelationCreateWorks(): void
    {
        // 回归：$related 此前从未赋值，create() 必然抛未初始化属性错误
        $user = P0User::create(['name' => 'u', 'email' => 'u@x.com']);

        $post = $user->posts()->create(['title' => 'via-relation']);

        $this->assertInstanceOf(P0Post::class, $post);
        $this->assertNotNull($post->id);
        $this->assertSame($user->id, $post->user_id);
        $this->assertSame(1, $user->posts()->count());
    }

    public function testRelationMakeWiresForeignKeyWithoutSaving(): void
    {
        $user = P0User::create(['name' => 'u', 'email' => 'u@x.com']);

        $post = $user->posts()->make(['title' => 'draft']);

        $this->assertInstanceOf(P0Post::class, $post);
        $this->assertSame($user->id, $post->user_id);
        $this->assertFalse($post->exists);
        $this->assertSame(0, P0Post::count());

        $post->save();
        $this->assertSame(1, P0Post::count());
    }

    public function testBelongsToManyMakeReturnsUnsavedModel(): void
    {
        $user = P0User::create(['name' => 'u', 'email' => 'u@x.com']);

        $role = $user->roles()->make(['name' => 'editor']);

        $this->assertInstanceOf(P0Role::class, $role);
        $this->assertSame('editor', $role->name);
        $this->assertFalse($role->exists);
        $this->assertSame(0, P0Role::count());
    }

    public function testMorphManyMakeWiresTypeAndKey(): void
    {
        $user = P0User::create(['name' => 'u', 'email' => 'u@x.com']);
        $post = P0Post::create(['user_id' => $user->id, 'title' => 'p']);

        $note = $post->notes()->make(['content' => 'hi']);

        $this->assertInstanceOf(P0Note::class, $note);
        $this->assertSame(P0Post::class, $note->notable_type);
        $this->assertSame($post->id, $note->notable_id);
        $this->assertFalse($note->exists);
    }

    public function testMorphWhereHasAndWithCountIsolateByType(): void
    {
        $user = P0User::create(['name' => 'u', 'email' => 'u@x.com']);
        $post1 = P0Post::create(['user_id' => $user->id, 'title' => 'p1']);
        $post2 = P0Post::create(['user_id' => $user->id, 'title' => 'p2']);

        // 同一 notable_id=1：一条属于 post1，一条属于 user（id 恰为 1）
        // 回归前 whereHas 走通用回退只比对 morphId，user 的 note 会泄漏进 post 的查询
        $this->pdo->exec("INSERT INTO p0_notes (notable_type, notable_id, content) VALUES ('" . P0Post::class . "', {$post1->id}, 'for-post'), ('" . P0User::class . "', {$user->id}, 'for-user')");

        $this->assertSame([$post1->id], P0Post::query()->whereHas('notes')->pluck('id'));

        // 回归前 withCount 走 Relation 基类占位实现直接抛 BadMethodCallException
        $counted = P0Post::query()->withCount('notes')->orderBy('id')->get();
        $this->assertSame(1, (int) $counted[0]->notes_count);
        $this->assertSame(0, (int) $counted[1]->notes_count);
    }

    public function testSyncToggleAndPivotValueFamily(): void
    {
        $user = P0User::create(['name' => 'u', 'email' => 'u@x.com']);
        $this->pdo->exec("INSERT INTO p0_roles (id, name) VALUES (1, 'admin'), (2, 'editor'), (3, 'viewer'), (4, 'bot'), (5, 'guest')");

        $relation = $user->roles();

        // 基础同步：附加 1、2
        $result = $relation->sync([1, 2]);
        $this->assertSame([1, 2], $result['attached']);
        $this->assertSame(2, $relation->count());

        // 映射形式：去 1、留 2 且写附加列、新增 3
        $result = $relation->sync([2 => ['note' => 'n2'], 3]);
        $this->assertSame([1], $result['detached']);
        $this->assertSame([3], $result['attached']);
        $this->assertSame([2], $result['updated']);

        $pivotNote = fn (int $id) => $this->pdo->query("SELECT note FROM p0_role_user WHERE role_id = {$id} AND user_id = {$user->id}")->fetchColumn();
        $this->assertSame('n2', $pivotNote(2));

        // toggle：2 已存在被移除，4 不存在被附加
        $result = $relation->toggle([2, 4]);
        $this->assertSame([2], $result['detached']);
        $this->assertSame([4], $result['attached']);

        // syncWithoutDetaching：只增不删
        $result = $relation->syncWithoutDetaching([5]);
        $this->assertSame([5], $result['attached']);
        $this->assertSame([], $result['detached']);
        $this->assertSame(3, $relation->count());

        // syncWithPivotValues：为关联统一写入附加列
        $result = $relation->syncWithPivotValues([3], ['note' => 'z3']);
        $this->assertSame([3], $result['updated']);
        $this->assertSame('z3', $pivotNote(3));
    }

    // ============================
    // 阶段10C：morph 写方法与 JSON 子句
    // ============================

    public function testMorphRelationCreateAndSave(): void
    {
        $user = P0User::create(['name' => 'u', 'email' => 'u@x.com']);
        $post = P0Post::create(['user_id' => $user->id, 'title' => 'p']);

        // create：morphId/morphType 接线并落库
        $note = $post->notes()->create(['content' => 'n1']);

        $this->assertInstanceOf(P0Note::class, $note);
        $this->assertNotNull($note->id);
        $this->assertSame($post->id, $note->notable_id);
        $this->assertSame(P0Post::class, $note->notable_type);
        $this->assertSame(1, P0Note::count());

        // saveMany：已有实例接线后保存
        $extra = new P0Note(['content' => 'n2']);
        $post->notes()->saveMany([new P0Note(['content' => 'n3']), $extra]);

        $this->assertSame(3, P0Note::count());
        $this->assertSame(P0Post::class, $extra->notable_type);

        // createMany
        $post->notes()->createMany([['content' => 'n4'], ['content' => 'n5']]);
        $this->assertSame(5, P0Note::count());
    }

    public function testWhereJsonContainsFiltersScalarAndArrayValues(): void
    {
        $insert = $this->pdo->prepare('INSERT INTO p0_json_items (tags, meta) VALUES (?, ?)');
        $insert->execute(['["php", "orm"]', '{"langs": ["en", "zh"]}']);
        $insert->execute(['["go", "orm"]', '{"langs": ["en"]}']);
        $insert->execute(['["rust"]', '{"langs": []}']);

        // 标量包含
        $this->assertSame([1, 2], P0JsonItem::query()->whereJsonContains('tags', 'orm')->orderBy('id')->pluck('id'));

        // 数组值 = 全部包含（ALL 语义）
        $this->assertSame([1], P0JsonItem::query()->whereJsonContains('tags', ['php', 'orm'])->pluck('id'));

        // 不包含
        $this->assertSame([3], P0JsonItem::query()->whereJsonDoesntContain('tags', 'orm')->pluck('id'));

        // JSON 路径 col->key
        $this->assertSame([1, 2], P0JsonItem::query()->whereJsonContains('meta->langs', 'en')->orderBy('id')->pluck('id'));
        $this->assertSame([1], P0JsonItem::query()->whereJsonContains('meta->langs', 'zh')->pluck('id'));

        // 空数组包含恒假 / 不包含恒真
        $this->assertSame(0, P0JsonItem::query()->whereJsonContains('tags', [])->count());
        $this->assertSame(3, P0JsonItem::query()->whereJsonDoesntContain('tags', [])->count());
    }
}

class P0User extends Model
{
    protected string $table = 'p0_users';

    protected array $fillable = ['name', 'email'];

    public function posts()
    {
        return $this->hasMany(P0Post::class, 'user_id');
    }

    public function roles()
    {
        return $this->belongsToMany(P0Role::class, 'p0_role_user', 'user_id', 'role_id');
    }

    public function notes()
    {
        return $this->morphMany(P0Note::class, 'notable');
    }
}

class P0UserWithEvents extends P0User
{
    protected array $dispatchesEvents = ['saved' => P0UserSavedEvent::class];
}

class P0UserPlain extends P0User
{
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

    public function comments()
    {
        return $this->hasMany(P0Comment::class, 'post_id');
    }

    public function notes()
    {
        return $this->morphMany(P0Note::class, 'notable');
    }
}

class P0Comment extends Model
{
    protected string $table = 'p0_comments';

    protected array $fillable = ['post_id', 'body'];
}

class P0Note extends Model
{
    protected string $table = 'p0_notes';

    protected array $fillable = ['notable_type', 'notable_id', 'content'];

    public function notable()
    {
        return $this->morphTo();
    }
}

class P0Role extends Model
{
    protected string $table = 'p0_roles';

    protected array $fillable = ['name'];
}

class P0JsonItem extends Model
{
    protected string $table = 'p0_json_items';

    public bool $timestamps = false;
}
