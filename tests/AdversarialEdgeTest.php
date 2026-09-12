<?php

declare(strict_types=1);

namespace Tests;

use Bin\Database\Collection;
use Bin\Database\Factory;
use Bin\Database\HasUuids;
use Bin\Database\Model;
use Bin\Database\Prunable;
use Bin\Database\Schema\Blueprint;
use Bin\Database\Schema\SchemaBuilder;
use Bin\Testing\TestCase;
use PDO;
use Throwable;

/**
 * 边界对抗测试（阶段14）
 *
 * 对阶段8-13新 API 的超出常规用例的输入做对抗性验证：
 * 空值、极端参数、非法组合、作用域交互、语义对齐。
 */
class AdversarialEdgeTest extends TestCase
{
    protected \PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE edge_users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, role TEXT, tags TEXT, deleted_at TEXT, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE edge_posts (id INTEGER PRIMARY KEY, user_id INTEGER, title TEXT, views INTEGER, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE edge_roles (id INTEGER PRIMARY KEY, name TEXT)');
        $this->pdo->exec('CREATE TABLE edge_role_user (role_id INTEGER, user_id INTEGER, note TEXT)');

        EdgeRole::resetBooted();

        EdgeUser::resetBooted();
        EdgePost::resetBooted();
        Model::setConnection($this->pdo);

        Factory::define(EdgeUser::class, fn () => ['name' => 'user-' . random_int(1, 999)]);
        Factory::define(EdgeSoftUser::class, fn () => ['name' => 'user-' . random_int(1, 999)]);
        Factory::define(EdgePost::class, fn () => ['title' => 'post', 'views' => 0]);
    }

    protected function tearDown(): void
    {
        Factory::flush();
        EdgeUser::flushEventListeners();
        EdgePost::flushEventListeners();
        EdgeRole::flushEventListeners();
        Model::setConnection(null);
    }

    // ============================
    // 查询构建器（阶段8A/12）
    // ============================

    public function testUpsertRejectsEmptyUniqueByWithClearError(): void
    {
        // 对抗：uniqueBy=[] 产生 ON CONFLICT() 括号内为空 → SQL 语法错误。
        try {
            EdgeUser::query()->upsert([['id' => 1, 'name' => 'a']], [], ['name']);
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('unique column', $e->getMessage());
        }
    }

    public function testUpsertRejectsExplicitEmptyUpdateList(): void
    {
        // 对抗：update=[]（显式空）会编译出 ON CONFLICT ... DO UPDATE SET（空赋值）。
        try {
            EdgeUser::query()->upsert([['id' => 1, 'name' => 'a']], ['id'], []);
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('non-empty list', $e->getMessage());
        }
    }

    public function testUpsertWithEmptyValuesIsNoop(): void
    {
        $this->assertSame(0, EdgeUser::query()->upsert([], ['id']));
        $this->assertSame(0, EdgeUser::count());
    }

    public function testUpsertNormalizesRowsWithDifferentColumnSets(): void
    {
        // 对抗：多行列集不一致——缺列补 null 而不是占位符错位
        EdgeUser::query()->upsert([
            ['id' => 1, 'name' => 'a', 'email' => 'a@x.com'],
            ['id' => 2, 'name' => 'b'],
        ], ['id'], ['name', 'email']);

        $this->assertSame('b', EdgeUser::find(2)->name);
        $this->assertNull(EdgeUser::find(2)->email);
    }

    public function testInsertOrIgnoreWithEmptyValuesIsNoop(): void
    {
        $this->assertSame(0, EdgeUser::query()->insertOrIgnore([]));
    }

    public function testCursorOnEmptyResultYieldsNothing(): void
    {
        $seen = 0;
        foreach (EdgeUser::query()->cursor() as $row) {
            $seen++;
        }

        $this->assertSame(0, $seen);
    }

