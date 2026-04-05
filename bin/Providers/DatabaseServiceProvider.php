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
     */
    public function boot(): void
    {
        // 初始化数据库连接
        $this->app->make('db.connection');
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
