<?php

declare(strict_types=1);

namespace Bin\Database\Schema;

use PDO;
use PDOException;
use Exception;

/**
 * Schema 构建器 - 执行表结构操作
 */
class SchemaBuilder
{
    protected PDO $connection;

    protected string $prefix = '';

    protected string $grammar = 'mysql';

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    /**
     * 创建表
     */
    public function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($this->prefix . $table, $this->connection);

        $callback($blueprint);

        $sql = $this->buildCreateTable($blueprint);

        $this->execute($sql);
    }

    /**
     * 修改表
     */
    public function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($this->prefix . $table, $this->connection);

        $callback($blueprint);

        $this->buildTableCommands($blueprint);
    }

    /**
     * 删除表
     */
    public function drop(string $table): void
    {
        $sql = "DROP TABLE {$this->prefix}{$table}";

        $this->execute($sql);
    }

    /**
     * 删除表（如果存在）
     */
    public function dropIfExists(string $table): void
    {
        $sql = "DROP TABLE IF EXISTS {$this->prefix}{$table}";

        $this->execute($sql);
    }

    /**
     * 重命名表
     */
    public function rename(string $from, string $to): void
    {
        $sql = "RENAME TABLE {$this->prefix}{$from} TO {$this->prefix}{$to}";

        $this->execute($sql);
    }

    /**
     * 检查表是否存在
     */
    public function hasTable(string $table): bool
    {
        $sql = "SELECT * FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([$this->prefix . $table]);

        return $stmt->fetch() !== false;
    }

    /**
     * 获取表列
     */
    public function getColumns(string $table): array
    {
        $sql = "SELECT * FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([$this->prefix . $table]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 检查列是否存在
     */
    public function hasColumn(string $table, string $column): bool
    {
        $columns = $this->getColumns($table);

        foreach ($columns as $col) {
            if ($col['COLUMN_NAME'] === $column) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取表列表
     */
    public function getTables(): array
    {
        $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()";

        $stmt = $this->connection->query($sql);

        $tables = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tables[] = $row['table_name'];
        }

        return $tables;
    }

    /**
     * 获取索引
     */
    public function getIndexes(string $table): array
    {
        $sql = "SHOW INDEX FROM {$this->prefix}{$table}";

        $stmt = $this->connection->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 检查索引是否存在
     */
    public function hasIndex(string $table, string $index): bool
    {
        $indexes = $this->getIndexes($table);

        foreach ($indexes as $idx) {
            if ($idx['Key_name'] === $index) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取外键
     */
    public function getForeignKeys(string $table): array
    {
        $sql = "
            SELECT
                CONSTRAINT_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME,
                UPDATE_RULE,
                DELETE_RULE
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND REFERENCED_TABLE_NAME IS NOT NULL
        ";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([$this->prefix . $table]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 禁用外键约束
     */
    public function disableForeignKeyConstraints(): void
    {
        $this->execute('SET FOREIGN_KEY_CHECKS=0');
    }

    /**
     * 启用外键约束
     */
    public function enableForeignKeyConstraints(): void
    {
        $this->execute('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * 构建创建表 SQL
     */
    protected function buildCreateTable(Blueprint $blueprint): string
    {
        $columns = $this->getColumnsSql($blueprint);

        $commands = $this->getCommandsSql($blueprint);

        $sql = "CREATE TABLE {$blueprint->getTable()} (";

        $sql .= implode(', ', array_merge($columns, $commands));

        $sql .= ") ENGINE={$blueprint->getEngine()} DEFAULT CHARSET={$blueprint->getCharset()} COLLATE={$blueprint->getCollation()}";

        return $sql;
    }

    /**
     * 获取列 SQL
     */
    protected function getColumnsSql(Blueprint $blueprint): array
    {
        $sql = [];

        foreach ($blueprint->getColumns() as $column) {
            $sql[] = $this->getColumnSql($column);
        }

        return $sql;
    }

    /**
     * 获取单个列 SQL
     */
    protected function getColumnSql(ColumnDefinition $column): string
    {
        $sql = "{$column->name} {$this->getColumnType($column)}";

        if ($column->unsigned) {
            $sql .= ' UNSIGNED';
        }

        if ($column->nullable) {
            $sql .= ' NULL';
        } else {
            $sql .= ' NOT NULL';
        }

        if ($column->autoIncrement) {
            $sql .= ' AUTO_INCREMENT';
        }

        if ($column->primary) {
            $sql .= ' PRIMARY KEY';
        }

        if ($column->unique) {
            $sql .= ' UNIQUE';
        }

        if ($column->default !== null) {
            $sql .= ' DEFAULT ' . $this->getDefaultValue($column->default);
        }

        if ($column->useCurrent) {
            $sql .= ' DEFAULT CURRENT_TIMESTAMP';
        }

        if ($column->useCurrentOnUpdate) {
            $sql .= ' ON UPDATE CURRENT_TIMESTAMP';
        }

        if ($column->comment !== null) {
            $sql .= " COMMENT '{$column->comment}'";
        }

        return $sql;
    }

    /**
     * 获取列类型
     */
    protected function getColumnType(ColumnDefinition $column): string
    {
        $type = strtoupper($column->getType());

        $length = $column->length;
        $precision = $column->precision;
        $scale = $column->scale;
        $allowed = $column->allowed;

        return match ($type) {
            'BIGINTEGER' => 'BIGINT' . ($length ? "({$length})" : ''),
            'INTEGER' => 'INT' . ($length ? "({$length})" : ''),
            'TINYINTEGER' => 'TINYINT' . ($length ? "({$length})" : ''),
            'SMALLINTEGER' => 'SMALLINT' . ($length ? "({$length})" : ''),
            'MEDIUMINTEGER' => 'MEDIUMINT' . ($length ? "({$length})" : ''),
            'STRING' => 'VARCHAR' . ($length ? "({$length})" : '(255)'),
            'TEXT' => 'TEXT',
            'LONGTEXT' => 'LONGTEXT',
            'MEDIUMTEXT' => 'MEDIUMTEXT',
            'TINYTEXT' => 'TINYTEXT',
            'DECIMAL' => "DECIMAL({$precision}, {$scale})",
            'DOUBLE' => 'DOUBLE' . ($precision && $scale ? "({$precision}, {$scale})" : ''),
            'FLOAT' => 'FLOAT' . ($precision && $scale ? "({$precision}, {$scale})" : ''),
            'BOOLEAN' => 'TINYINT(1)',
            'ENUM' => "ENUM('" . implode("', '", $allowed) . "')",
            'SET' => "SET('" . implode("', '", $allowed) . "')",
            'DATE' => 'DATE',
            'DATETIME' => 'DATETIME' . ($precision ? "({$precision})" : ''),
            'TIME' => 'TIME' . ($precision ? "({$precision})" : ''),
            'TIMESTAMP' => 'TIMESTAMP' . ($precision ? "({$precision})" : ''),
            'YEAR' => 'YEAR',
            'BINARY' => 'BINARY' . ($length ? "({$length})" : ''),
            'JSON' => 'JSON',
            'JSONB' => 'JSON',
            'UUID' => 'CHAR(36)',
            'GEOMETRY' => 'GEOMETRY',
            'POINT' => 'POINT',
            'LINESTRING' => 'LINESTRING',
            'POLYGON' => 'POLYGON',
            'GEOMETRYCOLLECTION' => 'GEOMETRYCOLLECTION',
            default => $type,
        };
    }

    /**
     * 获取默认值 SQL
     */
    protected function getDefaultValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if ($value === null) {
            return 'NULL';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return "'{$value}'";
    }

    /**
     * 获取命令 SQL
     */
    protected function getCommandsSql(Blueprint $blueprint): array
    {
        $sql = [];

        foreach ($blueprint->getCommands() as $command) {
            $sql[] = $this->getCommandSql($blueprint, $command);
        }

        return array_filter($sql);
    }

    /**
     * 获取命令 SQL
     */
    protected function getCommandSql(Blueprint $blueprint, array $command): string
    {
        return match ($command['type']) {
            'primary' => "PRIMARY KEY (" . implode(', ', (array) $command['columns']) . ")",
            'unique' => "UNIQUE KEY " . ($command['name'] ?? $this->createIndexName($blueprint->getTable(), $command['columns'], 'unique')) . " (" . implode(', ', (array) $command['columns']) . ")",
            'index' => "KEY " . ($command['name'] ?? $this->createIndexName($blueprint->getTable(), $command['columns'], 'index')) . " (" . implode(', ', (array) $command['columns']) . ")",
            'foreign' => "FOREIGN KEY (" . implode(', ', (array) $command['columns']) . ") REFERENCES {$command['on']}({$command['references']})" .
                ($command['onDelete'] ? " ON DELETE {$command['onDelete']}" : '') .
                ($command['onUpdate'] ? " ON UPDATE {$command['onUpdate']}" : ''),
            default => '',
        };
    }

    /**
     * 创建索引名
     */
    protected function createIndexName(string $table, array|string $columns, string $type): string
    {
        $table = str_replace($this->prefix, '', $table);
        $columns = is_array($columns) ? implode('_', $columns) : $columns;

        return strtolower("{$table}_{$columns}_{$type}");
    }

    /**
     * 构建表命令（修改表）
     */
    protected function buildTableCommands(Blueprint $blueprint): void
    {
        $table = $blueprint->getTable();

        // 添加列
        foreach ($blueprint->getColumns() as $column) {
            $sql = "ALTER TABLE {$table} ADD COLUMN " . $this->getColumnSql($column);

            if ($column->after) {
                $sql .= " AFTER {$column->after}";
            } elseif ($column->first) {
                $sql .= ' FIRST';
            }

            $this->execute($sql);
        }

        // 执行命令
        foreach ($blueprint->getCommands() as $command) {
            $this->executeCommand($table, $command);
        }
    }

    /**
     * 执行命令
     */
    protected function executeCommand(string $table, array $command): void
    {
        $sql = match ($command['type']) {
            'renameColumn' => "ALTER TABLE {$table} RENAME COLUMN {$command['from']} TO {$command['to']}",
            'dropColumn' => "ALTER TABLE {$table} DROP COLUMN " . implode(', ', $command['columns']),
            'renameTable' => "RENAME TABLE {$table} TO {$this->prefix}{$command['to']}",
            'dropPrimary' => "ALTER TABLE {$table} DROP PRIMARY KEY",
            'dropUnique' => "ALTER TABLE {$table} DROP INDEX {$command['index']}",
            'dropIndex' => "ALTER TABLE {$table} DROP INDEX {$command['index']}",
            'dropForeign' => "ALTER TABLE {$table} DROP FOREIGN KEY " . implode(', ', $command['index']),
            'primary' => "ALTER TABLE {$table} ADD PRIMARY KEY (" . implode(', ', (array) $command['columns']) . ")",
            'unique' => "ALTER TABLE {$table} ADD UNIQUE KEY " . ($command['name'] ?? $this->createIndexName($table, $command['columns'], 'unique')) . " (" . implode(', ', (array) $command['columns']) . ")",
            'index' => "ALTER TABLE {$table} ADD KEY " . ($command['name'] ?? $this->createIndexName($table, $command['columns'], 'index')) . " (" . implode(', ', (array) $command['columns']) . ")",
            'foreign' => "ALTER TABLE {$table} ADD CONSTRAINT " . ($command['name'] ?? $this->createIndexName($table, $command['columns'], 'foreign')) .
                " FOREIGN KEY (" . implode(', ', (array) $command['columns']) . ") REFERENCES {$command['on']}({$command['references']})" .
                ($command['onDelete'] ? " ON DELETE {$command['onDelete']}" : '') .
                ($command['onUpdate'] ? " ON UPDATE {$command['onUpdate']}" : ''),
            default => '',
        };

        if ($sql !== '') {
            $this->execute($sql);
        }
    }

    /**
     * 执行 SQL
     */
    protected function execute(string $sql): void
    {
        try {
            $this->connection->exec($sql);
        } catch (PDOException $e) {
            throw new Exception("Schema error: {$e->getMessage()}\nSQL: {$sql}");
        }
    }

    /**
     * 设置表前缀
     */
    public function setTablePrefix(string $prefix): self
    {
        $this->prefix = $prefix;
        return $this;
    }

    /**
     * 获取表前缀
     */
    public function getTablePrefix(): string
    {
        return $this->prefix;
    }
}
