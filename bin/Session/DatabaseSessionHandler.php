<?php

declare(strict_types=1);

namespace Bin\Session;

use PDO;
use RuntimeException;
use \SessionHandlerInterface;

/**
 * 数据库 Session 处理器
 */
class DatabaseSessionHandler implements \SessionHandlerInterface
{
    /** @var PDO 数据库连接 */
    private PDO $connection;

    /** @var string Session 表名 */
    private string $table;

    /** @var int Session 生命周期（秒） */
    private int $lifetime;

    /**
     * 构造函数
     */
    public function __construct(PDO $connection, ?string $table = null, int $minutes = 120)
    {
        $this->connection = $connection;
        $this->table = $table ?? 'sessions';
        $this->lifetime = $minutes * 60;

        $this->createTableIfNeeded();
    }

    /**
     * 打开 Session
     */
    public function open(string $path, string $name): bool
    {
        return true;
    }

    /**
     * 关闭 Session
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * 读取 Session 数据
     */
    public function read(string $id): string
    {
        $stmt = $this->connection->prepare(
            "SELECT payload FROM {$this->table} WHERE id = :id AND last_activity >= :time"
        );

        $time = time() - $this->lifetime;
        $stmt->bindParam(':id', $id, PDO::PARAM_STR);
        $stmt->bindParam(':time', $time, PDO::PARAM_INT);

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result === false || $result === null) {
            return '';
        }

        return $result['payload'] ?? '';
    }

    /**
     * 写入 Session 数据
     */
    public function write(string $id, string $data): bool
    {
        $stmt = $this->connection->prepare(
            "INSERT INTO {$this->table} (id, payload, last_activity) VALUES (:id, :payload, :time)
             ON DUPLICATE KEY UPDATE payload = :payload, last_activity = :time"
        );

        $time = time();

        $stmt->bindParam(':id', $id, PDO::PARAM_STR);
        $stmt->bindParam(':payload', $data, PDO::PARAM_STR);
        $stmt->bindParam(':time', $time, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * 销毁 Session
     */
    public function destroy(string $id): bool
    {
        $stmt = $this->connection->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_STR);

        return $stmt->execute();
    }

    /**
     * 垃圾回收
     */
    public function gc(int $max_lifetime): int
    {
        $time = time() - $max_lifetime;

        $stmt = $this->connection->prepare("DELETE FROM {$this->table} WHERE last_activity < :time");
        $stmt->bindParam(':time', $time, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * 创建 Session 表（如果不存在）
     */
    private function createTableIfNeeded(): void
    {
        $stmt = $this->connection->prepare(
            "CREATE TABLE IF NOT EXISTS {$this->table} (
                id VARCHAR(255) PRIMARY KEY,
                payload LONGTEXT NOT NULL,
                last_activity INT NOT NULL,
                INDEX idx_last_activity (last_activity)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $stmt->execute();
    }

    /**
     * 获取活跃 Session 数量
     */
    public function countActive(): int
    {
        $time = time() - $this->lifetime;

        $stmt = $this->connection->prepare(
            "SELECT COUNT(*) as count FROM {$this->table} WHERE last_activity >= :time"
        );
        $stmt->bindParam(':time', $time, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($result['count'] ?? 0);
    }

    /**
     * 清空所有 Session
     */
    public function clear(): bool
    {
        $stmt = $this->connection->prepare("DELETE FROM {$this->table}");
        return $stmt->execute();
    }

    /**
     * 获取指定用户的所有 Session ID
     */
    public function getUserSessions(string $userId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT id FROM {$this->table} WHERE id LIKE :user_id"
        );

        $pattern = "user_{$userId}_%";
        $stmt->bindParam(':user_id', $pattern, PDO::PARAM_STR);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_column($results, 'id');
    }

    /**
     * 销毁指定用户的所有 Session
     */
    public function destroyUserSessions(string $userId): int
    {
        $sessions = $this->getUserSessions($userId);
        $count = 0;

        foreach ($sessions as $sessionId) {
            if ($this->destroy($sessionId)) {
                $count++;
            }
        }

        return $count;
    }
}
