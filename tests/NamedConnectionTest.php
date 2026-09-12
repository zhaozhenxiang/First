<?php

declare(strict_types=1);

namespace Tests;

use Bin\Database\ConnectionManager;
use Bin\Database\Debug\DatabaseDebugger;
use Bin\Database\Model;
use Bin\Testing\TestCase;
use PDO;
use PDOException;

/**
 * 多命名连接回归测试（阶段9B）
 *
 * ConnectionManager 按名缓存 + 分驱动 DSN + Model::on()/#[Db]/Schema::connection 接线。
 */
class NamedConnectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ConnectionManager::reset();
    }

    protected function tearDown(): void
    {
        ConnectionManager::reset();
        DatabaseDebugger::disable();
        DatabaseDebugger::clear();
        Model::setConnection(null);
    }

    public function testNamedSlotsAreIndependent(): void
    {
        $one = new PDO('sqlite::memory:');
        $two = new PDO('sqlite::memory:');
        $fallback = new PDO('sqlite::memory:');

        ConnectionManager::setConnection($one, 'one');
        ConnectionManager::setConnection($two, 'two');
        ConnectionManager::setConnection($fallback); // 缺省名写入

        $this->assertSame($one, ConnectionManager::getConnection('one'));
        $this->assertSame($two, ConnectionManager::getConnection('two'));
        $this->assertSame($fallback, ConnectionManager::getConnection());

        // purge 只丢弃指定连接（清除后需重新注入才能获取）
        ConnectionManager::purge('one');
        $replacement = new PDO('sqlite::memory:');
        ConnectionManager::setConnection($replacement, 'one');
        $this->assertNotSame($one, ConnectionManager::getConnection('one'));
        $this->assertSame($two, ConnectionManager::getConnection('two'));

        // setConnection(null) 清除指定槽位
        ConnectionManager::setConnection(null, 'two');
        $replacementTwo = new PDO('sqlite::memory:');
        ConnectionManager::setConnection($replacementTwo, 'two');
        $this->assertNotSame($two, ConnectionManager::getConnection('two'));
    }

    public function testBuildDsnPerDriver(): void
    {
        // mysql：既有键名 dbname/user/pass
        $this->assertSame(
            'mysql:dbname=app;host=127.0.0.1;port=3306;charset=utf8mb4',
            ConnectionManager::buildDsn([
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => '3306',
                'dbname' => 'app',
                'user' => 'root',
                'pass' => 'secret',
                'charset' => 'utf8mb4',
            ])
        );

        // mysql：Laravel 风格键名 database
        $this->assertSame(
            'mysql:dbname=app;host=db;port=3306',
            ConnectionManager::buildDsn([
                'driver' => 'mysql',
                'host' => 'db',
                'port' => '3306',
                'database' => 'app',
                'username' => 'root',
                'password' => 'secret',
            ])
        );

        // mysql：配置不完整抛既有异常
        try {
            ConnectionManager::buildDsn(['driver' => 'mysql', 'host' => 'db']);
            $this->fail('Expected PDOException');
        } catch (PDOException $exception) {
            $this->assertSame('DB Config is invalid', $exception->getMessage());
        }

        // sqlite：文件路径与内存库缺省
        $this->assertSame('sqlite:/tmp/app.db', ConnectionManager::buildDsn(['driver' => 'sqlite', 'database' => '/tmp/app.db']));
        $this->assertSame('sqlite::memory:', ConnectionManager::buildDsn(['driver' => 'sqlite']));

        // pgsql
        $this->assertSame(
            'pgsql:host=127.0.0.1;port=5432;dbname=app',
            ConnectionManager::buildDsn(['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 5432, 'database' => 'app'])
        );
    }

    public function testConfiguredSqliteConnectionResolves(): void
    {
        $pdo = ConnectionManager::getConnection('sqlite');

        $this->assertSame('sqlite', $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        // 按名缓存：两次获取同一实例
        $this->assertSame($pdo, ConnectionManager::getConnection('sqlite'));
    }

    public function testUnknownConnectionNameFailsClearly(): void
    {
        try {
            ConnectionManager::getConnection('analytics');
            $this->fail('Expected PDOException');
        } catch (PDOException $exception) {
            $this->assertStringContainsString('Database connection [analytics] is not configured', $exception->getMessage());
        }
    }

    public function testModelOnRunsQueriesOnNamedConnection(): void
    {
        $secondary = new PDO('sqlite::memory:');
        $secondary->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $secondary->exec('CREATE TABLE nc_users (id INTEGER PRIMARY KEY, name TEXT, created_at TEXT, updated_at TEXT)');

        ConnectionManager::setConnection($secondary, 'secondary');

        // 默认连接是另一个实例（setUp 注入）
        $default = new PDO('sqlite::memory:');
        ConnectionManager::setConnection($default);
        $this->assertNotSame($secondary, Model::getConnection());

        NcUser::resetBooted();

        NcUser::on('secondary')->insert(['name' => 'a', 'created_at' => null, 'updated_at' => null]);
        NcUser::on('secondary')->insert(['name' => 'b', 'created_at' => null, 'updated_at' => null]);

        $this->assertSame(2, NcUser::on('secondary')->count());
        $this->assertSame('a', NcUser::on('secondary')->orderBy('id')->first()->name);

        // 实例连接名访问器
        $model = new NcUser();
        $this->assertNull($model->getConnectionName());
        $this->assertSame('secondary', $model->setConnectionName('secondary')->getConnectionName());

        // 默认连接上没有该表（模型默认查询走默认连接）
        try {
            NcUser::query()->count();
            $this->fail('Expected exception on default connection');
        } catch (\PDOException) {
            // 默认连接的内存库没有 nc_users 表——两个连接确实隔离
        }
    }

    public function testQueryLogRecordsConnectionName(): void
    {
        $secondary = new PDO('sqlite::memory:');
        $secondary->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $secondary->exec('CREATE TABLE nc_users (id INTEGER PRIMARY KEY, name TEXT, created_at TEXT, updated_at TEXT)');
        ConnectionManager::setConnection($secondary, 'secondary');

        DatabaseDebugger::enable();

        NcUser::on('secondary')->count();

        $queries = DatabaseDebugger::getQueries();
        $this->assertNotEmpty($queries);
        $this->assertSame('secondary', $queries[0]->connection);
    }

    public function testSchemaConnectionUsesNamedBuilder(): void
    {
        $secondary = new PDO('sqlite::memory:');
        $secondary->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        ConnectionManager::setConnection($secondary, 'secondary');

        $builder = \Bin\Database\Schema\Schema::connection('secondary');

        $this->assertSame($secondary, $builder->getConnection());
        $this->assertSame($builder, \Bin\Database\Schema\Schema::connection('secondary'));

        \Bin\Database\Schema\Schema::resetBuilders();
    }
}

class NcUser extends Model
{
    protected string $table = 'nc_users';

    protected array $fillable = ['name'];
}