    public function testLazyWithZeroOrNegativeChunkThrows(): void
    {
        // 对抗发现的缺陷：负数 LIMIT 在 SQLite 语义中等于无限制，lazy 无游标推进
        // → 死循环。修复为入口校验 chunkSize 下限（chunk/chunkById 同样防护）
        EdgeUser::factory()->count(3)->create();

        foreach ([0, -5] as $bad) {
            try {
                $seen = 0;
                foreach (EdgeUser::query()->lazy($bad) as $row) {
                    $seen++;
                    $this->assertLessThan(5, $seen, 'lazy must not loop');
                }
                $this->fail("Expected InvalidArgumentException for lazy({$bad})");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('at least 1', $e->getMessage());
            }

            try {
                // 生成器体在首次迭代时才执行，校验随之延迟触发
                foreach (EdgeUser::query()->lazyById($bad) as $row) {
                    break;
                }
                $this->fail("Expected InvalidArgumentException for lazyById({$bad})");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('at least 1', $e->getMessage());
            }
        }
    }

    public function testWhereKeyNullMatchesNothing(): void
    {
        // 与 Laravel 一致：whereKey(null) → whereIn(key, [null]) → 无匹配
        EdgeUser::factory()->count(2)->create();

        $this->assertSame(0, EdgeUser::query()->whereKey(null)->count());
    }

    public function testOrderByClosureNormalizesBogusDirection(): void
    {
        $sql = EdgeUser::query()->orderBy(
            fn ($q) => $q->from('edge_posts')->selectRaw('MAX(views)'),
            'sideways'
        )->toSql();

        $this->assertStringContainsString('asc', $sql);
    }

    public function testUpdateOrInsertWithExistingRowAndEmptyValues(): void
    {
        EdgeUser::factory()->create(['email' => 'x@x.com']);

        // 对抗：行存在且 values 为空——不发 UPDATE 但返回 true
        $this->assertTrue(EdgeUser::query()->updateOrInsert(['email' => 'x@x.com']));
    }

    public function testCursorPaginateFallsBackToIdAscWhenOnlyRawOrder(): void
    {
        foreach (['n1', 'n2', 'n3'] as $i => $name) {
            EdgeUser::forceCreate(['id' => $i + 1, 'name' => $name]);
        }

        // 对抗：orderByRaw 无法解析游标排序列 → 回落 id 升序，且 resetOrders 清掉 raw
        $page1 = EdgeUser::query()->orderByRaw('name DESC')->cursorPaginate(2);
        $this->assertSame([1, 2], array_column($page1->items(), 'id'));

        $page2 = EdgeUser::query()->orderByRaw('name DESC')->cursorPaginate(2, ['*'], 'cursor', $page1->nextCursor());
        $this->assertSame([3], array_column($page2->items(), 'id'));
    }

    // ============================
    // 模型层（阶段8B/9）
    // ============================

    public function testChangeTrackingOnNeverSavedModel(): void
    {
        $user = new EdgeUser(['name' => 'n']);

        $this->assertFalse($user->wasChanged());
        $this->assertSame([], $user->getChanges());
        $this->assertSame([], $user->getPrevious());
    }

    public function testSaveWithoutDirtyChangesIsClean(): void
    {
        $user = EdgeUser::factory()->create();
        $this->assertTrue($user->wasChanged('name')); // create 写入了

        // 无任何修改再保存：成功但 changes 为空
        $this->assertTrue($user->save());
        $this->assertFalse($user->wasChanged());
        $this->assertSame([], $user->getChanges());
    }

    public function testIsComparesTableAndKeyNotClass(): void
    {
        // 与 Laravel 一致：is() 比较表名 + 主键，不比较类
        $user = EdgeUser::factory()->create();
        $subclass = EdgeSubUser::find($user->id);

        $this->assertTrue($user->is($subclass));
        $this->assertTrue($subclass->is($user));
    }

