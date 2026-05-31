<?php

declare(strict_types=1);

namespace Bin\Database\Seeders;

/**
 * Seeder 文件生成器
 */
class SeederCreator
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? basePath('database/seeders');
    }

    /**
     * 创建 Seeder 文件
     */
    public function create(string $name, ?string $path = null): string
    {
        $path = $path ?? $this->path;

        // 确保目录存在
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        // 文件名
        $fileName = $name . 'Seeder.php';
        $filePath = $path . '/' . $fileName;

        // 检查是否已存在
        if (file_exists($filePath)) {
            throw new \RuntimeException("Seeder already exists: {$fileName}");
        }

        // 获取内容
        $content = $this->getStub($name);

        // 写入文件
        file_put_contents($filePath, $content);

        return $filePath;
    }

    /**
     * 获取模板内容
     */
    protected function getStub(string $name): string
    {
        $className = $name . 'Seeder';
        $lowerName = strtolower($name);

        return <<<PHP
<?php

declare(strict_types=1);

namespace Database\Seeders;

use Bin\Database\Seeders\Seeder;

/**
 * {$name} Seeder
 *
 * 填充 {$lowerName} 相关数据
 */
class {$className} extends Seeder
{
    /**
     * 执行填充
     */
    public function run(): void
    {
        // 在这里编写你的数据填充逻辑
        // 例如:
        //
        // \$this->create(Model::class, [
        //     'name' => 'Example',
        //     'email' => 'example@test.com',
        // ]);
        //
        // 或使用工厂:
        //
        // \$this->factory(Model::class)
        //     ->state('active', fn() => ['status' => 'active'])
        //     ->createMany(10);
    }
}

PHP;
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
     * 获取路径
     */
    public function getPath(): string
    {
        return $this->path;
    }
}
