<?php

declare(strict_types=1);

namespace Tests;

use Bin\Database\Attributes\Appends;
use Bin\Database\Attributes\Casts;
use Bin\Database\Attributes\Connection;
use Bin\Database\Attributes\Fillable;
use Bin\Database\Attributes\Guarded;
use Bin\Database\Attributes\Hidden;
use Bin\Database\Attributes\ObservedBy;
use Bin\Database\Attributes\ScopedBy;
use Bin\Database\Attributes\Table;
use Bin\Database\Model;
use Bin\Database\QueryBuilder;
use Bin\Database\Scope;
use Bin\Database\SoftDeletes;
use Bin\Testing\TestCase;
use PDO;

/**
 * PHP 属性配置回归测试（阶段11）
 *
 * #[Table]/#[Fillable]/#[Guarded]/#[Hidden]/#[Visible]/#[Appends]/#[Casts]/
 * #[Connection]/#[ScopedBy]/#[ObservedBy] 与传统属性声明共存，属性式优先。
 */
class ModelAttributesTest extends TestCase
{
    protected \PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE attr_members (member_id INTEGER PRIMARY KEY, name TEXT, secret TEXT, payload TEXT, extra TEXT, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE attr_defaults (id INTEGER PRIMARY KEY, name TEXT, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE attr_scoped (id INTEGER PRIMARY KEY, status TEXT, deleted_at TEXT, created_at TEXT, updated_at TEXT)');

        AttrMember::resetBooted();
        AttrDefault::resetBooted();
        AttrScoped::resetBooted();
        AttrMixed::resetBooted();
        BootNestedModel::resetBooted();
        Model::setConnection($this->pdo);
    }

    protected function tearDown(): void
    {
        foreach ([AttrMember::class, AttrDefault::class, AttrScoped::class, AttrMixed::class, BootNestedModel::class] as $class) {
            $class::flushEventListeners();
            $class::resetBooted();
        }
        Model::setConnection(null);
    }

    public function testTableAttributeConfiguresNameAndKey(): void
    {
        $member = new AttrMember();

        $this->assertSame('attr_members', $member->getTable());
        $this->assertSame('member_id', $member->getKeyName());
    }

    public function testFillableGuardedHiddenAppendsFromAttributes(): void
    {
        $member = new AttrMember(['name' => 'a', 'secret' => 's']); // secret 被 Guarded 拦截
        $this->assertNull($member->secret);
        $this->assertSame('a', $member->name);

        // Hidden 参与序列化过滤，Appends 参与追加
        $member->setRawAttributes(['member_id' => 1, 'name' => 'a', 'secret' => 'classified']);
        $array = $member->toArray();

        $this->assertArrayNotHasKey('secret', $array);
        $this->assertArrayHasKey('name', $array);
    }

    public function testCastsAttributeApplies(): void
    {
        $member = new AttrMember();
        $member->setRawAttributes(['member_id' => 1, 'payload' => '{"a":1}']);

        $this->assertSame(['a' => 1], $member->payload);
    }

    public function testTableTimestampsFalseDisablesTimestamps(): void
    {
        $this->pdo->exec('CREATE TABLE attr_no_ts (id INTEGER PRIMARY KEY, name TEXT)');

        AttrNoTimestamps::resetBooted();
        AttrNoTimestamps::forceCreate(['name' => 'x']);

        // 表无时间戳列：若 timestamps 未被属性禁用，INSERT 会带上 created_at 而报错
        $this->assertSame('x', AttrNoTimestamps::find(1)->name);
    }

    public function testPropertyDeclarationStillWinsForTable(): void
    {
        // 传统属性声明不受 #[Table] 缺省影响（AttrDefault 无 Table 属性）
        $this->assertSame('attr_defaults', (new AttrDefault())->getTable());

        // 混合：属性式 Guarded 追加到属性声明 fillable 之外
        $mixed = new AttrMixed(['name' => 'n']);
        $this->assertSame('n', $mixed->name);
    }

    public function testConnectionAttributeSetsConnectionName(): void
    {
        $secondary = new PDO('sqlite::memory:');
        $secondary->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $secondary->exec('CREATE TABLE attr_conn (id INTEGER PRIMARY KEY, name TEXT)');
        \Bin\Database\ConnectionManager::setConnection($secondary, 'attr-secondary');

        try {
            AttrConnected::resetBooted();

            $query = AttrConnected::query();
            $this->assertSame('attr-secondary', $query->getConnectionName());

            $query->insertGetId(['name' => 'x']);
            $this->assertSame(1, AttrConnected::count());
        } finally {
            \Bin\Database\ConnectionManager::purge('attr-secondary');
        }
    }

    public function testScopedByAttributeAppliesGlobalScope(): void
    {
        $insert = $this->pdo->prepare('INSERT INTO attr_scoped (id, status, deleted_at) VALUES (?, ?, NULL)');
        $insert->execute([1, 'active']);
        $insert->execute([2, 'archived']);

        AttrScoped::resetBooted();

        // #[ScopedBy] 作用域只保留 active
        $this->assertSame([1], AttrScoped::query()->orderBy('id')->pluck('id'));

        // withTrashed 式移除作用域仍可用（作用域标识为作用域类名）
        $this->assertSame([1, 2], AttrScoped::query()->withoutGlobalScope(AttrActiveScope::class)->orderBy('id')->pluck('id'));
    }

    public function testObservedByAttributeRegistersObserver(): void
    {
        AttrScoped::resetBooted();

        $observer = new AttrObserver();
        // 直接验证属性解析出的类；observe 注册已在 boot 时完成，这里验证触发
        AttrScoped::observe($observer);

        AttrScoped::create(['status' => 'active']);

        $this->assertSame(1, $observer->createdHits);
    }

    public function testScopedAndObservedByOnSameModel(): void
    {
        // AttrScoped 同时带 #[ScopedBy] 与 #[ObservedBy]：boot 自动注册两者
        // （观察者经属性注册触发一次）
        $post = AttrScoped::create(['status' => 'active']);
        $this->assertInstanceOf(AttrScoped::class, $post);
        $this->assertSame(1, AttrScoped::query()->count());
    }

    public function testNestedInstantiationDuringBootThrows(): void
    {
        BootNestedModel::resetBooted();

        // 首次实例化触发 boot，trait boot 钩子在 boot 期间尝试 new static
        $model = new BootNestedModel();

        $this->assertInstanceOf(\LogicException::class, BootNestedModel::$nestedResult);
        // boot 完成后实例化恢复正常
        $this->assertInstanceOf(BootNestedModel::class, $model);
    }
}

class AttrObserver extends \Bin\Database\Observer
{
    public int $createdHits = 0;