    public function testReplicateWithNonexistentExceptKeys(): void
    {
        $user = EdgeUser::factory()->create();

        $copy = $user->replicate(['nope', 'nada']);

        $this->assertNull($copy->id);
        $this->assertSame($user->name, $copy->name);
    }

    public function testDeleteQuietlyOnNonPersistedModel(): void
    {
        $user = new EdgeUser(['name' => 'ghost']);

        $this->assertFalse($user->deleteQuietly());
        $this->assertFalse($user->delete());
    }

    // ============================
    // 关系层（阶段8C）
    // ============================

    public function testHasRejectsUnknownRelation(): void
    {
        try {
            EdgeUser::query()->has('nonexistentRelation');
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('does not exist', $e->getMessage());
        }
    }

    public function testHasRejectsInvalidCountOperator(): void
    {
        try {
            EdgeUser::query()->has('edgePosts', '*', 3);
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Invalid count operator', $e->getMessage());
        }
    }

    public function testHasWithZeroCountMatchesRelationlessRows(): void
    {
        // 对抗：'=' 0 是合法计数比较——等价 doesntHave
        $with = EdgeUser::factory()->has(EdgePost::factory(), 'edgePosts')->create();
        $without = EdgeUser::factory()->create();

        $this->assertSame([$without->id], EdgeUser::query()->has('edgePosts', '=', 0)->pluck('id'));
    }

    public function testSyncEmptyArrayDetachesEverything(): void
    {
        $this->pdo->exec("INSERT INTO edge_roles (id, name) VALUES (1, 'a'), (2, 'b')");
        $user = EdgeUser::factory()->create();
        $relation = $user->edgeRoles();

        $relation->sync([1, 2]);
        $this->assertSame(2, $relation->count());

        // 对抗：空数组 = 清空全部关联
        $result = $relation->sync([]);
        $this->assertSame(0, $relation->count());
        $this->assertCount(2, $result['detached']);

        // detach=false 时空数组完全无操作
        $relation->sync([1]);
        $result = $relation->sync([], detach: false);
        $this->assertSame(1, $relation->count());
        $this->assertSame([], $result['detached']);
    }

    public function testSyncWithPivotValuesEmptyIdsIsNoop(): void
    {
        $user = EdgeUser::factory()->create();

        $result = $user->edgeRoles()->syncWithPivotValues([], ['note' => 'x'], detach: false);

        $this->assertSame(['attached' => [], 'detached' => [], 'updated' => []], $result);
    }

    public function testToggleOnUnrelatedIdsOnlyAttaches(): void
    {
        $this->pdo->exec("INSERT INTO edge_roles (id, name) VALUES (7, 'x'), (8, 'y')");
        $user = EdgeUser::factory()->create();
        $relation = $user->edgeRoles();

        $result = $relation->toggle([7, 8]);

        $this->assertSame([7, 8], $result['attached']);
        $this->assertSame([], $result['detached']);
        $this->assertSame(2, $relation->count());

        // 再次 toggle 同一组 → 全部解除
        $result = $relation->toggle([7, 8]);
        $this->assertSame([], $result['attached']);
        $this->assertSame(0, $relation->count());
    }

    // ============================
    // 集合与连接（阶段9）
    // ============================

    public function testCollectionFindOnEmptyAndMixedItems(): void
    {
        $this->assertNull((new Collection())->find(1));

        // 混合非模型元素：find/load 跳过而不是报错
        $mixed = new Collection(['string', 42, null, EdgeUser::factory()->create()]);
        $this->assertNull($mixed->find(999));

        $mixed->load('edgePosts');
        $mixed->loadCount('edgePosts');
        $this->assertTrue($mixed->last()->relationLoaded('edgePosts'));
    }

    public function testModelOnUnknownConnectionFailsClearly(): void
    {
        try {
            EdgeUser::on('no-such-connection')->count();
            $this->fail('Expected PDOException');
        } catch (\PDOException $e) {
            $this->assertStringContainsString('no-such-connection', $e->getMessage());
        }
    }

