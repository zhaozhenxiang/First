<?php

declare(strict_types=1);

namespace Bin\Database\Schema;

use PDO;
use Bin\Database\QueryBuilder;

/**
 * 表结构蓝图 - 用于定义表结构
 */
class Blueprint
{
    protected string $table;

    protected PDO $connection;

    protected array $columns = [];

    protected array $commands = [];

    protected string $engine = 'InnoDB';

    protected string $charset = 'utf8mb4';

    protected string $collation = 'utf8mb4_unicode_ci';

    protected bool $autoIncrement = true;

    protected string $primaryKey = 'id';

    protected bool $timestamps = true;

    protected string $createdAt = 'created_at';

    protected string $updatedAt = 'updated_at';

    protected bool $softDeletes = false;

    protected string $deletedAt = 'deleted_at';

    /** @var array<ColumnDefinition> */
    protected array $columnDefinitions = [];

    public function __construct(string $table, PDO $connection)
    {
        $this->table = $table;
        $this->connection = $connection;
    }

    /**
     * 添加主键列
     */
    public function id(): ColumnDefinition
    {
        $this->primaryKey = 'id';
        return $this->bigIncrements('id');
    }

    /**
     * 添加外键列（支持 constrained()/references()/on()/cascadeOnDelete() 流式链）
     */
    public function foreignId(string $column): ForeignIdDefinition
    {
        $definition = new ForeignIdDefinition($this, $column);

        $this->columnDefinitions[] = $definition;

        return $definition;
    }

    /**
     * 外键约束
     */
    public function foreign(string $column, ?string $table = null, string $columnOnTable = 'id'): ForeignKey
    {
        $referencedTable = $table ?? str_replace('_id', '', $column);

        return new ForeignKey($column, $referencedTable, $columnOnTable, $this);
    }

    /**
     * BIG INTEGER 自增
     */
    public function bigIncrements(string $column): ColumnDefinition
    {
        return $this->unsignedBigInteger($column)->autoIncrement()->primary();
    }

    /**
     * INTEGER 自增
     */
    public function increments(string $column): ColumnDefinition
    {
        return $this->unsignedInteger($column)->autoIncrement()->primary();
    }

    /**
     * TINY INTEGER 自增
     */
    public function tinyIncrements(string $column): ColumnDefinition
    {
        return $this->unsignedTinyInteger($column)->autoIncrement()->primary();
    }

    /**
     * SMALL INTEGER 自增
     */
    public function smallIncrements(string $column): ColumnDefinition
    {
        return $this->unsignedSmallInteger($column)->autoIncrement()->primary();
    }

    /**
     * MEDIUM INTEGER 自增
     */
    public function mediumIncrements(string $column): ColumnDefinition
    {
        return $this->unsignedMediumInteger($column)->autoIncrement()->primary();
    }

    /**
     * BIG INTEGER
     */
    public function bigInteger(string $column, bool $autoIncrement = false): ColumnDefinition
    {
        $def = $this->addColumn('bigInteger', $column);
        $def->unsigned(false);
        if ($autoIncrement) {
            $def->autoIncrement();
        }
        return $def;
    }

