<?php

declare(strict_types=1);

namespace Bin\Database;

use PDO;
use PDOException;

/**
 * 数据库连接管理器
 *
 * 从 Bin\Model\Model 提取的连接管理逻辑
 */
class ConnectionManager
{
    private static ?PDO $connection = null;

    private static mixed $resultType = null;

    /**
     * 获取数据库连接
     */
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

        if (!empty($config['charset'])) {
            $dsn .= ';charset=' . $config['charset'];
        }

        // 连接超时（秒）：数据库不可达时快速失败，而不是挂住请求
        $options = [
            PDO::ATTR_TIMEOUT => (int) ($config['timeout'] ?? 3),
        ];

        $dbh = new PDO($dsn, $config['user'], $config['pass'], $options);
        $dbh->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return self::$connection = $dbh;
    }

    /**
     * 设置连接（用于测试）
     */
    public static function setConnection(?PDO $connection): void
    {
        self::$connection = $connection;
    }

    /**
     * 获取结果类型
     */
    public static function getResultType(): mixed
    {
        return self::$resultType ?? PDO::FETCH_ASSOC;
    }

    /**
     * 重置连接（用于测试）
     */
    public static function reset(): void
    {
        self::$connection = null;
        self::$resultType = null;
    }

    /**
     * 获取数据库配置
     */
    private static function getDBConfig(): array
    {
        $driver = config('database.default');
        self::$resultType = config('database.resultType');

        return array_merge(config('database.connections.' . $driver), ['driver' => $driver, 'resultType' => self::$resultType]);
    }
}
