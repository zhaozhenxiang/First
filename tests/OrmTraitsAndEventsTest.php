<?php

declare(strict_types=1);

namespace Tests;

use Bin\Console\Commands\ModelPruneCommand;
use Bin\Console\Input;
use Bin\Console\Output;
use Bin\Database\Model;
use Bin\Database\ModelEventDispatcher;
use Bin\Database\MassPrunable;
use Bin\Database\Observer;
use Bin\Database\Prunable;
use Bin\Database\SoftDeletes;
use Bin\Database\HasUuids;
use Bin\Database\HasUlids;
use Bin\Testing\TestCase;

/**
 * ORM 阶段10B 回归测试：HasUuids/HasUlids、Prunable/MassPrunable + model:prune、
 * 软删/复制事件补齐（trashed/forceDeleting/forceDeleted/replicating）。
 */
class OrmTraitsAndEventsTest extends TestCase
{
    protected \PDO $pdo;

    public static string $tempDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE t_uuid_users (id TEXT PRIMARY KEY, name TEXT, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE t_ulid_users (id TEXT PRIMARY KEY, name TEXT, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE t_prune_posts (id INTEGER PRIMARY KEY, title TEXT, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE t_mass_posts (id INTEGER PRIMARY KEY, title TEXT, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE t_soft_posts (id INTEGER PRIMARY KEY, title TEXT, deleted_at TEXT, created_at TEXT, updated_at TEXT)');

        TUuidUser::resetBooted();
        TUlidUser::resetBooted();
        TPrunePost::resetBooted();
        TMassPost::resetBooted();
        TSoftPost::resetBooted();
        Model::setConnection($this->pdo);
    }

    protected function tearDown(): void
    {
        TUuidUser::flushEventListeners();
        TUlidUser::flushEventListeners();
        TPrunePost::flushEventListeners();
        TMassPost::flushEventListeners();
        TSoftPost::flushEventListeners();
        Model::setConnection(null);

        if (self::$tempDir !== '' && is_dir(self::$tempDir)) {
            foreach (glob(self::$tempDir . '/*.php') ?: [] as $f) {
                unlink($f);
            }
            rmdir(self::$tempDir);
            self::$tempDir = '';
        }
    }

    // === HasUuids / HasUlids ===

    public function testHasUuidsFillsPrimaryKeyOnCreate(): void
    {
        $user = TUuidUser::create(['name' => 'a']);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $user->id
        );
        $this->assertFalse($user->getIncrementing());
        $this->assertSame('string', $user->getKeyType());

        // 显式提供的 id 不被覆盖（id 需在 fillable 中才能经 create 传入）
        $fixedId = '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11';
        $fixed = TUuidUser::forceCreate(['id' => $fixedId, 'name' => 'b']);
        $this->assertSame($fixedId, $fixed->id);
    }

    public function testHasUlidsFillsPrimaryKeyOnCreate(): void
    {
        $user = TUlidUser::create(['name' => 'a']);

        // 26 位 Crockford base32（排除 I/L/O/U）
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $user->id);
        $this->assertSame('string', $user->getKeyType());
    }

    // === Prunable / MassPrunable ===

    public function testPrunablePruneAllDeletesAndFiresHook(): void
    {
        TPrunePost::create(['title' => 'old-1']);
        TPrunePost::create(['title' => 'old-2']);
        TPrunePost::create(['title' => 'keep-1']);
        TPrunePost::create(['title' => 'keep-2']);

        TPrunePost::$prunedHook = 0;

        $count = (new TPrunePost())->pruneAll();

        $this->assertSame(2, $count);
        $this->assertSame(2, TPrunePost::$prunedHook);
        $this->assertSame(2, TPrunePost::count());
    }

    public function testMassPrunableBulkDeletes(): void
    {
        TMassPost::create(['title' => 'old-1']);
        TMassPost::create(['title' => 'old-2']);
        TMassPost::create(['title' => 'fresh']);

        $count = (new TMassPost())->pruneAll();

        $this->assertSame(2, $count);
        $this->assertSame(1, TMassPost::count());
    }

    public function testModelPruneCommandPretendReportsCounts(): void
    {
        TPrunePost::create(['title' => 'old-1']);
        TPrunePost::create(['title' => 'old-2']);

        // 临时模型目录 + 可发现的 Prunable 模型
        self::$tempDir = sys_get_temp_dir() . '/orm_prune_' . uniqid();
        mkdir(self::$tempDir);

        file_put_contents(
            self::$tempDir . '/TTempPrunable.php',
            <<<'PHP'
            <?php
            namespace Tests;
            use Bin\Database\Model;
            use Bin\Database\Prunable;
            class TTempPrunable extends Model
            {
                use Prunable;
                protected string $table = 't_prune_posts';
                protected array $fillable = ['title'];
                public function prunable(): \Bin\Database\QueryBuilder
                {
                    return static::where('title', 'like', 'old-%');
                }
            }
            PHP
        );
        require self::$tempDir . '/TTempPrunable.php';

        $command = new class extends ModelPruneCommand {
            protected function modelPath(): string
            {
                return OrmTraitsAndEventsTest::$tempDir;
            }

            protected function modelNamespace(): string
            {
                return 'Tests\\';
            }
        };

        ob_start();
        $command->run(new Input(['php', 'model:prune', '--pretend']), new Output());
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('would be pruned', $output);
        $this->assertStringContainsString('2', $output);
        // --pretend 不实际删除（本测试共 2 行）
        $this->assertSame(2, TPrunePost::count());
    }

    // === 事件补齐 ===

    public function testSoftDeleteFiresTrashedEvent(): void
    {
        $fired = 0;
        // trashed 与实例方法同名，无法静态注册；经 ModelEventDispatcher 监听
        ModelEventDispatcher::listen(TSoftPost::class . '@trashed', function (TSoftPost $post) use (&$fired): void {
            $fired++;
        });

        $post = TSoftPost::create(['title' => 't']);
        $fired = 0;

        $this->assertTrue($post->delete());
        $this->assertSame(1, $fired);
        $this->assertTrue($post->trashed());
    }

    public function testForceDeleteFiresDedicatedEvents(): void
    {
        $forceDeleting = 0;
        $forceDeleted = 0;
        $plainDeleting = 0;
        $plainDeleted = 0;

        TSoftPost::forceDeleting(function (TSoftPost $post) use (&$forceDeleting): void {
            $forceDeleting++;
        });
        TSoftPost::forceDeleted(function (TSoftPost $post) use (&$forceDeleted): void {
            $forceDeleted++;
        });
        TSoftPost::deleting(function (TSoftPost $post) use (&$plainDeleting): void {
            $plainDeleting++;
        });
        TSoftPost::deleted(function (TSoftPost $post) use (&$plainDeleted): void {
            $plainDeleted++;
        });

        $post = TSoftPost::create(['title' => 't']);
        $post->delete(); // 软删一次（走 deleting/deleted）
        $plainDeleting = 0;
        $plainDeleted = 0;

        $this->assertTrue($post->forceDelete());
        $this->assertSame(1, $forceDeleting);
        $this->assertSame(1, $forceDeleted);
        // 对齐 Eloquent：forceDelete 不触发 deleting/deleted
        $this->assertSame(0, $plainDeleting);
        $this->assertSame(0, $plainDeleted);
    }

    public function testObserverReceivesTrashedAndForceDeleted(): void
    {
        $observer = new TSoftObserver();
        TSoftPost::observe($observer);

        $post = TSoftPost::create(['title' => 't']);

        $post->delete();
        $this->assertSame(1, $observer->trashedHits);

        $post->forceDelete();
        $this->assertSame(1, $observer->forceDeletedHits);
    }

    public function testReplicatingFiresOnReplicate(): void
    {
        $fired = 0;
        TPrunePost::replicating(function (TPrunePost $post) use (&$fired): void {
            $fired++;
        });

        $post = TPrunePost::create(['title' => 't']);
        $fired = 0;

        $post->replicate();

        $this->assertSame(1, $fired);
    }

    public function testQuietForceDeleteSkipsNewEvents(): void
    {
        $forceDeleted = 0;
        TSoftPost::forceDeleted(function (TSoftPost $post) use (&$forceDeleted): void {
            $forceDeleted++;
        });

        $post = TSoftPost::create(['title' => 't']);
        $forceDeleted = 0;

        $this->assertTrue($post->forceDeleteQuietly());
        $this->assertSame(0, $forceDeleted);
    }
}

class TUuidUser extends Model
{
    use HasUuids;