    /**
     * 无符号 BIG INTEGER
     */
    public function unsignedBigInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('bigInteger', $column)->unsigned();
    }

    /**
     * INTEGER
     */
    public function integer(string $column, bool $autoIncrement = false): ColumnDefinition
    {
        $def = $this->addColumn('integer', $column);
        $def->unsigned(false);
        if ($autoIncrement) {
            $def->autoIncrement();
        }
        return $def;
    }

    /**
     * 无符号 INTEGER
     */
    public function unsignedInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('integer', $column)->unsigned();
    }

    /**
     * TINY INTEGER
     */
    public function tinyInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('tinyInteger', $column);
    }

    /**
     * 无符号 TINY INTEGER
     */
    public function unsignedTinyInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('tinyInteger', $column)->unsigned();
    }

    /**
     * SMALL INTEGER
     */
    public function smallInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('smallInteger', $column);
    }

    /**
     * 无符号 SMALL INTEGER
     */
    public function unsignedSmallInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('smallInteger', $column)->unsigned();
    }

    /**
     * MEDIUM INTEGER
     */
    public function mediumInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('mediumInteger', $column);
    }

    /**
     * 无符号 MEDIUM INTEGER
     */
    public function unsignedMediumInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('mediumInteger', $column)->unsigned();
    }

    /**
     * VARCHAR
     */
    public function string(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn('string', $column)->length($length);
    }

    /**
     * TEXT
     */
    public function text(string $column): ColumnDefinition
    {
        return $this->addColumn('text', $column);
    }

    /**
     * LONG TEXT
     */
    public function longText(string $column): ColumnDefinition
    {
        return $this->addColumn('longText', $column);
    }

    /**
     * MEDIUM TEXT
     */
    public function mediumText(string $column): ColumnDefinition
    {
        return $this->addColumn('mediumText', $column);
    }

    /**
     * TINY TEXT
     */
    public function tinyText(string $column): ColumnDefinition
    {
        return $this->addColumn('tinyText', $column);
    }

    /**
     * DECIMAL
     */
    public function decimal(string $column, int $total = 8, int $places = 2): ColumnDefinition
    {
        return $this->addColumn('decimal', $column)->total($total)->places($places);
    }

    /**
     * UNSIGNED DECIMAL
     */
    public function unsignedDecimal(string $column, int $total = 8, int $places = 2): ColumnDefinition
    {
        return $this->decimal($column, $total, $places)->unsigned();
    }

    /**
     * DOUBLE
     */
    public function double(string $column, ?int $total = null, ?int $places = null): ColumnDefinition
    {
        $def = $this->addColumn('double', $column);
        if ($total !== null) {
            $def->total($total);
        }
        if ($places !== null) {
            $def->places($places);
        }
        return $def;
    }

    /**
     * FLOAT
     */
    public function float(string $column, ?int $total = null, ?int $places = null): ColumnDefinition
    {
        $def = $this->addColumn('float', $column);
        if ($total !== null) {
            $def->total($total);
        }
        if ($places !== null) {
            $def->places($places);
        }
        return $def;
    }

    /**
     * BOOLEAN
     */
    public function boolean(string $column): ColumnDefinition
    {
        return $this->addColumn('boolean', $column);
    }

    /**
     * ENUM
     */
    public function enum(string $column, array $allowed): ColumnDefinition
    {
        return $this->addColumn('enum', $column)->allowed($allowed);
    }

    /**
     * SET
     */
    public function set(string $column, array $allowed): ColumnDefinition
    {
        return $this->addColumn('set', $column)->allowed($allowed);
    }

    /**
     * DATE
     */
    public function date(string $column): ColumnDefinition
    {
        return $this->addColumn('date', $column);
    }

    /**
     * DATETIME
     */
    public function dateTime(string $column, int $precision = 0): ColumnDefinition
    {
        return $this->addColumn('dateTime', $column)->precision($precision);
    }

    /**
     * DATETIME with timezone
     */
    public function dateTimeTz(string $column, int $precision = 0): ColumnDefinition
    {
        return $this->dateTime($column, $precision);
    }

    /**
     * TIME
     */
    public function time(string $column, int $precision = 0): ColumnDefinition
    {
        return $this->addColumn('time', $column)->precision($precision);
    }

    /**
     * TIME with timezone
     */
    public function timeTz(string $column, int $precision = 0): ColumnDefinition
    {
        return $this->time($column, $precision);
    }

    /**
     * TIMESTAMP
     */
    public function timestamp(string $column, int $precision = 0): ColumnDefinition
    {
        return $this->addColumn('timestamp', $column)->precision($precision);
    }

    /**
     * TIMESTAMP with timezone
     */
    public function timestampTz(string $column, int $precision = 0): ColumnDefinition
    {
        return $this->timestamp($column, $precision);
    }

    /**
     * TIMESTAMP (useCurrent)
     */
    public function timestamps(int $precision = 0): void
    {
        $this->timestamp('created_at', $precision)->nullable()->useCurrent();
        $this->timestamp('updated_at', $precision)->nullable()->useCurrentOnUpdate();
    }

    /**
     * TIMESTAMP TZ
     */
    public function timestampsTz(int $precision = 0): void
    {
        $this->timestamps($precision);
    }

    /**
     * YEAR
     */
    public function year(string $column): ColumnDefinition
    {
        return $this->addColumn('year', $column);
    }

    /**
     * BINARY
     */
    public function binary(string $column, ?int $length = null): ColumnDefinition
    {
        $def = $this->addColumn('binary', $column);
        if ($length !== null) {
            $def->length($length);
        }
        return $def;
    }

    /**
     * JSON
     */
    public function json(string $column): ColumnDefinition
    {
        return $this->addColumn('json', $column);
    }

    /**
     * JSONB
     */
    public function jsonb(string $column): ColumnDefinition
    {
        return $this->addColumn('jsonb', $column);
    }

    /**
     * UUID
     */
    public function uuid(string $column = 'uuid'): ColumnDefinition
    {
        return $this->addColumn('uuid', $column);
    }

    /**
     * IP ADDRESS
     */
    public function ipAddress(string $column = 'ip_address'): ColumnDefinition
    {
        return $this->string($column, 45);
    }

    /**
     * MAC ADDRESS
     */
    public function macAddress(string $column = 'mac_address'): ColumnDefinition
    {
        return $this->string($column, 17);
    }

    /**
     * GEOMETRY / SPATIAL
     */
    public function geometry(string $column): ColumnDefinition
    {
        return $this->addColumn('geometry', $column);
    }

    public function point(string $column): ColumnDefinition
    {
        return $this->addColumn('point', $column);
    }

    public function lineString(string $column): ColumnDefinition
    {
        return $this->addColumn('lineString', $column);
    }

    public function polygon(string $column): ColumnDefinition
    {
        return $this->addColumn('polygon', $column);
    }

    public function geometryCollection(string $column): ColumnDefinition
    {
        return $this->addColumn('geometryCollection', $column);
    }

    /**
     * 软删除
     */
    public function softDeletes(string $column = 'deleted_at', int $precision = 0): void
    {
        $this->timestamp($column, $precision)->nullable();
        $this->softDeletes = true;
        $this->deletedAt = $column;
    }

    /**
     * 软删除 TZ
     */
    public function softDeletesTz(string $column = 'deleted_at', int $precision = 0): void
    {
        $this->softDeletes($column, $precision);
    }

    /**
     * 可为 null —— 已移除
     *
     * Blueprint 级 nullable() 会静默修改之前定义的所有列（包括 id()），
     * 与 Laravel 语义相悖（Laravel 中该方法不存在，误用应立即报错）。
     * 可空性请逐列设置：$table->string('x')->nullable()。
     */
    public function nullable(): self
    {
        throw new \BadMethodCallException(
            'Blueprint::nullable() 会影响所有已定义列，已被移除。请使用列级 $table->string(\'x\')->nullable()。'
        );
    }

    /**
     * 默认值 —— 已移除（理由同 nullable()）
     */
    public function default(mixed $value): self
    {
        throw new \BadMethodCallException(
            'Blueprint::default() 会影响所有已定义列，已被移除。请使用列级 $table->string(\'x\')->default($value)。'
        );
    }

    /**
     * 添加列
     */
    protected function addColumn(string $type, string $name): ColumnDefinition
    {
        $column = new ColumnDefinition($type, $name);
        $this->columnDefinitions[] = $column;
        return $column;
    }

    /**
     * 修改列（命令式）
     *
     * $attributes 支持与列修饰符同名的键（nullable/default/unsigned/length 等）。
     * 流式写法：$table->string('name', 100)->change()。
     */
    public function modifyColumn(string $name, string $newType, array $attributes = []): void
    {
        $this->commands[] = [
            'type' => 'modifyColumn',
            'name' => $name,
            'newType' => $newType,
            'attributes' => $attributes,
        ];
    }

    /**
     * 重命名列
     */
    public function renameColumn(string $from, string $to): void
    {
        $this->commands[] = [
            'type' => 'renameColumn',
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * 多态列对：{name}_type + {name}_id（unsignedBigInteger）+ 联合索引
     */
    public function morphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type");
        $this->unsignedBigInteger("{$name}_id");

        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * 可空多态列对
     */
    public function nullableMorphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type")->nullable();
        $this->unsignedBigInteger("{$name}_id")->nullable();

        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * UUID 多态列对
     */
    public function uuidMorphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type");
        $this->uuid("{$name}_id");

        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * remember_token 列（string(100) nullable）
     */
    public function rememberToken(): ColumnDefinition
    {
        return $this->string('remember_token', 100)->nullable();
    }

    /**
     * 删除列
     */
    public function dropColumn(string|array $columns): void
    {
        $this->commands[] = [
            'type' => 'dropColumn',
            'columns' => is_array($columns) ? $columns : [$columns],
        ];
    }

    /**
     * 重命名表
     */
    public function rename(string $to): void
    {
        $this->commands[] = [
            'type' => 'renameTable',
            'to' => $to,
        ];
    }

    /**
     * 删除主键
     */
    public function dropPrimary(?string $index = null): void
    {
        $this->commands[] = [
            'type' => 'dropPrimary',
            'index' => $index,
        ];
    }

    /**
     * 删除唯一索引（接受索引名或列数组，列数组按命名规则推导索引名）
     */
    public function dropUnique(string|array $index): void
    {
        $this->commands[] = [
            'type' => 'dropUnique',
            'index' => [$this->resolveDropIndexName($index, 'unique')],
        ];
    }

    /**
     * 删除索引（接受索引名或列数组）
     */
    public function dropIndex(string|array $index): void
    {
        $this->commands[] = [
            'type' => 'dropIndex',
            'index' => [$this->resolveDropIndexName($index, 'index')],
        ];
    }

    /**
     * 删除全文索引（接受索引名或列数组）
     */
    public function dropFullText(string|array $index): void
    {
        $this->commands[] = [
            'type' => 'dropFullText',
            'index' => [$this->resolveDropIndexName($index, 'fulltext')],
        ];
    }

    /**
     * 删除空间索引（接受索引名或列数组）
     */
    public function dropSpatialIndex(string|array $index): void
    {
        $this->commands[] = [
            'type' => 'dropSpatialIndex',
            'index' => [$this->resolveDropIndexName($index, 'spatialindex')],
        ];
    }

    /**
     * 解析 drop 系列的索引名：字符串视为索引名，数组视为列并按命名规则推导
     */
    protected function resolveDropIndexName(string|array $index, string $type): string
    {
        if (is_string($index)) {
            return $index;
        }

        return strtolower($this->table . '_' . implode('_', $index) . '_' . $type);
    }

    /**
     * 删除外键
     */
    public function dropForeign(string|array $index): void
    {
        $this->commands[] = [
            'type' => 'dropForeign',
            'index' => is_array($index) ? $index : [$index],
        ];
    }

    /**
     * 主键
     */
    public function primary(string|array $columns, ?string $name = null): void
    {
        $this->commands[] = [
            'type' => 'primary',
            'columns' => is_array($columns) ? $columns : [$columns],
            'name' => $name,
        ];
    }

    /**
     * 唯一索引
     */
    public function unique(string|array $columns, ?string $name = null, ?string $algorithm = null): void
    {
        $this->commands[] = [
            'type' => 'unique',
            'columns' => is_array($columns) ? $columns : [$columns],
            'name' => $name,
            'algorithm' => $algorithm,
        ];
    }

    /**
     * 索引
     */
    public function index(string|array $columns, ?string $name = null, ?string $algorithm = null): void
    {
        $this->commands[] = [
            'type' => 'index',
            'columns' => is_array($columns) ? $columns : [$columns],
            'name' => $name,
            'algorithm' => $algorithm,
        ];
    }

    /**
     * 全文索引
     */
    public function fullText(string|array $columns, ?string $name = null, ?string $algorithm = null): void
    {
        $this->commands[] = [
            'type' => 'fulltext',
            'columns' => is_array($columns) ? $columns : [$columns],
            'name' => $name,
            'algorithm' => $algorithm,
        ];
    }

    /**
     * 空间索引
     */
    public function spatialIndex(string|array $columns, ?string $name = null): void
    {
        $this->commands[] = [
            'type' => 'spatialIndex',
            'columns' => is_array($columns) ? $columns : [$columns],
            'name' => $name,
        ];
    }

    /**
     * 外键约束
     */
    public function foreignKey(
        string|array $columns,
        ?string $name = null,
        ?string $on = null,
        string $references = 'id',
        ?string $onDelete = null,
        ?string $onUpdate = null
    ): void {
        $this->commands[] = [
            'type' => 'foreign',
            'columns' => is_array($columns) ? $columns : [$columns],
            'name' => $name,
            'on' => $on,
            'references' => $references,
            'onDelete' => $onDelete,
            'onUpdate' => $onUpdate,
        ];
    }

    /**
     * 设置引擎
     */
    public function engine(string $engine): self
    {
        $this->engine = $engine;
        return $this;
    }

    /**
     * 设置字符集
     */
    public function charset(string $charset): self
    {
        $this->charset = $charset;
        return $this;
    }

    /**
     * 设置排序规则
     */
    public function collation(string $collation): self
    {
        $this->collation = $collation;
        return $this;
    }

    /**
     * 获取列定义
     */
    public function getColumns(): array
    {
        return $this->columnDefinitions;
    }

    /**
     * 获取命令
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * 获取表名
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * 获取连接
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * 获取引擎
     */
    public function getEngine(): string
    {
        return $this->engine;
    }

    /**
     * 获取字符集
     */
    public function getCharset(): string
    {
        return $this->charset;
    }

    /**
     * 获取排序规则
     */
    public function getCollation(): string
    {
        return $this->collation;
    }
}
