<?php

declare(strict_types=1);

namespace Bin\Database\Schema;

/**
 * 外键列定义 - foreignId()->constrained() 流式链
 *
 * 支持 Laravel 风格:
 *   $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
 *   $table->foreignId('user_id')->constrained();            // 表名按列名推导
 *   $table->foreignId('user_id')->references('uuid')->on('users')->nullOnDelete();
 *
 * 列本身就是外键列（UNSIGNED BIGINT），constrained()/references()/on()
 * 补充引用信息后由 SchemaBuilder 在建表/改表时一并生成外键约束，
 * 无需手动调用 ->add()。
 */
class ForeignIdDefinition extends ColumnDefinition
{
    public ?string $foreignTable = null;

    public string $foreignColumn = 'id';

    public ?string $onDelete = null;

    public ?string $onUpdate = null;

    public function __construct(protected Blueprint $blueprint, string $column)
    {
        parent::__construct('bigInteger', $column);

        $this->unsigned();
    }

    /**
     * 声明外键约束（表名缺省按列名推导: user_id → users）
     */
    public function constrained(?string $table = null, string $column = 'id'): self
    {
        $this->foreignTable = $table ?? $this->guessRelatedTable((string) $this->name);
        $this->foreignColumn = $column;

        return $this;
    }

    /**
     * 引用列
     */
    public function references(string $column): self
    {
        $this->foreignColumn = $column;

        return $this;
    }

    /**
     * 引用表
     */
    public function on(string $table): self
    {
        $this->foreignTable = $table;

        return $this;
    }

    public function cascadeOnDelete(): self
    {
        $this->onDelete = 'cascade';

        return $this;
    }

    public function cascadeOnUpdate(): self
    {
        $this->onUpdate = 'cascade';

        return $this;
    }

    public function restrictOnDelete(): self
    {
        $this->onDelete = 'restrict';

        return $this;
    }

    public function restrictOnUpdate(): self
    {
        $this->onUpdate = 'restrict';

        return $this;
    }

    public function nullOnDelete(): self
    {
        $this->onDelete = 'set null';

        return $this;
    }

    public function nullOnUpdate(): self
    {
        $this->onUpdate = 'set null';

        return $this;
    }

    /**
     * 是否声明了外键约束
     */
    public function hasForeignKey(): bool
    {
        return $this->foreignTable !== null;
    }

    /**
     * 按列名推导引用表（复数 + 常见不规则形式）
     */
    protected function guessRelatedTable(string $column): string
    {
        $base = str_ends_with($column, '_id') ? substr($column, 0, -3) : $column;

        $irregular = [
            'category' => 'categories',
            'person' => 'people',
            'child' => 'children',
            'man' => 'men',
            'woman' => 'women',
        ];

        return $irregular[$base] ?? $base . 's';
    }
}
