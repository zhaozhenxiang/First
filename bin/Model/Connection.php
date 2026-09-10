<?php

declare(strict_types=1);

namespace Bin\Model;

use PDO;

class Connection
{
    private static $connection = null;
    private static $config = [];

    public function __construct(array $config, $pdo = true)
    {
        self::$config = $config;
        self::$connection = $this->connection();
    }

    public function connection()
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = self::$config;
        // 连接超时（秒）：数据库不可达时快速失败，而不是挂住请求
        $options = [
            PDO::ATTR_TIMEOUT => (int) ($config['timeout'] ?? 3),
        ];
        $dbh = new PDO($config['dsn'], $config['user'], $config['password'], $options);
        $dbh->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return self::$connection = $dbh;
    }

    public function reconnection($config)
    {
        $this->disconnection();
        self::$config = $config;

        return $this->connection();
    }

    public function disconnection()
    {
        self::$connection = null;
    }
}