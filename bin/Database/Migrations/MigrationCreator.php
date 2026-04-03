<?php

declare(strict_types=1);

namespace Bin\Database\Migrations;

/**
 * 迁移文件生成器
 */
class MigrationCreator
{
    protected string $path;

    protected string $namespace = 'Database\\Migrations';

    public function __construct(string $path = '')
    {
        $this->path = $path ?: basePath('/database/migrations');
    }

    /**
     * 创建迁移文件
     */
    public function create(string $name, string $table = null): string
    {
        $this->ensureDirectoryExists();

        $filename = $this->getFilename($name);

        $path = $this->path . '/' . $filename;

        $stub = $this->getStub($table);

        $stub = $this->populateStub($name, $stub, $table);

        file_put_contents($path, $stub);

        return $path;
    }

    /**
     * 获取文件名
     */
    protected function getFilename(string $name): string
    {
        $prefix = date('Y_m_d_His');

        $name = strtolower(str_replace(' ', '_', $name));

        return "{$prefix}_{$name}.php";
    }

    /**
     * 获取模板
     */
    protected function getStub(?string $table): string
    {
        if ($table !== null) {
            return $this->getTableStub($table);
        }

        return $this->getBlankStub();
    }

    /**
     * 获取空白模板
     */
    protected function getBlankStub(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

use Bin\Database\Schema\Schema;
use Bin\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        //
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
PHP;
    }

    /**
     * 获取表模板
     */
    protected function getTableStub(string $table): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

use Bin\Database\Schema\Schema;
use Bin\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('{{table}}', function ($table) {
            $table->id();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('{{table}}');
    }
};
PHP;
    }

    /**
     * 填充模板
     */
    protected function populateStub(string $name, string $stub, ?string $table): string
    {
        if ($table !== null) {
            $stub = str_replace('{{table}}', $table, $stub);
        }

        return $stub;
    }

    /**
     * 确保目录存在
     */
    protected function ensureDirectoryExists(): void
    {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    /**
     * 设置路径
     */
    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * 设置命名空间
     */
    public function setNamespace(string $namespace): self
    {
        $this->namespace = $namespace;
        return $this;
    }
}
