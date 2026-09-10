<?php

declare(strict_types=1);

namespace Bin\Providers;

use Bin\Database\QueryBuilder;
use Bin\Database\ConnectionManager;

/**
 * 数据库服务提供者
 */
class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * 注册数据库服务
     */
    public function register(): void
    {
        // 注册数据库连接单例
        $this->singleton('db.connection', function () {
            return ConnectionManager::getConnection();
        });

        // 注册查询构建器工厂
        $this->bind('db.query', function () {
            return new QueryBuilder(ConnectionManager::getConnection());
        });

        // 设置别名
        $this->alias('db.connection', 'db');
        $this->alias('db.query', 'query');
    }

    /**
     * 启动数据库服务
     *
     * 连接保持懒加载：首次使用 db.connection / db.query 时才建立 PDO 连接，
     * 避免每个请求都为不触及数据库的路径支付连接握手开销。
     */
    public function boot(): void
    {
    }

    /**
     * 提供的服务
     */
    public function provides(): array
    {
        return [
            'db.connection',
            'db.query',
            'db',
            'query',
        ];
    }
}