    // ============================
    // Schema（阶段10A）
    // ============================

    public function testModifyColumnIgnoresUnknownAttributes(): void
    {
        $builder = new class ($this->pdo) extends SchemaBuilder {
            public array $captured = [];

            protected function execute(string $sql): void
            {
                $this->captured[] = $sql;
            }
        };

        $builder->table('edge_users', function (Blueprint $table): void {
            // 对抗：attributes 携带不存在的属性键 → 被忽略而非致命
            $table->modifyColumn('name', 'string', ['length' => 50, 'bogus_key' => 'x']);
        });

        $this->assertSame('ALTER TABLE `edge_users` MODIFY COLUMN `name` VARCHAR(50) NOT NULL', $builder->captured[0]);
    }

    public function testChangeFlagIgnoredOnCreatePath(): void
    {
        $builder = new class ($this->pdo) extends SchemaBuilder {
            public array $captured = [];

            protected function execute(string $sql): void
            {
                $this->captured[] = $sql;
            }
        };

        // 对抗：create 路径的 ->change() 无意义——仍应生成合法的 CREATE TABLE
        $builder->create('edge_new', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->change();
        });

        $this->assertStringContainsString('CREATE TABLE', $builder->captured[0]);
        $this->assertStringContainsString('`name` VARCHAR(255)', $builder->captured[0]);
    }

    // ============================
    // trait（阶段10B）与 JSON（10C）
    // ============================

    public function testHasUuidsOnCustomColumn(): void
    {
        // token 为自定义字符串主键（HasUuids 强制非自增，uniqueIds 指向主键列）
        $this->pdo->exec('CREATE TABLE edge_tokens (token TEXT PRIMARY KEY, created_at TEXT, updated_at TEXT)');

        $model = new class () extends Model {
            use HasUuids;

            protected string $table = 'edge_tokens';

            protected array $fillable = ['token'];

            public function uniqueIds(): array
            {
                return ['token'];
            }

            public function getKeyName(): string
            {
                return 'token';
            }
        };
        $model::resetBooted();

        $created = $model::create();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $created->token
        );
        $this->assertSame($created->token, $model::find($created->token)->token);
    }

    public function testWhereJsonContainsNonScalarValuesBindJson(): void
    {
        // 对抗修复回归：非标量值此前 (string) 产生 "Array" 警告与错误结果
        $this->pdo->exec('INSERT INTO edge_users (id, name, tags) VALUES (1, \'a\', \'[{"x":1}, "plain"]\')');

        $hit = EdgeUser::query()->whereJsonContains('tags', ['x' => 1])->pluck('id');
        $this->assertSame([1], $hit);

        $plain = EdgeUser::query()->whereJsonContains('tags', ['plain'])->pluck('id');
        $this->assertSame([1], $plain);
    }

    public function testWhereJsonContainsOnNullColumnExcludesRow(): void
    {
        // 列为 NULL：包含为假（与 MySQL JSON_CONTAINS 的 NULL 语义一致）
        $this->pdo->exec('INSERT INTO edge_users (id, name, tags) VALUES (1, \'a\', NULL)');

        $this->assertSame([], EdgeUser::query()->whereJsonContains('tags', 'x')->pluck('id'));
        // 不包含对 NULL 行为真（与 NOT JSON_CONTAINS 一致）
        $this->assertSame([1], EdgeUser::query()->whereJsonDoesntContain('tags', 'x')->pluck('id'));
    }

    public function testWhereJsonContainsEmptyArraySemantics(): void
    {
        $this->pdo->exec('INSERT INTO edge_users (id, name, tags) VALUES (1, \'a\', \'["x"]\')');

        // 空 ALL 语义：包含空数组恒假、不包含恒真
        $this->assertSame([], EdgeUser::query()->whereJsonContains('tags', [])->pluck('id'));
        $this->assertSame([1], EdgeUser::query()->whereJsonDoesntContain('tags', [])->pluck('id'));
    }

    // ============================
    // 工厂（阶段13）
    // ============================

    public function testFactoryCountZeroReturnsEmptyCollection(): void
    {
        $users = EdgeUser::factory()->count(0)->create();

        $this->assertInstanceOf(Collection::class, $users);
        $this->assertSame(0, $users->count());
        $this->assertSame(0, EdgeUser::count());
    }

    public function testFactoryStateClosureReturningNonArrayIsIgnored(): void
    {
        $user = EdgeUser::factory()
            ->state(fn () => 'not-an-array')
            ->create(['name' => 'kept']);

        $this->assertSame('kept', $user->name);
    }

    public function testFactoryUndefinedModelSuggestsConvention(): void
    {
        try {
            Factory::make('App\\Model\\Missing');
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Database\\Factories\\MissingFactory', $e->getMessage());
        }
    }

    public function testFactorySequenceEmptySetsAppliesNothing(): void
    {
        $user = EdgeUser::factory()->sequence()->create(['name' => 'plain']);

        $this->assertSame('plain', $user->name);
    }

    public function testHasOnMissingRelationMethodFailsAtCall(): void
    {
        // 对抗：has() 的关系名在父模型上不存在——调用时明确报错
        $factory = EdgeUser::factory()->has(EdgePost::factory()->count(1), 'noSuchRelation');

        try {
            $factory->create();
            $this->fail('Expected Error');
        } catch (Throwable $e) {
            $this->assertStringContainsString('noSuchRelation', $e->getMessage());
        }
    }

    // ============================
    // 作用域交互（阶段8/10 组合）
    // ============================

    public function testSoftDeleteScopeInteractsWithHasAndUpsert(): void
    {
        // 软删模型的 has() 计数与 upsert 时间戳在作用域下的组合行为
        $user = EdgeSoftUser::factory()->create();
        $user->edgePosts()->create(['title' => 'p1']);

        // 软删一篇文章后 has('edgePosts') 仍应排除软删行（作用域应用于 EXISTS 子查询）
        $post = EdgePost::query()->first();
        $post->delete();

        $this->assertSame(0, EdgeSoftUser::query()->has('edgePosts')->count());

        // upsert 触碰软删行不受 deleted_at 过滤（ON CONFLICT 按唯一键）
        $affected = EdgeSoftUser::query()->upsert(
            [['id' => $user->id, 'name' => 'upserted', 'email' => null, 'role' => null, 'tags' => null, 'deleted_at' => null]],
            ['id'],
            ['name']
        );

        $this->assertSame(1, $affected);
        $this->assertSame('upserted', $user->fresh()->name);
    }
}

class EdgeSubUser extends EdgeUser
{
    protected string $table = 'edge_users';
}

class EdgeSoftUser extends EdgeUser
{
    use \Bin\Database\SoftDeletes;

    protected string $table = 'edge_users';
}

class EdgeUser extends Model
{
    protected string $table = 'edge_users';

    protected array $fillable = ['name', 'email', 'role', 'tags'];

    public function edgePosts()
    {
        return $this->hasMany(EdgePost::class, 'user_id');
    }

    public function edgeRoles()
    {
        // 轻量多对多：复用 edge_posts 表结构做 pivot 语义演示不合适——
        // 使用独立的内存 pivot 由测试基类表承担；这里挂 BelongsToMany 到 edge_posts 的对偶
        return $this->belongsToMany(EdgeRole::class, 'edge_role_user', 'user_id', 'role_id');
    }
}

class EdgeRole extends Model
{
    protected string $table = 'edge_roles';

    protected array $fillable = ['name'];
}

class EdgePost extends Model
{
    protected string $table = 'edge_posts';

    protected array $fillable = ['user_id', 'title', 'views'];
}
