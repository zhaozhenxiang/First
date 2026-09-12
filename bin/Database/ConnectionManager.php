<?php

declare(strict_types=1);

namespace Bin\Database;

use PDO;
use PDOException;

/**
 * 数据库连接管理器
 *
 * 支持多命名连接：按 config('database.connections.<name>') 解析并按名缓存；
 * 名字缺省时使用 config('database.default')。读写分离尚未支持
 * （需要按语句类型分流的 Connection 抽象层，见 差距laravel.md P2）。
 */
class ConnectionManager
{
    /**
     * 按名缓存的连接
     *
     * @var array<string, PDO>
     */
    private static array $connections = [];

    private static mixed $resultType = null;

    /**
     * 获取数据库连接
     */
    public static function getConnection(?string $name = null): PDO
    {
        $name ??= self::defaultName();

        if (isset(self::$connections[$name])) {
            return self::$connections[$name];
        }

        return self::$connections[$name] = self::connect($name);
    }

    /**
     * 设置连接（用于测试；缺省名写入，与既有的注入语义兼容）
     */
    public static function setConnection(?PDO $connection, ?string $name = null): void
    {
        $name ??= self::defaultName();

        if ($connection === null) {
            unset(self::$connections[$name]);
            return;
        }

        self::$connections[$name] = $connection;
    }

    /**
     * 获取结果类型
     */
    public static function getResultType(): mixed
    {
        return self::$resultType ?? PDO::FETCH_ASSOC;
    }

    /**
     * 丢弃指定连接（下次获取时重连）
     */
    public static function purge(?string $name = null): void
    {
        unset(self::$connections[$name ?? self::defaultName()]);
    }

    /**
     * 重置全部连接（用于测试）
     */
    public static function reset(): void
    {
        self::$connections = [];
        self::$resultType = null;
    }

    /**
     * 获取默认连接名
     */
    public static function defaultName(): string
    {
        return (string) (config('database.default') ?? 'mysql');
    }

    /**
     * 建立命名连接
     */
    private static function connect(string $name): PDO
    {
        $config = self::getConfig($name);

        if (empty($config['driver'])) {
            throw new PDOException("Database connection [{$name}] is not configured.");
        }

        $options = [
            PDO::ATTR_TIMEOUT => (int) ($config['timeout'] ?? 3),
        ];

        $dbh = new PDO(
            self::buildDsn($config),
            $config['user'] ?? $config['username'] ?? null,
            $config['pass'] ?? $config['password'] ?? null,
            $options
        );
        $dbh->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $dbh;
    }

    /**
     * 按驱动构建 DSN（公开供方言单测）
     *
     * mysql：dbname/host/port[/charset]，沿用既有键名 dbname/user/pass，
     * 同时接受 Laravel 风格的 database/username/password；
     * sqlite：database 键为库文件路径，缺省内存库；
     * pgsql：host/port/database。
     */
    public static function buildDsn(array $config): string
    {
        return match ($config['driver'] ?? 'mysql') {
            'sqlite' => 'sqlite:' . ($config['database'] ?? ':memory:'),
            'pgsql' => sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? 5432,
                $config['database'] ?? $config['dbname'] ?? ''
            ),
            'mysql' => self::buildMysqlDsn($config),
            default => self::buildMysqlDsn($config),
        };
    }

    /**
     * 构建 MySQL DSN（含配置完整性校验，行为与拆分前一致）
     */
    private static function buildMysqlDsn(array $config): string
    {
        $dbname = $config['dbname'] ?? $config['database'] ?? null;
        $user = $config['user'] ?? $config['username'] ?? null;
        $pass = $config['pass'] ?? $config['password'] ?? null;

        if (empty($config) || $config['host'] === null || $config['port'] === null || $pass === null || $user === null || $dbname === null) {
            throw new PDOException('DB Config is invalid');
        }

        $dsn = 'mysql:dbname=' . $dbname . ';host=' . $config['host'] . ';port=' . $config['port'];

        if (!empty($config['charset'])) {
            $dsn .= ';charset=' . $config['charset'];
        }

        return $dsn;
    }

    /**
     * 获取命名连接配置
     */
    private static function getConfig(string $name): array
    {
        self::$resultType = config('database.resultType');

        $config = config('database.connections.' . $name);

        if (!is_array($config)) {
            return [];
        }

        return array_merge($config, ['driver' => $config['driver'] ?? $name, 'resultType' => self::$resultType]);
    }
}
