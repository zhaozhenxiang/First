<?php

declare(strict_types=1);

namespace Bin\Queue\Drivers;

use Bin\Database\ConnectionManager;
use Bin\Queue\Contracts\QueueInterface;
use Bin\Queue\InvalidPayloadException;
use Bin\Queue\Job;
use PDO;

/**
 * 数据库队列驱动
 *
 * 使用 jobs 表存储任务，failed_jobs 表存储失败记录。
 */
class DatabaseQueue implements QueueInterface
{
    protected PDO $pdo;
    protected string $table = 'jobs';
    protected string $failedTable = 'failed_jobs';

    public function __construct(
        protected string $connectionName = 'default',
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? ConnectionManager::getConnection($connectionName);
    }

    public function push(mixed $job, string $queue = 'default'): mixed
    {
        $payload = $this->createPayload($job);
        $now = time();

        $sql = "INSERT INTO `{$this->table}` (queue, payload, attempts, reserved_at, available_at, created_at)
                VALUES (:queue, :payload, 0, NULL, :available, :created)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':queue' => $queue,
            ':payload' => $payload,
            ':available' => $now,
            ':created' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function pushRaw(string $payload, string $queue = 'default'): mixed
    {
        $now = time();

        $sql = "INSERT INTO `{$this->table}` (queue, payload, attempts, reserved_at, available_at, created_at)
                VALUES (:queue, :payload, 0, NULL, :available, :created)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':queue' => $queue,
            ':payload' => $payload,
            ':available' => $now,
            ':created' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function later(int $delay, mixed $job, string $queue = 'default'): mixed
    {
        $payload = $this->createPayload($job);
        $availableAt = time() + $delay;
        $now = time();

        $sql = "INSERT INTO `{$this->table}` (queue, payload, attempts, reserved_at, available_at, created_at)
                VALUES (:queue, :payload, 0, NULL, :available, :created)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':queue' => $queue,
            ':payload' => $payload,
            ':available' => $availableAt,
            ':created' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function pop(string $queue = 'default'): ?Job
    {
        $now = time();

        // 原子操作：查询可用任务并标记保留
        $this->pdo->beginTransaction();

        try {
            $sql = "SELECT * FROM `{$this->table}`
                    WHERE queue = :queue
                      AND reserved_at IS NULL
                      AND available_at <= :now
                    ORDER BY id ASC
                    LIMIT 1";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':queue' => $queue, ':now' => $now]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($record === false) {
                $this->pdo->commit();
                return null;
            }

            // 标记保留
            $updateSql = "UPDATE `{$this->table}`
                          SET reserved_at = :reserved, attempts = attempts + 1
                          WHERE id = :id";

            $updateStmt = $this->pdo->prepare($updateSql);
            $updateStmt->execute([
                ':reserved' => $now,
                ':id' => $record['id'],
            ]);

            $job = $this->hydrateJob($record, true);

            if ($job === null) {
                $exception = new InvalidPayloadException((int) $record['id'], $queue, (string) $record['payload']);
                $this->logFailedPayload($this->connectionName, $queue, (string) $record['payload'], $exception);
                $this->deleteById((int) $record['id']);

                $this->pdo->commit();
                throw $exception;
            }

            $this->pdo->commit();

            return $job;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function delete(mixed $job): bool
    {
        $id = $this->getJobDatabaseId($job);

        if ($id === null) {
            return false;
        }

        $sql = "DELETE FROM `{$this->table}` WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([':id' => $id]);
    }

    public function release(mixed $job, int $delay = 0): bool
    {
        $id = $this->getJobDatabaseId($job);

        if ($id === null) {
            return false;
        }

        $availableAt = time() + $delay;

        $sql = "UPDATE `{$this->table}`
                SET reserved_at = NULL, available_at = :available
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([':available' => $availableAt, ':id' => $id]);
    }

    public function size(string $queue = 'default'): int
    {
        $sql = "SELECT COUNT(*) FROM `{$this->table}`
                WHERE queue = :queue AND reserved_at IS NULL AND available_at <= :now";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':queue' => $queue, ':now' => time()]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * 记录失败任务
     */
    public function logFailedJob(string $connection, string $queue, Job $job, \Throwable $exception): bool
    {
        $sql = "INSERT INTO `{$this->failedTable}` (connection, queue, payload, exception, failed_at)
                VALUES (:connection, :queue, :payload, :exception, :failed_at)";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':connection' => $connection,
            ':queue' => $queue,
            ':payload' => $this->createPayload($job),
            ':exception' => (string) $exception,
            ':failed_at' => time(),
        ]);
    }

    /**
     * 获取失败任务列表
     *
     * @return array<int, array>
     */
    public function getFailedJobs(): array
    {
        $sql = "SELECT * FROM `{$this->failedTable}` ORDER BY id DESC";
        $stmt = $this->pdo->query($sql);

        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * 重试失败任务
     */
    public function retryFailedJob(int $id): bool
    {
        $sql = "SELECT * FROM `{$this->failedTable}` WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($record === false) {
            return false;
        }

        // 重新入队
        $this->pushRaw($record['payload'], $record['queue']);

        // 删除失败记录
        $deleteSql = "DELETE FROM `{$this->failedTable}` WHERE id = :id";
        $deleteStmt = $this->pdo->prepare($deleteSql);
        $deleteStmt->execute([':id' => $id]);

        return true;
    }

    public function forgetFailedJob(int $id): bool
    {
        $sql = "DELETE FROM `{$this->failedTable}` WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function flushFailedJobs(): int
    {
        $sql = "DELETE FROM `{$this->failedTable}`";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function findFailedJob(int $id): ?array
    {
        $sql = "SELECT * FROM `{$this->failedTable}` WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        return $record !== false ? $record : null;
    }

    /**
     * 创建 Job payload
     */
    protected function createPayload(mixed $job): string
    {
        if ($job instanceof Job) {
            return $job->toJson();
        }

        return json_encode(['job' => serialize($job)]) ?: '{}';
    }

    /**
     * 从数据库记录还原 Job
     */
    protected function hydrateJob(array $record, bool $incremented = false): ?Job
    {
        $data = json_decode($record['payload'], true);
        if ($data === null || !isset($data['job'])) {
            return null;
        }

        $job = @unserialize($data['job'], ['allowed_classes' => true]);
        if (!$job instanceof Job) {
            return null;
        }

        $job->setJobId((string) $record['id']);
        $attempts = (int) $record['attempts'];
        $job->setAttempts($incremented ? $attempts + 1 : $attempts);

        return $job;
    }

    /**
     * 获取 Job 的数据库 ID
     */
    protected function getJobDatabaseId(mixed $job): ?int
    {
        if ($job instanceof Job) {
            $id = $job->getJobId();
            return $id !== '' ? (int) $id : null;
        }

        return null;
    }

    /**
     * 设置表名
     */
    public function setTable(string $table): static
    {
        $this->table = $this->sanitizeIdentifier($table);
        return $this;
    }

    /**
     * 设置失败表名
     */
    public function setFailedTable(string $table): static
    {
        $this->failedTable = $this->sanitizeIdentifier($table);
        return $this;
    }

    protected function logFailedPayload(string $connection, string $queue, string $payload, \Throwable $exception): bool
    {
        $sql = "INSERT INTO `{$this->failedTable}` (connection, queue, payload, exception, failed_at)
                VALUES (:connection, :queue, :payload, :exception, :failed_at)";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':connection' => $connection,
            ':queue' => $queue,
            ':payload' => $payload,
            ':exception' => (string) $exception,
            ':failed_at' => time(),
        ]);
    }

    protected function deleteById(int $id): bool
    {
        $sql = "DELETE FROM `{$this->table}` WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([':id' => $id]);
    }

    /**
     * 净化 SQL 标识符（表名/列名）
     */
    protected function sanitizeIdentifier(string $identifier): string
    {
        return str_replace('`', '', $identifier);
    }
}
