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
     * 获取底层连接
     */
    public function getConnection(): PDO
    {
        return $this->connection;
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
        $sql = 'DROP TABLE ' . $this->wrapId($this->prefix . $table);

        $this->execute($sql);
    }

    /**
     * 删除表（如果存在）
     */
    public function dropIfExists(string $table): void
    {
        $sql = 'DROP TABLE IF EXISTS ' . $this->wrapId($this->prefix . $table);

        $this->execute($sql);
    }

    /**
     * 重命名表
     */
    public function rename(string $from, string $to): void
    {
        $sql = 'RENAME TABLE ' . $this->wrapId($this->prefix . $from) . ' TO ' . $this->wrapId($this->prefix . $to);

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
     * 包裹 DDL 标识符（反引号），保留字列名/表名不再产生语法错误
     */
    protected function wrapId(string $identifier): string
    {
        if ($identifier === '' || str_contains($identifier, '`')) {
            return $identifier;
        }

        return '`' . str_replace('.', '`.`', $identifier) . '`';
    }

    /**
     * 转义 SQL 字符串字面量（单引号翻倍，MySQL/SQLite 兼容）
     */
    protected function quoteString(mixed $value): string
    {
        return "'" . str_replace("'", "''", (string) $value) . "'";
    }

    /**
     * 构建创建表 SQL
     */
    protected function buildCreateTable(Blueprint $blueprint): string
    {
        $columns = $this->getColumnsSql($blueprint);

        $commands = $this->getCommandsSql($blueprint);

        // foreignId()->constrained() 声明的外键在列定义中携带，这里一并生成
        foreach ($blueprint->getColumns() as $column) {
            if ($column instanceof ForeignIdDefinition && $column->hasForeignKey()) {
                $commands[] = $this->getForeignIdSql($column);
            }
        }

        $sql = 'CREATE TABLE ' . $this->wrapId($blueprint->getTable()) . ' (';

        $sql .= implode(', ', array_merge($columns, $commands));

        $sql .= ") ENGINE={$blueprint->getEngine()} DEFAULT CHARSET={$blueprint->getCharset()} COLLATE={$blueprint->getCollation()}";

        return $sql;
    }

    /**
     * foreignId 流式链生成的外键子句
     */
    protected function getForeignIdSql(ForeignIdDefinition $column): string
    {
        $sql = 'FOREIGN KEY (' . $this->wrapId((string) $column->name) . ') REFERENCES '
            . $this->wrapId($column->foreignTable) . '(' . $this->wrapId($column->foreignColumn) . ')';

        if ($column->onDelete !== null) {
            $sql .= " ON DELETE {$column->onDelete}";
        }

        if ($column->onUpdate !== null) {
            $sql .= " ON UPDATE {$column->onUpdate}";
        }

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
        $sql = $this->wrapId((string) $column->name) . ' ' . $this->getColumnType($column);

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

        // DEFAULT 子句只能出现一次：显式默认值与 CURRENT_TIMESTAMP 互斥使用
        if ($column->useCurrent) {
            $sql .= ' DEFAULT CURRENT_TIMESTAMP';
        } elseif ($column->default !== null) {
            $sql .= ' DEFAULT ' . $this->getDefaultValue($column->default);
        }

        if ($column->useCurrentOnUpdate) {
            $sql .= ' ON UPDATE CURRENT_TIMESTAMP';
        }

        if ($column->comment !== null) {
            $sql .= ' COMMENT ' . $this->quoteString($column->comment);
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
            'DECIMAL' => 'DECIMAL(' . ($precision ?? 8) . ', ' . ($scale ?? 2) . ')',
            'DOUBLE' => 'DOUBLE' . ($precision && $scale ? "({$precision}, {$scale})" : ''),
            'FLOAT' => 'FLOAT' . ($precision && $scale ? "({$precision}, {$scale})" : ''),
            'BOOLEAN' => 'TINYINT(1)',
            'ENUM' => 'ENUM(' . implode(', ', array_map(fn ($v) => $this->quoteString($v), $allowed)) . ')',
            'SET' => 'SET(' . implode(', ', array_map(fn ($v) => $this->quoteString($v), $allowed)) . ')',
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

        return $this->quoteString($value);
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
        $wrapColumns = fn (array $columns) => implode(', ', array_map(fn ($c) => $this->wrapId($c), $columns));

        return match ($command['type']) {
            'primary' => 'PRIMARY KEY (' . $wrapColumns((array) $command['columns']) . ')',
            'unique' => 'UNIQUE KEY ' . $this->wrapId($command['name'] ?? $this->createIndexName($blueprint->getTable(), $command['columns'], 'unique')) . ' (' . $wrapColumns((array) $command['columns']) . ')',
            'index' => 'KEY ' . $this->wrapId($command['name'] ?? $this->createIndexName($blueprint->getTable(), $command['columns'], 'index')) . ' (' . $wrapColumns((array) $command['columns']) . ')',
            'fulltext' => 'FULLTEXT KEY ' . $this->wrapId($command['name'] ?? $this->createIndexName($blueprint->getTable(), $command['columns'], 'fulltext')) . ' (' . $wrapColumns((array) $command['columns']) . ')',
            'spatialIndex' => 'SPATIAL KEY ' . $this->wrapId($command['name'] ?? $this->createIndexName($blueprint->getTable(), $command['columns'], 'spatialindex')) . ' (' . $wrapColumns((array) $command['columns']) . ')',
            'foreign' => 'FOREIGN KEY (' . $wrapColumns((array) $command['columns']) . ') REFERENCES '
                . $this->wrapId((string) $command['on']) . '(' . $this->wrapId((string) $command['references']) . ')'
                . ($command['onDelete'] ? " ON DELETE {$command['onDelete']}" : '')
                . ($command['onUpdate'] ? " ON UPDATE {$command['onUpdate']}" : ''),
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

        // 添加列（->change() 标记的列走 MODIFY COLUMN，其余 ADD COLUMN）
        foreach ($blueprint->getColumns() as $column) {
            if ($column->change) {
                $this->execute('ALTER TABLE ' . $this->wrapId($table) . ' MODIFY COLUMN ' . $this->getColumnSql($column));
                continue;
            }

            $sql = 'ALTER TABLE ' . $this->wrapId($table) . ' ADD COLUMN ' . $this->getColumnSql($column);

            if ($column->after) {
                $sql .= ' AFTER ' . $this->wrapId($column->after);
            } elseif ($column->first) {
                $sql .= ' FIRST';
            }

            $this->execute($sql);
        }

        // foreignId()->constrained() 声明的外键（改表路径）
        foreach ($blueprint->getColumns() as $column) {
            if ($column instanceof ForeignIdDefinition && $column->hasForeignKey()) {
                $this->execute(
                    'ALTER TABLE ' . $this->wrapId($table) . ' ADD CONSTRAINT '
                    . $this->wrapId($this->createIndexName($table, [(string) $column->name], 'foreign'))
                    . ' ' . $this->getForeignIdSql($column)
                );
            }
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
        $wrapColumns = fn (array $columns) => implode(', ', array_map(fn ($c) => $this->wrapId($c), $columns));

        // 数组形式索引名：MySQL 要求每条 DROP 一条语句（dropForeign 先例）
        foreach (['dropForeign', 'dropUnique', 'dropIndex', 'dropFullText', 'dropSpatialIndex'] as $dropType) {
            if ($command['type'] === $dropType) {
                $keyword = $dropType === 'dropForeign' ? 'FOREIGN KEY' : 'INDEX';

                foreach ((array) $command['index'] as $index) {
                    $this->execute('ALTER TABLE ' . $this->wrapId($table) . " DROP {$keyword} " . $this->wrapId($index));
                }

                return;
            }
        }

        $sql = match ($command['type']) {
            'renameColumn' => 'ALTER TABLE ' . $this->wrapId($table) . ' RENAME COLUMN ' . $this->wrapId($command['from']) . ' TO ' . $this->wrapId($command['to']),
            'dropColumn' => 'ALTER TABLE ' . $this->wrapId($table) . ' DROP COLUMN ' . $wrapColumns($command['columns']),
            'renameTable' => 'RENAME TABLE ' . $this->wrapId($table) . ' TO ' . $this->wrapId($this->prefix . $command['to']),
            'dropPrimary' => 'ALTER TABLE ' . $this->wrapId($table) . ' DROP PRIMARY KEY',
            'dropUnique' => 'ALTER TABLE ' . $this->wrapId($table) . ' DROP INDEX ' . $this->wrapId($command['index']),
            'dropIndex' => 'ALTER TABLE ' . $this->wrapId($table) . ' DROP INDEX ' . $this->wrapId($command['index']),
            'primary' => 'ALTER TABLE ' . $this->wrapId($table) . ' ADD PRIMARY KEY (' . $wrapColumns((array) $command['columns']) . ')',
            'unique' => 'ALTER TABLE ' . $this->wrapId($table) . ' ADD UNIQUE KEY ' . $this->wrapId($command['name'] ?? $this->createIndexName($table, $command['columns'], 'unique')) . ' (' . $wrapColumns((array) $command['columns']) . ')',
            'index' => 'ALTER TABLE ' . $this->wrapId($table) . ' ADD KEY ' . $this->wrapId($command['name'] ?? $this->createIndexName($table, $command['columns'], 'index')) . ' (' . $wrapColumns((array) $command['columns']) . ')',
            'fulltext' => 'ALTER TABLE ' . $this->wrapId($table) . ' ADD FULLTEXT KEY ' . $this->wrapId($command['name'] ?? $this->createIndexName($table, $command['columns'], 'fulltext')) . ' (' . $wrapColumns((array) $command['columns']) . ')',
            'spatialIndex' => 'ALTER TABLE ' . $this->wrapId($table) . ' ADD SPATIAL KEY ' . $this->wrapId($command['name'] ?? $this->createIndexName($table, $command['columns'], 'spatialindex')) . ' (' . $wrapColumns((array) $command['columns']) . ')',
            'modifyColumn' => 'ALTER TABLE ' . $this->wrapId($table) . ' MODIFY COLUMN ' . $this->getModifiedColumnSql($command),
            'foreign' => 'ALTER TABLE ' . $this->wrapId($table) . ' ADD CONSTRAINT '
                . $this->wrapId($command['name'] ?? $this->createIndexName($table, $command['columns'], 'foreign'))
                . ' FOREIGN KEY (' . $wrapColumns((array) $command['columns']) . ') REFERENCES '
                . $this->wrapId((string) $command['on']) . '(' . $this->wrapId((string) $command['references']) . ')'
                . ($command['onDelete'] ? " ON DELETE {$command['onDelete']}" : '')
                . ($command['onUpdate'] ? " ON UPDATE {$command['onUpdate']}" : ''),
            default => '',
        };

        if ($sql !== '') {
            $this->execute($sql);
        }
    }

    /**
     * 命令式 modifyColumn 的列 SQL（把 attributes 映射回列定义修饰符）
     */
    protected function getModifiedColumnSql(array $command): string
    {
        $definition = new ColumnDefinition($command['newType'], $command['name']);

        foreach ($command['attributes'] as $property => $value) {
            if (property_exists($definition, $property)) {
                $definition->$property = $value;
            }
        }

        return $this->getColumnSql($definition);
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