    protected string $table = 't_uuid_users';

    protected array $fillable = ['name'];
}

class TUlidUser extends Model
{
    use HasUlids;

    protected string $table = 't_ulid_users';

    protected array $fillable = ['name'];
}

class TPrunePost extends Model
{
    use Prunable;

    public static int $prunedHook = 0;

    protected string $table = 't_prune_posts';

    protected array $fillable = ['title'];

    public function prunable(): \Bin\Database\QueryBuilder
    {
        return static::where('title', 'like', 'old-%');
    }

    protected function pruning(): void
    {
        self::$prunedHook++;
    }
}

class TMassPost extends Model
{
    use MassPrunable;

    protected string $table = 't_mass_posts';

    protected array $fillable = ['title'];

    public function prunable(): \Bin\Database\QueryBuilder
    {
        return static::where('title', 'like', 'old-%');
    }
}

class TSoftPost extends Model
{
    use SoftDeletes;

    protected string $table = 't_soft_posts';

    protected array $fillable = ['title'];
}

class TSoftObserver extends Observer
{
    public int $trashedHits = 0;
    public int $forceDeletedHits = 0;

    public function trashed(Model $model): void
    {
        $this->trashedHits++;
    }

    public function forceDeleted(Model $model): void
    {
        $this->forceDeletedHits++;
    }
}
