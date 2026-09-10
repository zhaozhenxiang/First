<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Database\Model;
use Bin\Database\Relations\Relation;

/**
 * ORM 综合回归测试
 *
 * 覆盖审核发现的高频缺陷：__through_key 泄漏、belongsToMany withCount、
 * morphMap 别名解析、insertGetId string 键、replicate/refresh、
 * hasManyThrough eager 与懒加载行为一致。
 */
class OrmRegressionTest extends TestCase
{
    protected \PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE or_users (id INTEGER PRIMARY KEY, name TEXT, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE or_countries (id INTEGER PRIMARY KEY, name TEXT)');
        $this->pdo->exec('CREATE TABLE or_people (id INTEGER PRIMARY KEY, country_id INTEGER)');
        $this->pdo->exec('CREATE TABLE or_news (id INTEGER PRIMARY KEY, person_id INTEGER, title TEXT)');
        $this->pdo->exec('CREATE TABLE or_roles (id INTEGER PRIMARY KEY, name TEXT)');
        $this->pdo->exec('CREATE TABLE or_role_user (role_id INTEGER, user_id INTEGER)');

        $this->pdo->exec("INSERT INTO or_countries (name) VALUES ('cn')");
        $this->pdo->exec("INSERT INTO or_people (country_id) VALUES (1)");
        $this->pdo->exec("INSERT INTO or_news (person_id, title) VALUES (1, 'n1'), (1, 'n2')");
        $this->pdo->exec("INSERT INTO or_users (name) VALUES ('a')");
        $this->pdo->exec("INSERT INTO or_roles (name) VALUES ('admin')");
        $this->pdo->exec("INSERT INTO or_role_user (role_id, user_id) VALUES (1, 1)");

        OrUser::resetBooted();
        OrRole::resetBooted();
        OrCountry::resetBooted();
        Model::setConnection($this->pdo);
        Relation::flushMorphMap();
    }

    protected function tearDown(): void
    {
        Relation::flushMorphMap();
        Model::setConnection(null);
    }

    public function testHasManyThroughLazyLoadStripsThroughKey(): void
    {
        $country = OrCountry::find(1);
        $news = $country->news;

        $this->assertCount(2, $news);
        $array = $news[0]->toArray();
        $this->assertArrayNotHasKey('__through_key', $array);
    }

    public function testHasManyThroughEagerLoadMatchesLazy(): void
    {
        $eager = OrCountry::with('news')->get()->first();

        $this->assertCount(2, $eager->news);
        $this->assertArrayNotHasKey('__through_key', $eager->news[0]->toArray());
    }

    public function testBelongsToManyWithCountWorks(): void
    {
        // 此前直接 fatal: Call to undefined method getForeignKeyName
        $users = OrUser::withCount('roles')->get();

        $this->assertSame(1, (int) $users[0]->roles_count);
    }

    public function testBelongsToManyWhereHasWorks(): void
    {
        $users = OrUser::whereHas('roles')->get();
        $this->assertCount(1, $users);

        $none = OrUser::whereDoesntHave('roles')->get();
        $this->assertCount(0, $none);
    }

    public function testMorphMapAliasResolvesGlobally(): void
    {
        Model::enforceMorphMap(['usr' => OrUser::class]);

        $this->assertSame('usr', (new OrUser())->getMorphClass());

        // MorphTo 解析走全局映射
        $resolve = new \ReflectionMethod('Bin\Database\Relations\MorphTo', 'resolveMorphClass');
        $rel = (function () {
            return $this->morphTo();
        })->call(new class extends Model {
            protected string $table = 'or_comments';
        });

        $this->assertSame(OrUser::class, $resolve->invoke($rel, 'usr'));
    }

    public function testInsertGetIdReturnsNumericIdAsStringSafe(): void
    {
        $model = OrUser::create(['name' => 'new']);

        // 自增主键为 int
        $this->assertSame(2, $model->id);
    }

    public function testReplicateKeepsGuardedAttributes(): void
    {
        $guarded = new class extends OrUser {
            protected array $fillable = [];   // 全部 guarded
            protected string $table = 'or_users';
        };

        $original = $guarded::find(1);
        $copy = $original->replicate();

        // guarded 字段不再丢失
        $this->assertSame('a', $copy->name);
        $this->assertNull($copy->id);
        $this->assertFalse($copy->exists);
    }

    public function testRefreshClearsChanges(): void
    {
        $user = OrUser::find(1);
        $user->name = 'changed';
        $user->save();
        $user->refresh();

        $this->assertSame([], (fn () => $this->changes)->call($user));
        $this->assertFalse($user->isDirty());
    }

    public function testInstanceUpdateIsScopedToPrimaryKey(): void
    {
        $user = OrUser::find(1);
        $user->update(['name' => 'renamed']);

        $names = $this->pdo->query('SELECT name FROM or_users ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame(['renamed'], $names); // 只有一行，且只有它被改
    }
}

class OrUser extends Model
{
    protected string $table = 'or_users';
    protected array $fillable = ['name'];

    public function roles()
    {
        return $this->belongsToMany(OrRole::class, 'or_role_user', 'user_id', 'role_id');
    }
}

class OrRole extends Model
{
    protected string $table = 'or_roles';
}

class OrCountry extends Model
{
    protected string $table = 'or_countries';

    public function news()
    {
        return $this->hasManyThrough(OrNews::class, OrPerson::class, 'country_id', 'person_id', 'id', 'id');
    }
}

class OrPerson extends Model
{
    protected string $table = 'or_people';
}

class OrNews extends Model
{
    protected string $table = 'or_news';
}
