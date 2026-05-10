<?php

declare(strict_types=1);

namespace Bin\Queue\Drivers;

use Bin\Database\ConnectionManager;
use Bin\Queue\Contracts\QueueInterface;
use Bin\Queue\InvalidPayloadException;
use Bin\Queue\Job;
use PDO;
use PDOStatement;
use RuntimeException;

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
        ?PDO $pdo = null,
        protected int $retryAfter = 90
    ) {
        $this->pdo = $pdo ?? ConnectionManager::getConnection($connectionName);
    }

    public function push(mixed $job, string $queue = 'default'): mixed
    {
        $payload = $this->createPayload($job);
        $now = time();

        $sql = "INSERT INTO `{$this->table}` (queue, payload, attempts, reserved_at, available_at, created_at)
                VALUES (:queue, :payload, 0, NULL, :available, :created)";

        $stmt = $this->prepareOrFail($sql, "prepare insert into {$this->table}");
        $this->executeOrFail($stmt, [
            ':queue' => $queue,
            ':payload' => $payload,
            ':available' => $now,
            ':created' => $now,
        ], "insert into {$this->table}");

        return (int) $this->pdo->lastInsertId();
    }

    public function pushRaw(string $payload, string $queue = 'default'): mixed
    {
        $now = time();

        $sql = "INSERT INTO `{$this->table}` (queue, payload, attempts, reserved_at, available_at, created_at)
                VALUES (:queue, :payload, 0, NULL, :available, :created)";

        $stmt = $this->prepareOrFail($sql, "prepare insert into {$this->table}");
        $this->executeOrFail($stmt, [
            ':queue' => $queue,
            ':payload' => $payload,
            ':available' => $now,
            ':created' => $now,
        ], "insert into {$this->table}");

        return (int) $this->pdo->lastInsertId();
    }

    public function later(int $delay, mixed $job, string $queue = 'default'): mixed
    {
        $payload = $this->createPayload($job);
        $availableAt = time() + $delay;
        $now = time();

        $sql = "INSERT INTO `{$this->table}` (queue, payload, attempts, reserved_at, available_at, created_at)
                VALUES (:queue, :payload, 0, NULL, :available, :created)";

        $stmt = $this->prepareOrFail($sql, "prepare insert into {$this->table}");
        $this->executeOrFail($stmt, [
            ':queue' => $queue,
            ':payload' => $payload,
            ':available' => $availableAt,
            ':created' => $now,
        ], "insert into {$this->table}");

        return (int) $this->pdo->lastInsertId();
    }

    public function pop(string $queue = 'default'): ?Job
    {
        $now = time();
        $expiredAt = $now - $this->retryAfter;

        // 原子操作：查询可用任务并标记保留
        $this->pdo->beginTransaction();

        try {
            $sql = "SELECT * FROM `{$this->table}`
                    WHERE queue = :queue
                      AND (reserved_at IS NULL OR reserved_at <= :expired)
                      AND available_at <= :now
                    ORDER BY id ASC
                    LIMIT 1";

            $stmt = $this->prepareOrFail($sql, "prepare select from {$this->table}");
            $this->executeOrFail($stmt, [':queue' => $queue, ':expired' => $expiredAt, ':now' => $now], "select from {$this->table}");
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($record === false) {
                $this->pdo->commit();
                return null;
            }

            // 标记保留
            $updateSql = "UPDATE `{$this->table}`
                          SET reserved_at = :reserved, attempts = attempts + 1
                          WHERE id = :id
                            AND queue = :queue
                            AND (reserved_at IS NULL OR reserved_at <= :expired)
                            AND available_at <= :now";

            $updateStmt = $this->prepareOrFail($updateSql, "prepare reserve {$this->table}");
            $this->executeOrFail($updateStmt, [
                ':reserved' => $now,
                ':id' => $record['id'],
                ':queue' => $queue,
                ':expired' => $expiredAt,
                ':now' => $now,
            ], "reserve {$this->table}");

            if ($updateStmt->rowCount() === 0) {
                $this->pdo->rollBack();
                return null;
            }

            $job = $this->hydrateJob($record, true, $now);

            if ($job === null) {
                $exception = new InvalidPayloadException((int) $record['id'], $queue, (string) $record['payload']);
                $this->logFailedPayload($this->connectionName, $queue, (string) $record['payload'], $exception);
                if (!$this->deleteById((int) $record['id'])) {
                    throw new RuntimeException("Failed to delete invalid payload from {$this->table}.");
                }

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
        $ownership = $this->getJobReservationOwnership($job);

        if ($ownership === null) {
            return false;
        }

        return $this->deleteOwnedJob($ownership['id'], $ownership['attempts'], $ownership['reserved_at']);
    }

    public function release(mixed $job, int $delay = 0): bool
    {
        $ownership = $this->getJobReservationOwnership($job);

        if ($ownership === null) {
            return false;
        }

        $availableAt = time() + $delay;

        $sql = "UPDATE `{$this->table}`
                SET reserved_at = NULL, available_at = :available
                WHERE id = :id
                  AND attempts = :attempts
                  AND reserved_at = :reserved";

        $stmt = $this->prepareOrFail($sql, "prepare release {$this->table}");
        $this->executeOrFail($stmt, [
            ':available' => $availableAt,
            ':id' => $ownership['id'],
            ':attempts' => $ownership['attempts'],
            ':reserved' => $ownership['reserved_at'],
        ], "release {$this->table}");

        return $stmt->rowCount() > 0;
    }

    public function size(string $queue = 'default'): int
    {
        $now = time();
        $expiredAt = $now - $this->retryAfter;

        $sql = "SELECT COUNT(*) FROM `{$this->table}`
                WHERE queue = :queue
                  AND (reserved_at IS NULL OR reserved_at <= :expired)
                  AND available_at <= :now";

        $stmt = $this->prepareOrFail($sql, "prepare count {$this->table}");
        $this->executeOrFail($stmt, [':queue' => $queue, ':expired' => $expiredAt, ':now' => $now], "count {$this->table}");

        return (int) $stmt->fetchColumn();
    }

    /**
     * 记录失败任务
     */
    public function logFailedJob(string $connection, string $queue, Job $job, \Throwable $exception): bool
    {
        $sql = "INSERT INTO `{$this->failedTable}` (connection, queue, payload, exception, failed_at)
                VALUES (:connection, :queue, :payload, :exception, :failed_at)";

        $stmt = $this->prepareOrFail($sql, "prepare insert into {$this->failedTable}");
        $this->executeOrFail($stmt, [
            ':connection' => $connection,
            ':queue' => $queue,
            ':payload' => $this->createPayload($job),
            ':exception' => (string) $exception,
            ':failed_at' => time(),
        ], "insert into {$this->failedTable}");

        return true;
    }

    public function failJob(string $connection, string $queue, Job $job, \Throwable $exception): bool
    {
        $ownership = $this->getJobReservationOwnership($job);

        if ($ownership === null) {
            return false;
        }

        $this->pdo->beginTransaction();

        try {
            if (!$this->ownsReservedJob($ownership['id'], $ownership['attempts'], $ownership['reserved_at'])) {
                $this->pdo->commit();
                return false;
            }

            $this->logFailedJob($connection, $queue, $job, $exception);

            if (!$this->deleteOwnedJob($ownership['id'], $ownership['attempts'], $ownership['reserved_at'])) {
                $this->pdo->rollBack();
                return false;
            }

            $this->pdo->commit();

            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
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
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->prepareOrFail($sql, "prepare select from {$this->failedTable}");
            $this->executeOrFail($stmt, [':id' => $id], "select from {$this->failedTable}");

            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($record === false) {
                $this->pdo->commit();
                return false;
            }

            $deleteSql = "DELETE FROM `{$this->failedTable}` WHERE id = :id";
            $deleteStmt = $this->prepareOrFail($deleteSql, "prepare delete from {$this->failedTable}");
            $this->executeOrFail($deleteStmt, [':id' => $id], "delete from {$this->failedTable}");

            if ($deleteStmt->rowCount() === 0) {
                $this->pdo->rollBack();
                return false;
            }

            $this->pushRaw((string) $record['payload'], (string) $record['queue']);

            $this->pdo->commit();

            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function forgetFailedJob(int $id): bool
    {
        $sql = "DELETE FROM `{$this->failedTable}` WHERE id = :id";
        $stmt = $this->prepareOrFail($sql, "prepare delete from {$this->failedTable}");
        $this->executeOrFail($stmt, [':id' => $id], "delete from {$this->failedTable}");

        return $stmt->rowCount() > 0;
    }

    public function flushFailedJobs(): int
    {
        $sql = "DELETE FROM `{$this->failedTable}`";
        $stmt = $this->prepareOrFail($sql, "prepare flush {$this->failedTable}");
        $this->executeOrFail($stmt, null, "flush {$this->failedTable}");

        return $stmt->rowCount();
    }

    public function findFailedJob(int $id): ?array
    {
        $sql = "SELECT * FROM `{$this->failedTable}` WHERE id = :id";
        $stmt = $this->prepareOrFail($sql, "prepare select from {$this->failedTable}");
        $this->executeOrFail($stmt, [':id' => $id], "select from {$this->failedTable}");

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
    protected function hydrateJob(array $record, bool $incremented = false, ?int $reservedAt = null): ?Job
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
        $job->setReservedAt($reservedAt ?? ($record['reserved_at'] !== null ? (int) $record['reserved_at'] : null));

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
     * @return array{id:int,attempts:int,reserved_at:int}|null
     */
    protected function getJobReservationOwnership(mixed $job): ?array
    {
        if (!$job instanceof Job) {
            return null;
        }

        $id = $this->getJobDatabaseId($job);
        $reservedAt = $job->getReservedAt();

        if ($id === null || $reservedAt === null) {
            return null;
        }

        return [
            'id' => $id,
            'attempts' => $job->getAttempts(),
            'reserved_at' => $reservedAt,
        ];
    }

    protected function ownsReservedJob(int $id, int $attempts, int $reservedAt): bool
    {
        $sql = "SELECT COUNT(*) FROM `{$this->table}`
                WHERE id = :id
                  AND attempts = :attempts
                  AND reserved_at = :reserved";

        $stmt = $this->prepareOrFail($sql, "prepare ownership check {$this->table}");
        $this->executeOrFail($stmt, [
            ':id' => $id,
            ':attempts' => $attempts,
            ':reserved' => $reservedAt,
        ], "ownership check {$this->table}");

        return (int) $stmt->fetchColumn() > 0;
    }

    protected function deleteOwnedJob(int $id, int $attempts, int $reservedAt): bool
    {
        $sql = "DELETE FROM `{$this->table}`
                WHERE id = :id
                  AND attempts = :attempts
                  AND reserved_at = :reserved";

        $stmt = $this->prepareOrFail($sql, "prepare delete from {$this->table}");
        $this->executeOrFail($stmt, [
            ':id' => $id,
            ':attempts' => $attempts,
            ':reserved' => $reservedAt,
        ], "delete from {$this->table}");

        return $stmt->rowCount() > 0;
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

    public function setRetryAfter(int $seconds): static
    {
        $this->retryAfter = max(0, $seconds);
        return $this;
    }

    protected function logFailedPayload(string $connection, string $queue, string $payload, \Throwable $exception): bool
    {
        $sql = "INSERT INTO `{$this->failedTable}` (connection, queue, payload, exception, failed_at)
                VALUES (:connection, :queue, :payload, :exception, :failed_at)";

        $stmt = $this->prepareOrFail($sql, "prepare insert into {$this->failedTable}");

        $this->executeOrFail($stmt, [
            ':connection' => $connection,
            ':queue' => $queue,
            ':payload' => $payload,
            ':exception' => (string) $exception,
            ':failed_at' => time(),
        ], "insert into {$this->failedTable}");

        return true;
    }

    protected function deleteById(int $id): bool
    {
        $sql = "DELETE FROM `{$this->table}` WHERE id = :id";
        $stmt = $this->prepareOrFail($sql, "prepare delete from {$this->table}");
        $this->executeOrFail($stmt, [':id' => $id], "delete from {$this->table}");

        return $stmt->rowCount() > 0;
    }

    protected function prepareOrFail(string $sql, string $context): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);

        if (!$stmt instanceof PDOStatement) {
            throw new RuntimeException("Failed to {$context}.");
        }

        return $stmt;
    }

    protected function executeOrFail(PDOStatement $stmt, ?array $params, string $context): void
    {
        if (!$stmt->execute($params)) {
            throw new RuntimeException("Failed to {$context}.");
        }
    }

    /**
     * 净化 SQL 标识符（表名/列名）
     */
    protected function sanitizeIdentifier(string $identifier): string
    {
        return str_replace('`', '', $identifier);
    }
}
