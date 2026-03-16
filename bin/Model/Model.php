<?php

declare(strict_types=1);

namespace Bin\Model;

use PDO;
use PDOException;

abstract class Model
{
    private static $connection;

    protected $table;
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
        $driver = config('db:driver');
        self::$resultType = config('db:resultType');

        return array_merge(config('db:connection:' . $driver), ['driver' => $driver, 'resultType' => self::$resultType]);
    }

    final public static function select(string $sql, array $data): array
    {
        return self::action($sql, $data);
    }

    final public static function update(string $sql, array $data): array
    {
        return self::action($sql, $data);
    }

    final public static function delete(string $sql, array $data): array
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