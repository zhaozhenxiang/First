<?php
namespace Bin\Model;

class Connection
{
    private static $connection = null;
    private static $config = [];

    public function __construct(array $config, $pdo = TRUE)
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
        $dbh = new \PDO($config['dsn'], $config['user'], $config['password']);
        $dbh->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        return self::$connection = $dbh;
//        return self::$conncection = $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function reconnection($config)
    {
        self::disconnection();
        self::$config = $config;

        return self::connection();
    }

    public function disconnection()
    {
        self::$connection = null;
    }
}