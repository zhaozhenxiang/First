<?php
namespace Bin\Model;
abstract class Model
{
    private static $connection;

    protected $table;
    protected static $resultType;

    public function __construct()
    {

    }

    public static function getConnection()
    {
        if (!is_null(self::$connection)) {
            return self::$connection;
        }

        $config = self::getDBConfig();
        if (empty($config) || is_null($config['host']) || is_null($config['port']) || is_null($config['pass']) || is_null($config['user']) || is_null($config['dbname'])) {
            throw new \Exception('DB Config is invalid');
        }

        $conConfig = [
            'dsn' => $config['driver'] . ':dbname=' .$config['dbname'] . ';host=' . $config['host'],
            'user' => $config['user'],
            'password' => $config['pass']
        ];

        return self::$connection = (new Connection($conConfig))->connection();
    }

    private static function getDBConfig()
    {
        $driver = config('db:driver');
        self::$resultType = config('db:resultType');

        return array_merge(config('db:connection:' . $driver), ['driver' => $driver, 'resultType' => self::$resultType]);
    }

    public final static function select($sql, $data)
    {
        return self::action($sql, $data);
    }

    public final static function update($sql, $data)
    {
        return self::action($sql, $data);
    }

    public final static function delete($sql, $data)
    {
        return self::action($sql, $data);
    }

    private static function action($sql, $data)
    {
        $stmt = self::getConnection()->prepare($sql);

        $stmt->execute();

        //@todo 设置返回值类型
        return $stmt->fetchAll(self::$resultType);
    }
}