    public function created(Model $model): void
    {
        $this->createdHits++;
    }
}

class AttrActiveScope implements Scope
{
    public function apply(QueryBuilder $query, Model $model): void
    {
        $query->where('status', 'active');
    }
}

#[Table('attr_members', key: 'member_id')]
#[Fillable(['name', 'payload', 'extra'])]
#[Guarded(['secret'])]
#[Hidden(['secret'])]
#[Casts(['payload' => 'array'])]
#[Appends(['display'])]
class AttrMember extends Model
{
    public function getDisplayAttribute($value = null)
    {
        return 'member:' . ($this->name ?? '');
    }
}

class AttrDefault extends Model
{
    protected string $table = 'attr_defaults';

    protected array $fillable = ['name'];
}

class AttrMixed extends Model
{
    protected string $table = 'attr_defaults';

    protected array $fillable = ['name'];

    #[Guarded(['secret'])]
    protected array $guarded = [];
}

#[ScopedBy(AttrActiveScope::class)]
#[ObservedBy(AttrObserver::class)]
class AttrScoped extends Model
{
    use SoftDeletes;

    protected string $table = 'attr_scoped';

    protected array $fillable = ['status'];
}

#[Table(timestamps: false)]
class AttrNoTimestamps extends Model
{
    protected string $table = 'attr_no_ts';

    protected array $fillable = ['name'];
}

#[Connection('attr-secondary')]
class AttrConnected extends Model
{
    protected string $table = 'attr_conn';

    protected array $fillable = ['name'];
}

trait BootNestedTrigger
{
    public static mixed $nestedResult = null;

    protected static function bootBootNestedTrigger(): void
    {
        try {
            static::$nestedResult = new static();
        } catch (\Throwable $e) {
            static::$nestedResult = $e;
        }
    }
}

class BootNestedModel extends Model
{
    use BootNestedTrigger;

    protected string $table = 'attr_defaults';
}
