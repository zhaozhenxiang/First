<?php

declare(strict_types=1);

namespace Bin\Model;

use PDO;
use PDOException;

/**
 * 遗留模型基类 — 仅提供原始 SQL 查询和连接管理
 *
 * @deprecated 请改用 Bin\Database\Model（Eloquent 风格 ORM）或 Bin\Database\ConnectionManager
 */
abstract class Model
{
    private static $connection;

    protected string $table;
    protected static $resultType;

    public function __construct()
    {

    }

    public static function getConnection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $config = self::getDBConfig();

        if (empty($config) || $config['host'] === null || $config['port'] === null || $config['pass'] === null || $config['user'] === null || $config['dbname'] === null) {
            throw new PDOException('DB Config is invalid');
        }

        $dsn = $config['driver'] . ':dbname=' . $config['dbname'] . ';host=' . $config['host'] . ';port=' . $config['port'];

        $conConfig = [
            'dsn' => $dsn,
            'user' => $config['user'],
            'password' => $config['pass']
        ];

        return self::$connection = (new Connection($conConfig))->connection();
    }

    private static function getDBConfig(): array
    {
        $driver = config('database.default');
        self::$resultType = config('database.resultType');

        return array_merge(config('database.connections.' . $driver), ['driver' => $driver, 'resultType' => self::$resultType]);
    }

    final public static function select(string $sql, array $data): array
    {
        return self::action($sql, $data);
    }

    final public static function updateSql(string $sql, array $data): array
    {
        return self::action($sql, $data);
    }

    /**
     * 执行删除 SQL（原始 SQL 方式）
     */
    final public static function deleteSql(string $sql, array $data): array
    {
        return self::action($sql, $data);
    }

    private static function action(string $sql, array $data): array
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($data);

        return $stmt->fetchAll(self::$resultType ?? PDO::FETCH_ASSOC);
    }
}