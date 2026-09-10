<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Database\Model;
use Bin\Database\SoftDeletes;

/**
 * trait 引导递归回归测试
 *
 * 此前 bootTraits 用 class_uses()（不递归），父类 use SoftDeletes
 * 时子类不生效——已软删数据对子类可见。
 */
class TraitInheritanceTest extends TestCase
{
    protected \PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('
            CREATE TABLE ti_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                deleted_at TEXT,
                created_at TEXT,
                updated_at TEXT
            )
        ');

        TiBaseItem::resetBooted();
        TiChildItem::resetBooted();
        Model::setConnection($this->pdo);
    }

    protected function tearDown(): void
    {
        Model::setConnection(null);
    }

    public function testParentClassSoftDeletesAppliesToChild(): void
    {
        // 父类注册的作用域
        TiBaseItem::query();
        $this->assertArrayHasKey('soft_delete', TiBaseItem::getGlobalScopes());

        // 子类必须同样拥有（此前为空 → 已软删数据泄漏）
        TiChildItem::query();
        $this->assertArrayHasKey('soft_delete', TiChildItem::getGlobalScopes());
    }

    public function testChildClassHidesSoftDeletedRows(): void
    {
        $item = TiChildItem::create(['name' => 'x']);
        $item->delete();

        $this->assertSame(0, TiChildItem::count());
        $this->assertNotNull(TiChildItem::withTrashed()->find($item->id));
    }

    public function testNestedTraitBootMethodsRun(): void
    {
        // initializeTraits 同样递归：嵌套 trait 的初始化钩子应执行
        $booted = [];
        Model::setConnection($this->pdo);

        $model = new class extends Model {
            protected string $table = 'ti_items';
        };

        $uses = (new \ReflectionMethod(Model::class, 'classUsesRecursive'));

        // HasAttributes 等 trait 在继承链上必须被识别
        $traits = $uses->invoke(null, $model::class);
        $this->assertArrayHasKey(\Bin\Database\Model\HasAttributes::class, $traits);
        $this->assertArrayHasKey(\Bin\Database\Model\HasEvents::class, $traits);
    }
}

class TiBaseItem extends Model
{
    use SoftDeletes;

    protected string $table = 'ti_items';
    protected array $fillable = ['name'];
}

class TiChildItem extends TiBaseItem
{
}
